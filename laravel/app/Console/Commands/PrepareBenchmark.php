<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\CommonAreaSeeder;
use Database\Seeders\OccurrenceTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrepareBenchmark extends Command
{
    protected $signature = 'benchmark:prepare {profile=small : small, medium, large} {--seed=2026} {--users=} {--units=} {--reservations=} {--occurrences=} {--histories=} {--notices=}';

    protected $description = 'Create deterministic synthetic benchmark data in an isolated database';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'condovale_benchmark') {
            $this->error('Only the isolated MySQL database condovale_benchmark is accepted.');
            return self::FAILURE;
        }
        $profiles = [
            'small' => [100, 50, 500, 300, 450, 60],
            'medium' => [1200, 500, 10000, 6000, 9000, 500],
            'large' => [5000, 2000, 50000, 30000, 45000, 2000],
        ];
        if (! isset($profiles[$this->argument('profile')])) {
            $this->error('Unknown profile.');
            return self::FAILURE;
        }
        $counts = array_combine(['users', 'units', 'reservations', 'occurrences', 'histories', 'notices'], $profiles[$this->argument('profile')]);
        foreach ($counts as $key => $value) {
            if ($this->option($key) !== null) {
                $counts[$key] = filter_var($this->option($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            }
            if ($counts[$key] < 1) {
                $this->error("{$key} must be positive.");
                return self::FAILURE;
            }
        }
        if ($counts['users'] < 4) {
            $this->error('At least four users are required for admin, syndic and resident roles.');
            return self::FAILURE;
        }
        $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);
        if ($seed === false) {
            return self::FAILURE;
        }
        $signature = hash('sha256', json_encode([$this->argument('profile'), $seed, $counts]));
        if (DB::table('users')->exists()) {
            $stored = DB::table('cache')->where('key', 'benchmark_prepare_signature')->value('value');
            if ($stored === $signature && collect($counts)->every(fn ($expected, $table) => DB::table($table)->count() === $expected)) {
                $this->info('Benchmark data already prepared with this profile and seed.');
                return self::SUCCESS;
            }
            $this->error('Database is not empty or differs from the requested profile. Reset the disposable volume with the benchmark runner.');
            return self::FAILURE;
        }
        foreach ([PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class, CommonAreaSeeder::class, OccurrenceTypeSeeder::class] as $seeder) {
            app($seeder)->run();
        }
        $stamp = '2026-01-01 00:00:00';
        $plainPassword = getenv('BENCHMARK_PASSWORD');
        if (! is_string($plainPassword) || strlen($plainPassword) < 24) {
            $this->error('BENCHMARK_PASSWORD must contain at least 24 characters.');
            return self::FAILURE;
        }
        $password = Hash::make($plainPassword);
        $roles = DB::table('roles')->pluck('id', 'name');
        $users = [];
        $roleLinks = [];
        for ($i = 1; $i <= $counts['users']; $i++) {
            $role = $i === 1 ? 'admin' : ($i === 2 ? 'syndic' : ($i % 20 === 0 ? 'employee' : 'resident'));
            $users[] = ['id' => $i, 'name' => "Benchmark User {$i}", 'email' => "vu{$i}@benchmark.invalid", 'password' => $password, 'role' => $role, 'status' => 'active', 'must_change_password' => false, 'created_at' => $stamp, 'updated_at' => $stamp];
            $roleLinks[] = ['role_id' => $roles[$role], 'model_type' => User::class, 'model_id' => $i];
        }
        foreach (array_chunk($users, 500) as $chunk) DB::table('users')->insert($chunk);
        foreach (array_chunk($roleLinks, 500) as $chunk) DB::table('model_has_roles')->insert($chunk);
        $unitRows = [];
        for ($i = 1; $i <= $counts['units']; $i++) $unitRows[] = ['id' => $i, 'block' => 'Benchmark', 'number' => (string) $i, 'code' => "BENCH-{$i}", 'status' => 'active', 'created_at' => $stamp, 'updated_at' => $stamp];
        foreach (array_chunk($unitRows, 500) as $chunk) DB::table('units')->insert($chunk);
        $residentIds = array_column(array_filter($users, fn ($user) => $user['role'] === 'resident'), 'id');
        $occupancies = [];
        foreach ($residentIds as $index => $id) {
            $unitId = $index % $counts['units'] + 1;
            $occupancies[] = ['unit_id' => $unitId, 'user_id' => $id, 'occupant_type' => 'tenant', 'started_at' => '2025-01-01', 'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp];
            DB::table('users')->where('id', $id)->update(['unit_id' => $unitId]);
        }
        foreach (array_chunk($occupancies, 500) as $chunk) DB::table('unit_occupancies')->insert($chunk);
        $areaIds = DB::table('common_areas')->pluck('id')->all();
        $typeIds = DB::table('occurrence_types')->pluck('id')->all();
        $this->insertRows('reservations', $counts['reservations'], function ($i) use ($residentIds, $areaIds, $seed) {
            $day = gmdate('Y-m-d', strtotime('2025-01-01 UTC') + $this->bucket($seed, 'reservation-day', $i, 365) * 86400);
            return ['common_area_id' => $areaIds[$this->bucket($seed, 'reservation-area', $i, count($areaIds))], 'resident_id' => $residentIds[$this->bucket($seed, 'reservation-user', $i, count($residentIds))], 'date' => $day, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'status' => $i % 10 < 6 ? 'approved' : ($i % 10 < 8 ? 'cancelled' : 'rejected'), 'created_at' => "$day 10:00:00", 'updated_at' => "$day 10:00:00"];
        });
        $occurrenceOwners = [];
        $occurrenceDays = [];
        $this->insertRows('occurrences', $counts['occurrences'], function ($i) use ($residentIds, $typeIds, $counts, $seed, &$occurrenceOwners, &$occurrenceDays) {
            $day = gmdate('Y-m-d', strtotime('2025-01-01 UTC') + $this->bucket($seed, 'occurrence-day', $i, 365) * 86400);
            $residentIndex = $this->bucket($seed, 'occurrence-user', $i, count($residentIds));
            $occurrenceOwners[$i] = $residentIds[$residentIndex];
            $occurrenceDays[$i] = $day;
            return ['title' => "Benchmark occurrence {$i}", 'description' => 'Synthetic maintenance request', 'occurrence_type_id' => $typeIds[$this->bucket($seed, 'occurrence-type', $i, count($typeIds))], 'resident_id' => $residentIds[$residentIndex], 'unit_id' => $residentIndex % $counts['units'] + 1, 'status' => 'open', 'created_at' => "$day 10:00:00", 'updated_at' => "$day 10:00:00"];
        });
        $this->insertRows('occurrence_histories', $counts['histories'], function ($i) use ($counts, $occurrenceOwners, $occurrenceDays) {
            $occurrenceId = ($i - 1) % $counts['occurrences'] + 1;
            $day = $occurrenceDays[$occurrenceId];
            return ['occurrence_id' => $occurrenceId, 'user_id' => $occurrenceOwners[$occurrenceId], 'type' => 'created', 'new_status' => 'open', 'created_at' => "$day 11:00:00", 'updated_at' => "$day 11:00:00"];
        });
        $this->insertRows('notices', $counts['notices'], function ($i) use ($seed) {
            $day = gmdate('Y-m-d', strtotime('2025-01-01 UTC') + $this->bucket($seed, 'notice-day', $i, 365) * 86400);
            return ['title' => "Benchmark notice {$i}", 'content' => 'Synthetic announcement', 'author_id' => 2, 'status' => 'published', 'published_at' => "$day 09:00:00", 'created_at' => "$day 09:00:00", 'updated_at' => "$day 09:00:00"];
        });
        foreach ($counts as $table => $expected) {
            $actual = DB::table($table)->count();
            if ($actual !== $expected) throw new \RuntimeException("{$table}: expected {$expected}, got {$actual}");
        }
        $invalidLinks = DB::table('users as u')
            ->join('unit_occupancies as o', 'o.user_id', '=', 'u.id')
            ->where('o.is_active', true)
            ->whereColumn('u.unit_id', '!=', 'o.unit_id')
            ->count();
        if ($invalidLinks !== 0) throw new \RuntimeException("{$invalidLinks} inconsistent resident unit links");
        $invalidOccurrences = DB::table('occurrences as c')
            ->join('users as u', 'u.id', '=', 'c.resident_id')
            ->whereColumn('c.unit_id', '!=', 'u.unit_id')
            ->count();
        if ($invalidOccurrences !== 0) throw new \RuntimeException("{$invalidOccurrences} inconsistent occurrence units");
        DB::table('cache')->insert(['key' => 'benchmark_prepare_signature', 'value' => $signature, 'expiration' => 2147483647]);
        $this->info(json_encode(['profile' => $this->argument('profile'), 'seed' => $seed, 'counts' => $counts]));
        return self::SUCCESS;
    }

    private function insertRows(string $table, int $count, callable $make): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $make($i);
            if (count($rows) === 500) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }
        if ($rows) DB::table($table)->insert($rows);
    }

    private function bucket(int $seed, string $scope, int $index, int $size): int
    {
        $digest = hash_hmac('sha256', "{$scope}:{$index}", (string) $seed);
        return hexdec(substr($digest, 0, 8)) % $size;
    }
}

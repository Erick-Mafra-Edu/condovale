<?php

namespace App\Http\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HandlePaginationAction
{
    /**
     * Oracle rejects IN lists longer than this many entries (ORA-01795).
     */
    private const ORACLE_IN_CLAUSE_LIMIT = 999;

    /**
     * How long the table column listing stays cached.
     */
    private const COLUMN_CACHE_TTL_SECONDS = 86400;

    /**
     * Deepest relation chain a client may request through `relations`.
     */
    private const MAX_RELATION_DEPTH = 3;

    public static function execute(Request $request, Model $model, array $searchColumns = []): Builder
    {
        $tableName = $model->getTable();

        // Cached because the schema only changes on migrations.
        $tableColumns = Cache::remember(
            "table-columns:{$tableName}",
            self::COLUMN_CACHE_TTL_SECONDS,
            fn () => Schema::getColumnListing($tableName)
        );

        $safeColumns = array_diff($tableColumns, $model->getHidden());

        $attributes = $request->get('attributes') ?? '*';
        $selectColumns = $safeColumns;

        if ($attributes !== '*') {
            $requested = array_intersect(array_map('trim', explode(',', $attributes)), $safeColumns);

            if (! empty($requested)) {
                $selectColumns = $requested;
            }
        }

        $response = $model::select($selectColumns);

        $isSafeColumn = fn ($col) => in_array($col, $safeColumns);

        if ($id = $request->get('id')) {
            $response->where('id', $id);
        }

        if ($search = $request->get('search')) {
            $searchUpper = strtoupper($search);

            $response->where(function ($query) use ($searchColumns, $searchUpper, $isSafeColumn) {
                foreach ($searchColumns as $column) {
                    if ($isSafeColumn($column)) {
                        $query->orWhereRaw("UPPER({$column}) LIKE ?", ["%{$searchUpper}%"]);
                    }
                }
            });
        }

        if ($filters = $request->get('filters')) {
            foreach (explode(';', $filters) as $filter) {
                if (trim($filter) === '') {
                    continue;
                }

                $conditions = explode(':', $filter);

                if (count($conditions) === 3) {
                    [$column, $operator, $value] = array_map('trim', $conditions);

                    if ($value !== '' && $isSafeColumn($column)) {
                        $response->where($column, $operator, $value);
                    }
                }
            }
        }

        if ($filtersOr = $request->get('filtersOr')) {
            $allowedOperators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE'];

            // The whole OR block is wrapped, otherwise it would leak out and
            // cancel the scope where() the controller adds afterwards.
            $response->where(function ($query) use ($filtersOr, $allowedOperators, $isSafeColumn) {
                foreach (explode(';', $filtersOr) as $filter) {
                    $conditions = explode(':', $filter);

                    if (count($conditions) === 3) {
                        $column = trim($conditions[0]);
                        $operator = strtoupper(trim($conditions[1]));
                        $value = trim($conditions[2]);

                        if ($isSafeColumn($column) && in_array($operator, $allowedOperators)) {
                            $query->orWhereRaw("UPPER({$column}) {$operator} ?", [strtoupper($value)]);
                        }
                    }
                }
            });
        }

        if ($filtersIn = $request->get('filtersIn')) {
            foreach (explode(';', $filtersIn) as $filter) {
                $conditions = explode(':', $filter);

                if (count($conditions) >= 2) {
                    $column = trim($conditions[0]);

                    if ($isSafeColumn($column)) {
                        // Split into OR'ed chunks so a long list cannot break the query.
                        $chunks = array_chunk(explode(',', $conditions[1]), self::ORACLE_IN_CLAUSE_LIMIT);

                        $response->where(function ($query) use ($column, $chunks) {
                            foreach ($chunks as $chunk) {
                                $query->orWhereIn($column, $chunk);
                            }
                        });
                    }
                }
            }
        }

        $direction = strtolower($request->get('direction') ?? 'asc');
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'asc';

        if (($sort = $request->get('sort')) && $isSafeColumn($sort)) {
            $response->orderBy($sort, $direction);
        }

        // Only relations declared on the model are accepted, and nesting is capped
        // so a single request cannot cascade through the whole domain.
        if ($relations = $request->get('relations')) {
            foreach (explode(';', $relations) as $relation) {
                $relation = trim($relation);

                if ($relation === '' || count(explode('.', $relation)) > self::MAX_RELATION_DEPTH) {
                    continue;
                }

                $relationName = explode(':', explode('.', $relation)[0])[0];

                if (method_exists($model, $relationName)) {
                    $response->with($relation);
                }
            }
        }

        return $response;
    }
}

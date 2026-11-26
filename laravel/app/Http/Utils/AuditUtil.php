<?php

namespace App\Http\Utils;

use Illuminate\Database\Eloquent\Model;

class AuditUtil
{
    /** Columns every save touches, which say nothing about the operation. */
    private const IGNORED = ['created_at', 'updated_at'];

    /**
     * What a save is about to change, as {de, para} with only the columns that
     * really change — the evidence of an edit (RNF04).
     *
     * Call it between fill() and save(): after the save the original already
     * holds the new values, and this Laravel keeps no copy of the old ones.
     * Hidden columns never enter the trail; the password hash is hidden for a
     * reason.
     *
     * @return array{de: array<string, mixed>, para: array<string, mixed>}|array{}
     */
    public static function diff(Model $model): array
    {
        $columns = array_diff(array_keys($model->getDirty()), $model->getHidden(), self::IGNORED);

        if ($columns === []) {
            return [];
        }

        $before = [];
        $after = [];

        foreach ($columns as $column) {
            $old = $model->getOriginal($column);
            $new = $model->getAttribute($column);

            // Dirty compares the raw values: "22:00" over a stored "22:00:00"
            // is dirty, though both read "22:00". Objects compare loosely, so
            // two Carbon instances of the same moment count as equal; scalars
            // strictly, so a capacity going from null to 0 is still recorded.
            if ($old === $new || (is_object($old) && is_object($new) && $old == $new)) {
                continue;
            }

            $before[$column] = $old;
            $after[$column] = $new;
        }

        if ($before === []) {
            return [];
        }

        return ['de' => $before, 'para' => $after];
    }
}

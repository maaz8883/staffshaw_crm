<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $columns = DB::select(<<<'SQL'
            SELECT TABLE_NAME, DATA_TYPE, COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME = 'id'
              AND DATA_TYPE IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
              AND EXTRA NOT LIKE '%auto_increment%'
            ORDER BY TABLE_NAME
            SQL);

        // Validate every affected table before changing any of them.
        foreach ($columns as $column) {
            $table = $this->quoteIdentifier($column->TABLE_NAME);
            $counts = DB::selectOne(<<<SQL
                SELECT
                    COUNT(*) AS total_rows,
                    COUNT(DISTINCT `id`) AS unique_ids,
                    SUM(`id` IS NULL) AS null_ids
                FROM {$table}
                SQL);

            if (
                (int) $counts->total_rows !== (int) $counts->unique_ids
                || (int) $counts->null_ids > 0
            ) {
                throw new RuntimeException(sprintf(
                    'Cannot repair %s.id because duplicate or null IDs exist.',
                    $column->TABLE_NAME
                ));
            }
        }

        foreach ($columns as $column) {
            $tableName = (string) $column->TABLE_NAME;
            $table = $this->quoteIdentifier($tableName);

            $idUniqueIndex = DB::selectOne(<<<'SQL'
                SELECT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'id'
                  AND NON_UNIQUE = 0
                  AND SEQ_IN_INDEX = 1
                LIMIT 1
                SQL, [$tableName]);

            if (! $idUniqueIndex) {
                $primaryKey = DB::selectOne(<<<'SQL'
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND CONSTRAINT_TYPE = 'PRIMARY KEY'
                    LIMIT 1
                    SQL, [$tableName]);

                if ($primaryKey) {
                    $index = $this->quoteIdentifier($tableName.'_id_unique');
                    DB::statement("ALTER TABLE {$table} ADD UNIQUE INDEX {$index} (`id`)");
                } else {
                    DB::statement("ALTER TABLE {$table} ADD PRIMARY KEY (`id`)");
                }
            }

            $type = strtoupper((string) $column->DATA_TYPE);

            if (str_contains(strtolower((string) $column->COLUMN_TYPE), 'unsigned')) {
                $type .= ' UNSIGNED';
            }

            DB::statement(
                "ALTER TABLE {$table} MODIFY `id` {$type} NOT NULL AUTO_INCREMENT"
            );
        }
    }

    public function down(): void
    {
        // This migration repairs a broken schema; rolling it back must not break it again.
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};

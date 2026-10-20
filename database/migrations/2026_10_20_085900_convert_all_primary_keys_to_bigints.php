<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->addBigIntPrimaryKey('cache', 'key', 'id', 'cache_key_unique');
        $this->addBigIntPrimaryKey('cache_locks', 'key', 'id', 'cache_locks_key_unique');
        $this->addBigIntPrimaryKey('job_batches', 'id', 'pk', 'job_batches_id_unique');
        $this->addBigIntPrimaryKey('password_reset_tokens', 'email', 'id', 'password_reset_tokens_email_unique');
        $this->addBigIntPrimaryKey('sessions', 'id', 'pk', 'sessions_id_unique');
        $this->convertCoinAdjustments();

        if ($this->columnType('migrations', 'id') !== 'bigint unsigned') {
            DB::statement('ALTER TABLE `migrations` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void {}

    private function addBigIntPrimaryKey(string $table, string $businessKey, string $primaryKey, string $uniqueIndex): void
    {
        if (! Schema::hasTable($table) || $this->columnType($table, $primaryKey) === 'bigint unsigned') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` DROP PRIMARY KEY, ADD COLUMN `%s` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST, ADD UNIQUE KEY `%s` (`%s`)',
            $table,
            $primaryKey,
            $uniqueIndex,
            $businessKey,
        ));
    }

    private function convertCoinAdjustments(): void
    {
        if (! Schema::hasTable('coin_adjustments') || $this->columnType('coin_adjustments', 'id') === 'bigint unsigned') {
            return;
        }

        DB::statement('ALTER TABLE `wallet_entries` DROP INDEX `wallet_entries_adjustment_id_unique`, CHANGE `adjustment_id` `legacy_adjustment_id` CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE `coin_adjustments` CHANGE `id` `legacy_id` CHAR(36) NOT NULL, DROP PRIMARY KEY, ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST, ADD UNIQUE KEY `coin_adjustments_legacy_id_unique` (`legacy_id`)');
        DB::statement('ALTER TABLE `wallet_entries` ADD COLUMN `adjustment_id` BIGINT UNSIGNED NULL AFTER `user_id`');
        DB::statement('UPDATE `wallet_entries` INNER JOIN `coin_adjustments` ON `coin_adjustments`.`legacy_id` = `wallet_entries`.`legacy_adjustment_id` SET `wallet_entries`.`adjustment_id` = `coin_adjustments`.`id`');
        DB::statement('ALTER TABLE `wallet_entries` MODIFY `adjustment_id` BIGINT UNSIGNED NOT NULL, ADD UNIQUE KEY `wallet_entries_adjustment_id_unique` (`adjustment_id`), ADD CONSTRAINT `wallet_entries_adjustment_id_foreign` FOREIGN KEY (`adjustment_id`) REFERENCES `coin_adjustments` (`id`)');
        DB::statement('ALTER TABLE `wallet_entries` DROP COLUMN `legacy_adjustment_id`');
        DB::statement('ALTER TABLE `coin_adjustments` DROP INDEX `coin_adjustments_legacy_id_unique`, DROP COLUMN `legacy_id`');
    }

    private function columnType(string $table, string $column): ?string
    {
        $record = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first(['COLUMN_TYPE']);

        return $record?->COLUMN_TYPE;
    }
};

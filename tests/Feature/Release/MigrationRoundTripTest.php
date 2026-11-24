<?php

namespace Tests\Feature\Release;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every migration must be reversible (rule: always provide a rollback path): a full
 * reset followed by a fresh migrate must succeed and leave the same schema.
 */
class MigrationRoundTripTest extends TestCase
{
    public function test_all_migrations_roll_back_and_reapply(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $before = $this->schema();
        $this->assertNotEmpty($before);

        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]), Artisan::output());
        // The baseline migration only creates tables missing from SQL-dump installs, so its down()
        // deliberately keeps them (never silently delete data that may predate it).
        $kept = ['migrations', 'sqlite_sequence', 'game_exclusives', 'ggds_spin_config', 'ggds_spin_runs', 'suit_pay_payments'];
        $this->assertSame([], array_values(array_diff($this->tables(), $kept)), 'tables left behind after a full rollback');

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        $this->assertSame($before, $this->schema());
    }

    private function tables(): array
    {
        return collect(Schema::getConnection()->select("SELECT name FROM sqlite_master WHERE type = 'table'"))
            ->pluck('name')->sort()->values()->all();
    }

    private function schema(): array
    {
        $out = [];
        foreach ($this->tables() as $t) {
            $cols = Schema::getColumnListing($t);
            sort($cols);
            $out[$t] = $cols;
        }

        return $out;
    }
}

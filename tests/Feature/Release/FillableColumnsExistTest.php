<?php

namespace Tests\Feature\Release;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every model's table and fillable columns must exist on a schema built from migrations.
 * Drift here means admin forms fail on fresh installs (several did).
 */
class FillableColumnsExistTest extends TestCase
{
    public function test_models_match_the_migrated_schema(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true]);
        $problems = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            $ref = new \ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }
            $model = new $class();
            if (! Schema::hasTable($model->getTable())) {
                $problems[] = "{$class}: table {$model->getTable()} missing";
                continue;
            }
            $missing = array_diff($model->getFillable(), Schema::getColumnListing($model->getTable()));
            if ($missing) {
                $problems[] = "{$class}: ".implode(', ', $missing);
            }
        }

        $this->assertSame([], $problems);
    }
}

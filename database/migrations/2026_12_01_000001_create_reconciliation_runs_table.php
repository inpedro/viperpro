<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Results of wallet-vs-ledger reconciliation runs (hourly scheduler or "Run now"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('trigger', 20); // schedule | manual | cli
            $table->unsignedInteger('buckets_checked')->default(0);
            $table->unsignedInteger('mismatches')->default(0);
            $table->unsignedInteger('unbalanced_journals')->default(0);
            $table->unsignedInteger('observed_journals')->default(0);
            $table->json('details')->nullable(); // first mismatches: wallet, bucket, projection, ledger
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};

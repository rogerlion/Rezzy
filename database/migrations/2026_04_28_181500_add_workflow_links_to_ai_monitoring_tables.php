<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_monitoring_checks')) {
            $this->makeScoresNullable();
        }

        if (Schema::hasTable('ai_monitoring_plans')) {
            Schema::table('ai_monitoring_plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_monitoring_plans', 'article_id')) {
                    $table->foreignId('article_id')
                        ->nullable()
                        ->after('check_id')
                        ->constrained('articles')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('ai_monitoring_plans', 'task_id')) {
                    $table->foreignId('task_id')
                        ->nullable()
                        ->after('article_id')
                        ->constrained('tasks')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_monitoring_plans')) {
            Schema::table('ai_monitoring_plans', function (Blueprint $table): void {
                if (Schema::hasColumn('ai_monitoring_plans', 'task_id')) {
                    $table->dropConstrainedForeignId('task_id');
                }

                if (Schema::hasColumn('ai_monitoring_plans', 'article_id')) {
                    $table->dropConstrainedForeignId('article_id');
                }
            });
        }
    }

    private function makeScoresNullable(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ai_monitoring_checks ALTER COLUMN mention_rank DROP NOT NULL');
            DB::statement('ALTER TABLE ai_monitoring_checks ALTER COLUMN mention_rank DROP DEFAULT');
            DB::statement('ALTER TABLE ai_monitoring_checks ALTER COLUMN visibility_score DROP NOT NULL');
            DB::statement('ALTER TABLE ai_monitoring_checks ALTER COLUMN visibility_score DROP DEFAULT');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE ai_monitoring_checks MODIFY mention_rank SMALLINT UNSIGNED NULL DEFAULT NULL');
            DB::statement('ALTER TABLE ai_monitoring_checks MODIFY visibility_score TINYINT UNSIGNED NULL DEFAULT NULL');
        }
    }
};

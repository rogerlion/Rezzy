<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_monitoring_checks')) {
            Schema::create('ai_monitoring_checks', function (Blueprint $table): void {
                $table->id();
                $table->string('platform', 80);
                $table->text('query_text');
                $table->string('target_brand', 120)->default('');
                $table->boolean('brand_mentioned')->default(false);
                $table->unsignedSmallInteger('mention_rank')->nullable();
                $table->unsignedTinyInteger('visibility_score')->nullable();
                $table->string('sentiment', 20)->default('neutral');
                $table->longText('answer_summary')->nullable();
                $table->longText('mention_context')->nullable();
                $table->text('source_urls')->nullable();
                $table->text('competitors')->nullable();
                $table->text('gap_keywords')->nullable();
                $table->string('status', 20)->default('new');
                $table->string('created_by', 100)->default('');
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->index(['platform', 'status']);
                $table->index(['brand_mentioned', 'checked_at']);
            });
        }

        if (! Schema::hasTable('ai_monitoring_plans')) {
            Schema::create('ai_monitoring_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('check_id')->nullable()->constrained('ai_monitoring_checks')->nullOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
                $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
                $table->string('plan_type', 20)->default('article');
                $table->string('title', 200);
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('todo');
                $table->string('target_channel', 120)->default('');
                $table->string('target_keyword', 200)->default('');
                $table->longText('action_plan')->nullable();
                $table->string('budget_range', 100)->default('');
                $table->date('due_date')->nullable();
                $table->string('created_by', 100)->default('');
                $table->timestamps();

                $table->index(['plan_type', 'status']);
                $table->index(['priority', 'due_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_monitoring_plans');
        Schema::dropIfExists('ai_monitoring_checks');
    }
};

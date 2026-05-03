<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMonitoringPlan extends Model
{
    protected $table = 'ai_monitoring_plans';

    protected $fillable = [
        'check_id',
        'article_id',
        'task_id',
        'plan_type',
        'title',
        'priority',
        'status',
        'target_channel',
        'target_keyword',
        'action_plan',
        'budget_range',
        'due_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_id' => 'integer',
            'article_id' => 'integer',
            'task_id' => 'integer',
            'due_date' => 'date',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(AiMonitoringCheck::class, 'check_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }
}

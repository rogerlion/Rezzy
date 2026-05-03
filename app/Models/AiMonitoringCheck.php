<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMonitoringCheck extends Model
{
    protected $table = 'ai_monitoring_checks';

    protected $fillable = [
        'platform',
        'query_text',
        'target_brand',
        'brand_mentioned',
        'mention_rank',
        'visibility_score',
        'sentiment',
        'answer_summary',
        'mention_context',
        'source_urls',
        'competitors',
        'gap_keywords',
        'status',
        'created_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'brand_mentioned' => 'boolean',
            'mention_rank' => 'integer',
            'visibility_score' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(AiMonitoringPlan::class, 'check_id');
    }
}

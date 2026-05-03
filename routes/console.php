<?php

/**
 * Artisan 自定义命令注册（闭包命令或后续类命令）。
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Horizon 监控快照：用于沉淀队列吞吐、等待等时序指标。
 */
Schedule::command('horizon:snapshot')->everyFiveMinutes();

/**
 * GeoFlow 任务调度：每分钟扫描一次可执行任务并入队（对齐 bak cron 逻辑）。
 */
Schedule::command('geoflow:schedule-tasks')->everyMinute();

/**
 * llms.txt / llms-full.txt 生成器：每小时重写，便于 AI 抓取最新内容。
 */
Schedule::command('geoflow:generate-llms')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();

/**
 * 搜索引擎 SEO 主动推送：每天 03:00 把过去 24h 新发布或更新的 URL
 * 推送给百度搜索资源平台 + IndexNow（Bing/Yandex/Naver）。
 *
 * 取代了之前文章发布瞬间的 instant push（已在 AppServiceProvider 注释）—
 * 节省 Baidu 普通收录配额（合并多 URL 为单次 API 调用）。
 */
Schedule::command('geoflow:push-seo-urls --new')
    ->dailyAt('03:00')
    ->withoutOverlapping(10)
    ->onOneServer();

/**
 * AI 监控 plan → title 库自动转换：每天 09:30（错峰 09:00 监控 + 03:00 push）
 * 闭环 v3 §17.2 关键一环：监控发现的内容缺口自动转入待写标题，让 task 消费产出文章。
 */
Schedule::command("geoflow:plans-to-titles --limit=5")
    ->dailyAt("09:30")
    ->withoutOverlapping(10)
    ->onOneServer();

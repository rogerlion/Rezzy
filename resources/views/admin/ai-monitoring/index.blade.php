@extends('admin.layouts.app')

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $checks */
        $statusOptions = __('admin.ai_monitoring.status_options');
        $sentimentOptions = __('admin.ai_monitoring.sentiment_options');
        $priorityOptions = __('admin.ai_monitoring.priority_options');
        $planTypeOptions = __('admin.ai_monitoring.plan_type_options');
        $hasArticleTargets = ! empty($articleCategories) && ! empty($articleAuthors);
    @endphp

    <div class="px-4 sm:px-0">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.ai_monitoring.heading') }}</h1>
            <p class="mt-1 text-sm text-gray-600">{{ __('admin.ai_monitoring.subtitle') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white shadow rounded-lg p-5">
                <div class="flex items-center">
                    <i data-lucide="radar" class="h-6 w-6 text-blue-600"></i>
                    <div class="ml-4">
                        <div class="text-sm text-gray-500">{{ __('admin.ai_monitoring.total_checks') }}</div>
                        <div class="text-2xl font-bold text-gray-900">{{ number_format((int) $stats['total_checks']) }}</div>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow rounded-lg p-5">
                <div class="flex items-center">
                    <i data-lucide="message-circle" class="h-6 w-6 text-green-600"></i>
                    <div class="ml-4">
                        <div class="text-sm text-gray-500">{{ __('admin.ai_monitoring.mention_rate') }}</div>
                        <div class="text-2xl font-bold text-gray-900">{{ number_format((float) $stats['mention_rate'], 1) }}%</div>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow rounded-lg p-5">
                <div class="flex items-center">
                    <i data-lucide="gauge" class="h-6 w-6 text-purple-600"></i>
                    <div class="ml-4">
                        <div class="text-sm text-gray-500">{{ __('admin.ai_monitoring.avg_visibility') }}</div>
                        <div class="text-2xl font-bold text-gray-900">{{ number_format((float) $stats['avg_visibility'], 1) }}</div>
                    </div>
                </div>
            </div>
            <div class="bg-white shadow rounded-lg p-5">
                <div class="flex items-center">
                    <i data-lucide="list-checks" class="h-6 w-6 text-orange-600"></i>
                    <div class="ml-4">
                        <div class="text-sm text-gray-500">{{ __('admin.ai_monitoring.open_plans') }}</div>
                        <div class="text-2xl font-bold text-gray-900">{{ number_format((int) $stats['open_plans']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <section class="xl:col-span-2 space-y-6">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('admin.ai_monitoring.filter_title') }}</h2>
                    </div>
                    <form method="GET" action="{{ route('admin.ai-monitoring.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 p-6">
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.search') }}</label>
                            <input id="search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="{{ __('admin.ai_monitoring.search_placeholder') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="platform" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.platform') }}</label>
                            <select id="platform" name="platform" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">{{ __('admin.ai_monitoring.all_platforms') }}</option>
                                @foreach ($platforms as $platform)
                                    <option value="{{ $platform }}" @selected($filters['platform'] === $platform)>{{ $platform }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.status') }}</label>
                            <select id="status" name="status" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">{{ __('admin.ai_monitoring.all_statuses') }}</option>
                                @foreach (['new', 'reviewed', 'planned', 'done'] as $status)
                                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $statusOptions[$status] ?? $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-3">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                {{ __('admin.ai_monitoring.filter') }}
                            </button>
                            <a href="{{ route('admin.ai-monitoring.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.ai_monitoring.reset') }}</a>
                        </div>
                    </form>
                </div>

                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('admin.ai_monitoring.recent_checks') }}</h2>
                    </div>

                    <div class="divide-y divide-gray-200">
                        @forelse ($checks as $check)
                            <article class="p-6">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">{{ $check->platform }}</span>
                                            <span class="inline-flex items-center rounded-full @if($check->brand_mentioned) bg-green-50 text-green-700 @else bg-gray-100 text-gray-600 @endif px-2.5 py-1 text-xs font-medium">
                                                {{ $check->brand_mentioned ? __('admin.ai_monitoring.brand_mentioned') : __('admin.ai_monitoring.not_mentioned') }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ optional($check->checked_at)->format('Y-m-d H:i') }}</span>
                                        </div>
                                        <h3 class="mt-3 text-base font-semibold leading-6 text-gray-900">{{ $check->query_text }}</h3>
                                        @if (! empty($check->answer_summary))
                                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $check->answer_summary }}</p>
                                        @endif
                                        @if (! empty($check->mention_context))
                                            <div class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm leading-6 text-blue-900">
                                                <div class="font-medium">{{ __('admin.ai_monitoring.mention_context') }}</div>
                                                <p class="mt-1 whitespace-pre-wrap">{{ $check->mention_context }}</p>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-3 lg:w-72">
                                        <div class="rounded-lg border border-gray-200 px-3 py-2">
                                            <div class="text-xs text-gray-500">{{ __('admin.ai_monitoring.visibility_score') }}</div>
                                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $check->visibility_score === null ? '-' : (int) $check->visibility_score }}</div>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 px-3 py-2">
                                            <div class="text-xs text-gray-500">{{ __('admin.ai_monitoring.mention_rank') }}</div>
                                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $check->mention_rank === null || (int) $check->mention_rank === 0 ? '-' : (int) $check->mention_rank }}</div>
                                        </div>
                                        <div class="rounded-lg border border-gray-200 px-3 py-2">
                                            <div class="text-xs text-gray-500">{{ __('admin.ai_monitoring.sentiment') }}</div>
                                            <div class="mt-1 text-sm font-semibold text-gray-900">{{ $sentimentOptions[$check->sentiment] ?? $check->sentiment }}</div>
                                        </div>
                                        <form method="POST" action="{{ route('admin.ai-monitoring.checks.status', ['checkId' => (int) $check->id]) }}" class="col-span-2 sm:col-span-3 flex items-center gap-2">
                                            @csrf
                                            <select name="status" class="min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                                @foreach (['new', 'reviewed', 'planned', 'done'] as $status)
                                                    <option value="{{ $status }}" @selected($check->status === $status)>{{ $statusOptions[$status] ?? $status }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                {{ __('admin.ai_monitoring.update_status') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <div class="font-medium text-gray-500">{{ __('admin.ai_monitoring.competitors') }}</div>
                                        <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ $check->competitors ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-500">{{ __('admin.ai_monitoring.gap_keywords') }}</div>
                                        <p class="mt-1 whitespace-pre-wrap text-gray-700">{{ $check->gap_keywords ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-500">{{ __('admin.ai_monitoring.source_urls') }}</div>
                                        <p class="mt-1 whitespace-pre-wrap break-words text-gray-700">{{ $check->source_urls ?: '-' }}</p>
                                    </div>
                                </div>

                                @if ($check->plans->isNotEmpty())
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($check->plans as $plan)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                {{ $planTypeOptions[$plan->plan_type] ?? $plan->plan_type }} · {{ $priorityOptions[$plan->priority] ?? $plan->priority }} · {{ $statusOptions[$plan->status] ?? $plan->status }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="p-10 text-center text-sm text-gray-500">{{ __('admin.ai_monitoring.empty_checks') }}</div>
                        @endforelse
                    </div>
                </div>

                @if ($checks->hasPages())
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-500">
                            {{ __('admin.ai_monitoring.page_summary', ['total' => $checks->total(), 'page' => $checks->currentPage(), 'total_pages' => $checks->lastPage()]) }}
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($checks->onFirstPage())
                                <span class="px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-300 bg-white">{{ __('admin.button.previous') }}</span>
                            @else
                                <a href="{{ $checks->previousPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.previous') }}</a>
                            @endif
                            @if ($checks->hasMorePages())
                                <a href="{{ $checks->nextPageUrl() }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white hover:bg-gray-50">{{ __('admin.button.next') }}</a>
                            @else
                                <span class="px-4 py-2 border border-gray-200 rounded-md text-sm text-gray-300 bg-white">{{ __('admin.button.next') }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </section>

            <section class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('admin.ai_monitoring.new_check') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('admin.ai_monitoring.new_check_desc') }}</p>
                </div>

                <form method="POST" action="{{ route('admin.ai-monitoring.checks.store') }}" class="p-6 space-y-5">
                    @csrf
                    <div>
                        <label for="new_platform" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.platform') }}</label>
                        <select id="new_platform" name="platform" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" required>
                            @foreach ($platforms as $platform)
                                <option value="{{ $platform }}" @selected(old('platform', 'ChatGPT') === $platform)>{{ $platform }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="target_brand" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.target_brand') }}</label>
                        <input id="target_brand" name="target_brand" type="text" value="{{ old('target_brand', $adminSiteName) }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="query_text" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.query') }}</label>
                        <textarea id="query_text" name="query_text" rows="3" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('query_text') }}</textarea>
                        @error('query_text')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" name="brand_mentioned" value="1" @checked(old('brand_mentioned')) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            {{ __('admin.ai_monitoring.brand_mentioned') }}
                        </label>
                        <div>
                            <label for="checked_at" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.checked_at') }}</label>
                            <input id="checked_at" name="checked_at" type="datetime-local" value="{{ old('checked_at', now()->format('Y-m-d\TH:i')) }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="mention_rank" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.mention_rank') }}</label>
                            <input id="mention_rank" name="mention_rank" type="number" min="0" max="999" value="{{ old('mention_rank') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="visibility_score" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.visibility_score') }}</label>
                            <input id="visibility_score" name="visibility_score" type="number" min="0" max="100" value="{{ old('visibility_score') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="sentiment" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.sentiment') }}</label>
                            <select id="sentiment" name="sentiment" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                @foreach ($sentimentOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('sentiment', 'neutral') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="answer_summary" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.answer_summary') }}</label>
                        <textarea id="answer_summary" name="answer_summary" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('answer_summary') }}</textarea>
                    </div>

                    <div>
                        <label for="mention_context" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.mention_context') }}</label>
                        <textarea id="mention_context" name="mention_context" rows="3" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('mention_context') }}</textarea>
                    </div>

                    <div>
                        <label for="gap_keywords" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.gap_keywords') }}</label>
                        <textarea id="gap_keywords" name="gap_keywords" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('gap_keywords') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="competitors" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.competitors') }}</label>
                            <textarea id="competitors" name="competitors" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('competitors') }}</textarea>
                        </div>
                        <div>
                            <label for="source_urls" class="block text-sm font-medium text-gray-700 mb-1">{{ __('admin.ai_monitoring.source_urls') }}</label>
                            <textarea id="source_urls" name="source_urls" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('source_urls') }}</textarea>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.ai_monitoring.article_plan') }}</h3>
                        <div class="mt-3 space-y-3">
                            <input name="article_plan_title" type="text" value="{{ old('article_plan_title') }}" placeholder="{{ __('admin.ai_monitoring.article_plan_title') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <input name="article_target_keyword" type="text" value="{{ old('article_target_keyword') }}" placeholder="{{ __('admin.ai_monitoring.article_target_keyword') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <textarea name="article_plan" rows="3" placeholder="{{ __('admin.ai_monitoring.article_plan_body') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('article_plan') }}</textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <select name="article_priority" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @foreach ($priorityOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('article_priority', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="article_due_date" type="date" value="{{ old('article_due_date') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-5">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.ai_monitoring.media_plan') }}</h3>
                        <div class="mt-3 space-y-3">
                            <input name="media_plan_title" type="text" value="{{ old('media_plan_title') }}" placeholder="{{ __('admin.ai_monitoring.media_plan_title') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input name="media_channel" type="text" value="{{ old('media_channel') }}" placeholder="{{ __('admin.ai_monitoring.media_channel') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <input name="media_budget_range" type="text" value="{{ old('media_budget_range') }}" placeholder="{{ __('admin.ai_monitoring.media_budget_range') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                            <textarea name="media_plan" rows="3" placeholder="{{ __('admin.ai_monitoring.media_plan_body') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ old('media_plan') }}</textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <select name="media_priority" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    @foreach ($priorityOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('media_priority', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="media_due_date" type="date" value="{{ old('media_due_date') }}" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        {{ __('admin.ai_monitoring.save_check') }}
                    </button>
                </form>
            </section>
        </div>

        <section class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('admin.ai_monitoring.plans_title') }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.plan_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.plan_action') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.linked_check') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.priority') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.execution_link') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.ai_monitoring.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($plans as $plan)
                            <tr class="align-top">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $planTypeOptions[$plan->plan_type] ?? $plan->plan_type }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="font-medium text-gray-900">{{ $plan->title }}</div>
                                    @if ($plan->target_keyword || $plan->target_channel || $plan->budget_range)
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $plan->target_keyword ?: $plan->target_channel }}@if($plan->budget_range) · {{ $plan->budget_range }} @endif
                                        </div>
                                    @endif
                                    @if ($plan->action_plan)
                                        <p class="mt-2 max-w-xl whitespace-pre-wrap text-gray-600">{{ $plan->action_plan }}</p>
                                    @endif
                                    @if ($plan->due_date)
                                        <div class="mt-2 text-xs text-gray-500">{{ __('admin.ai_monitoring.due_date') }}：{{ optional($plan->due_date)->format('Y-m-d') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if ($plan->check)
                                        <div class="font-medium text-gray-700">{{ $plan->check->platform }}</div>
                                        <div class="mt-1 max-w-sm truncate">{{ $plan->check->query_text }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $priorityOptions[$plan->priority] ?? $plan->priority }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div class="space-y-3 min-w-72">
                                        @if ($plan->article)
                                            <a href="{{ route('admin.articles.edit', ['articleId' => (int) $plan->article->id]) }}" class="inline-flex items-center text-blue-700 hover:text-blue-900">
                                                <i data-lucide="file-text" class="mr-1 h-4 w-4"></i>
                                                {{ __('admin.ai_monitoring.linked_article') }}：{{ $plan->article->title }}
                                            </a>
                                        @elseif ($plan->plan_type === 'article')
                                            @if ($hasArticleTargets)
                                                <form method="POST" action="{{ route('admin.ai-monitoring.plans.article', ['planId' => (int) $plan->id]) }}" class="space-y-2">
                                                    @csrf
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        <select name="category_id" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                                            @foreach ($articleCategories as $category)
                                                                <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select name="author_id" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                                            @foreach ($articleAuthors as $author)
                                                                <option value="{{ $author['id'] }}">{{ $author['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                                                        <i data-lucide="file-plus-2" class="mr-1 h-4 w-4"></i>
                                                        {{ __('admin.ai_monitoring.create_article_draft') }}
                                                    </button>
                                                </form>
                                            @else
                                                <div class="text-xs text-gray-500">{{ __('admin.ai_monitoring.article_target_missing') }}</div>
                                            @endif
                                        @endif

                                        @if ($plan->task)
                                            <a href="{{ route('admin.tasks.edit', ['taskId' => (int) $plan->task->id]) }}" class="inline-flex items-center text-blue-700 hover:text-blue-900">
                                                <i data-lucide="workflow" class="mr-1 h-4 w-4"></i>
                                                {{ __('admin.ai_monitoring.linked_task') }}：{{ $plan->task->name }}
                                            </a>
                                        @elseif ($plan->plan_type === 'article' && ! empty($contentTasks))
                                            <form method="POST" action="{{ route('admin.ai-monitoring.plans.task', ['planId' => (int) $plan->id]) }}" class="flex items-center gap-2">
                                                @csrf
                                                <select name="task_id" class="min-w-0 flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                                    @foreach ($contentTasks as $task)
                                                        <option value="{{ $task['id'] }}">{{ $task['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    {{ __('admin.ai_monitoring.attach_task') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.ai-monitoring.plans.status', ['planId' => (int) $plan->id]) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="status" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                            @foreach (['todo', 'in_progress', 'done', 'archived'] as $status)
                                                <option value="{{ $status }}" @selected($plan->status === $status)>{{ $statusOptions[$status] ?? $status }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            {{ __('admin.ai_monitoring.update_status') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">{{ __('admin.ai_monitoring.empty_plans') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

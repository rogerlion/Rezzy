<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use XMLWriter;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];
        $latestArticleTime = $this->latestArticleTime();

        $urls[] = $this->url(route('site.home'), $latestArticleTime, 'daily', '1.0');
        $urls[] = $this->url(route('site.archive'), $latestArticleTime, 'daily', '0.7');

        foreach ($this->archiveMonths() as $archiveMonth) {
            $urls[] = $this->url(
                route('site.archive.month', ['year' => $archiveMonth['year'], 'month' => $archiveMonth['month']]),
                $archiveMonth['lastmod'],
                'weekly',
                '0.6'
            );
        }

        $categories = Category::query()
            ->whereHas('articles', fn ($query) => $query->published())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug']);

        foreach ($categories as $category) {
            $categoryLastmod = Article::query()
                ->published()
                ->where('category_id', (int) $category->id)
                ->selectRaw('COALESCE(updated_at, published_at, created_at) AS lastmod')
                ->orderByDesc('lastmod')
                ->value('lastmod');

            $urls[] = $this->url(route('site.category', $category->slug), $categoryLastmod, 'weekly', '0.7');
        }

        Article::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->chunk(500, function ($articles) use (&$urls): void {
                foreach ($articles as $article) {
                    $lastmod = $article->updated_at ?? $article->published_at ?? $article->created_at;
                    $urls[] = $this->url(route('site.article', $article->slug), $lastmod, 'monthly', '0.8');
                }
            });

        return response($this->render($urls), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @return array{loc:string,lastmod:string,changefreq:string,priority:string}
     */
    private function url(string $loc, mixed $lastmod, string $changefreq, string $priority): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $this->formatLastmod($lastmod),
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function latestArticleTime(): Carbon
    {
        $latest = Article::query()
            ->published()
            ->selectRaw('COALESCE(updated_at, published_at, created_at) AS lastmod')
            ->orderByDesc('lastmod')
            ->value('lastmod');

        return $latest !== null ? Carbon::parse($latest) : now();
    }

    /**
     * @return list<array{year:string,month:string,lastmod:mixed}>
     */
    private function archiveMonths(): array
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            $rows = DB::select("
                SELECT
                    EXTRACT(YEAR FROM COALESCE(published_at, created_at))::int AS y,
                    LPAD(EXTRACT(MONTH FROM COALESCE(published_at, created_at))::text, 2, '0') AS m,
                    MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
                FROM articles
                WHERE status = 'published' AND deleted_at IS NULL
                GROUP BY y, m
                ORDER BY y DESC, m DESC
            ");

            return array_map(static fn (object $row): array => [
                'year' => (string) $row->y,
                'month' => (string) $row->m,
                'lastmod' => $row->lastmod,
            ], $rows);
        }

        return Article::query()
            ->published()
            ->get(['published_at', 'updated_at', 'created_at'])
            ->groupBy(fn (Article $article): string => ($article->published_at ?? $article->created_at)->format('Y-m'))
            ->map(function ($articles, string $key): array {
                [$year, $month] = explode('-', $key, 2);

                return [
                    'year' => $year,
                    'month' => $month,
                    'lastmod' => $articles
                        ->map(fn (Article $article) => $article->updated_at ?? $article->published_at ?? $article->created_at)
                        ->sortDesc()
                        ->first(),
                ];
            })
            ->values()
            ->all();
    }

    private function formatLastmod(mixed $value): string
    {
        return Carbon::parse($value ?: now())->toAtomString();
    }

    /**
     * @param  list<array{loc:string,lastmod:string,changefreq:string,priority:string}>  $urls
     */
    private function render(array $urls): string
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', $url['loc']);
            $writer->writeElement('lastmod', $url['lastmod']);
            $writer->writeElement('changefreq', $url['changefreq']);
            $writer->writeElement('priority', $url['priority']);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }
}

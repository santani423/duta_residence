<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Models\HeroSlide;
use App\Models\LandingAboutSetting;
use App\Models\LandingArticle;
use App\Models\LandingContactSetting;
use App\Models\LandingEvent;
use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\LandingFooterSetting;
use App\Models\LandingGalleryAlbum;
use App\Models\LandingHeaderSetting;
use App\Models\LandingPartner;
use App\Models\LandingSeoSetting;
use App\Models\LandingService;
use App\Models\LandingSocialLink;
use App\Models\LandingStatistic;
use App\Models\LandingTestimonial;
use App\Models\NavMenuItem;
use App\Models\Resident;
use App\Models\SiteSetting;
use App\Models\Unit;
use App\Support\LandingContentCache;

class LandingController extends Controller
{
    use ApiResponse;

    /**
     * Public, unauthenticated aggregate of every active/published landing-page
     * section in one cached payload. Live-counted statistics are resolved
     * outside the cache (see below) so "dynamic counters" stay genuinely live
     * without needing a cache-bust hook on every Resident/Unit/Cluster write.
     */
    public function content()
    {
        $payload = LandingContentCache::remember(function () {
            $header = LandingHeaderSetting::current()->load('logo');
            $about = LandingAboutSetting::current()->load('image');
            $footer = LandingFooterSetting::current()->load('logo');
            $navItems = NavMenuItem::query()->active()->ordered()->get();

            return [
                'site' => SiteSetting::current()->load('logo'),
                'header' => [...$header->toArray(), 'nav_items' => $navItems],
                'footer' => [
                    ...$footer->toArray(),
                    'quick_links' => $navItems->where('show_in_footer', true)->values(),
                ],
                'about' => $about,
                'contact' => LandingContactSetting::current(),
                'seo' => LandingSeoSetting::current()->load(['ogImage', 'favicon']),
                'hero_slides' => HeroSlide::query()->active()->ordered()->with('backgroundMedia')->get(),
                'services' => LandingService::query()->active()->ordered()->with(['image', 'category'])->get(),
                'features' => LandingFeature::query()->active()->ordered()->with('image')->get(),
                'statistics' => LandingStatistic::query()->active()->ordered()->get(),
                'testimonials' => LandingTestimonial::query()->active()->ordered()->with('photo')->get(),
                'partners' => LandingPartner::query()->active()->ordered()->with('logo')->get(),
                'faqs' => LandingFaq::query()->active()->ordered()->with('category')->get(),
                'events' => LandingEvent::query()->active()->published()->orderBy('starts_at')->with('banner')->get(),
                'articles' => LandingArticle::query()->active()->published()
                    ->orderByDesc('published_at')
                    ->with(['category', 'featuredImage', 'thumbnail', 'author'])
                    ->limit(24)
                    ->get(),
                'gallery_albums' => LandingGalleryAlbum::query()->active()->ordered()->withCount('items')->with('cover')->get(),
                'social_links' => LandingSocialLink::query()->active()->ordered()->get(),
            ];
        });

        $payload['statistics'] = collect($payload['statistics'])->map(function ($statistic) {
            $statistic = $statistic->toArray();
            $statistic['resolved_value'] = $this->resolveStatisticValue($statistic);

            return $statistic;
        })->values();

        return $this->success($payload);
    }

    public function article(string $slug)
    {
        $article = LandingArticle::query()
            ->active()->published()
            ->where('slug', $slug)
            ->with(['category', 'featuredImage', 'thumbnail', 'author'])
            ->firstOrFail();

        return $this->success($article);
    }

    public function event(string $slug)
    {
        $event = LandingEvent::query()->active()->where('slug', $slug)->with('banner')->firstOrFail();

        return $this->success($event);
    }

    public function galleryAlbum(string $slug)
    {
        $album = LandingGalleryAlbum::query()
            ->active()
            ->where('slug', $slug)
            ->with(['cover', 'category', 'items' => fn ($query) => $query->active()->ordered()->with('media')])
            ->firstOrFail();

        return $this->success($album);
    }

    /**
     * Lightweight public identity lookup (name only) for surfaces that don't
     * need the full landing aggregate above - the authenticated app shell,
     * the login screen, and the mobile app.
     */
    public function identity()
    {
        return $this->success(['site_name' => SiteSetting::current()->site_name]);
    }

    private function resolveStatisticValue(array $statistic): float
    {
        return match ($statistic['source'] ?? 'manual') {
            'residents_count' => (float) Resident::query()->count(),
            'units_count' => (float) Unit::query()->count(),
            'clusters_count' => (float) Cluster::query()->count(),
            default => (float) $statistic['value'],
        };
    }
}

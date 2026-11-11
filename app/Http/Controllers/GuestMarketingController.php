<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\AdminBlogs\Models\Blog;
use Modules\AdminFaqs\Models\Faq;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSettings\Support\OptionStore;

class GuestMarketingController extends Controller
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function home(): View
    {
        $homePricing = \Pricing::plansWithFeatures();
        $homePlanTypes = collect(\Plan::getTypes())
            ->filter(fn ($label, $key) => ! empty($homePricing[$key] ?? []));

        return view(theme_view('pages.home', 'guest'), [
            'pageTitle' => null,
            'latestBlogs' => $this->publishedBlogs(3),
            'featuredPlans' => $this->publicPlans()->take(3),
            'featuredFaqs' => $this->publicFaqs(6),
            'homePricing' => $homePricing,
            'homePlanTypes' => $homePlanTypes,
            'homeDefaultType' => (int) ($homePlanTypes->keys()->first() ?? 1),
            'marketingStats' => [
                'plans' => $this->publicPlans()->count(),
                'blogs' => Blog::query()->where('status', true)->count(),
                'faqs' => Faq::query()->where('status', true)->count(),
            ],
        ]);
    }

    public function pricing(): View
    {
        $plans = $this->publicPlans();

        return view(theme_view('pages.pricing', 'guest'), [
            'pageTitle' => __('Pricing'),
            'plans' => $plans,
            'featuredFaqs' => $this->publicFaqs(6),
        ]);
    }

    public function faqs(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $faqs = Faq::query()
            ->where('status', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CASE WHEN slug LIKE ? THEN 1 ELSE 0 END', ['demo-preview-%'])
            ->orderByDesc('created')
            ->paginate(12)
            ->withQueryString();

        return view(theme_view('pages.faqs', 'guest'), [
            'pageTitle' => __('FAQs'),
            'faqs' => $faqs,
            'filters' => ['q' => $search],
        ]);
    }

    public function blogs(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $blogs = Blog::query()
            ->with(['category:id,name,name_translations', 'tags:id,name,name_translations'])
            ->where('status', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('created')
            ->paginate(9)
            ->withQueryString();

        return view(theme_view('pages.blogs', 'guest'), [
            'pageTitle' => __('Blog'),
            'blogs' => $blogs,
            'filters' => ['q' => $search],
            'featuredPost' => $blogs->getCollection()->first(),
        ]);
    }

    public function contact(): View
    {
        return view(theme_view('pages.contact', 'guest'), [
            'pageTitle' => __('Contact'),
        ]);
    }

    public function blogShow(string $slug): View
    {
        $blog = Blog::query()
            ->with(['category:id,name,name_translations', 'tags:id,name,name_translations'])
            ->where('status', true)
            ->where('slug', $slug)
            ->firstOrFail();

        return view(theme_view('pages.blog-show', 'guest'), [
            'pageTitle' => $blog->seoTitle(),
            'pageDescription' => $blog->seoDescription(),
            'pageImage' => $blog->thumbnailUrl(),
            'blog' => $blog,
            'relatedBlogs' => Blog::query()
                ->with(['category:id,name,name_translations'])
                ->where('status', true)
                ->whereKeyNot($blog->id)
                ->orderByDesc('published_at')
                ->orderByDesc('created')
                ->limit(3)
                ->get(),
        ]);
    }

    protected function publicPlans()
    {
        return AdminPlan::query()
            ->where('status', true)
            ->orderByDesc('featured')
            ->orderBy('position')
            ->orderBy('price')
            ->get();
    }

    protected function publicFaqs(int $limit)
    {
        return Faq::query()
            ->where('status', true)
            ->where('slug', 'not like', 'demo-preview-%')
            ->orderByDesc('created')
            ->limit($limit)
            ->get();
    }

    protected function publishedBlogs(int $limit)
    {
        return Blog::query()
            ->with(['category:id,name,name_translations'])
            ->where('status', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created')
            ->limit($limit)
            ->get();
    }
}

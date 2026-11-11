<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectDemoThemeSwitcher
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('app.demo_mode') || ! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || stripos($content, '</body>') === false || str_contains($content, 'data-demo-theme-switcher-script')) {
            return $response;
        }

        $theme = $request->attributes->get('currentTheme');
        $isGuestTheme = ($theme->area ?? null) === 'guest';
        $currentTheme = $isGuestTheme
            ? (string) ($theme->name ?? 'default')
            : (string) ($request->query('demo_theme') ?: $request->cookie('demo_frontend_theme', 'default'));

        $response->setContent(str_ireplace('</body>', $this->renderSwitcher($currentTheme, $isGuestTheme).'</body>', $content));

        return $response;
    }

    protected function shouldInject(Request $request, Response $response): bool
    {
        if ($request->expectsJson() || $request->is('livewire/*') || ! method_exists($response, 'getContent')) {
            return false;
        }

        $theme = $request->attributes->get('currentTheme');

        if (! $theme) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
    }

    protected function renderSwitcher(string $currentTheme, bool $isGuestTheme): string
    {
        $currentTheme = in_array($currentTheme, ['default', 'luma'], true) ? $currentTheme : 'default';
        $themes = json_encode([
            [
                'key' => 'default',
                'label' => 'Default',
                'description' => 'Dark SaaS landing theme with compact navigation, pricing, blog, FAQ, auth screens, and marketing sections.',
                'preview' => asset('resources/themes/guest/default/preview.png'),
            ],
            [
                'key' => 'luma',
                'label' => 'Luma',
                'description' => 'Light-first premium theme for social scheduling, content planning, analytics, pricing, auth, and buyer-focused pages.',
                'preview' => asset('resources/themes/guest/luma/preview.png'),
            ],
        ], JSON_THROW_ON_ERROR);

        return view('demo.theme-switcher', [
            'currentTheme' => $currentTheme,
            'isGuestTheme' => $isGuestTheme,
            'themes' => json_decode($themes, true, 512, JSON_THROW_ON_ERROR),
        ])->render();
    }
}

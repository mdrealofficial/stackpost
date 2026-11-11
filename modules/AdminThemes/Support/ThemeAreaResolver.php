<?php

namespace Modules\AdminThemes\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThemeAreaResolver
{
    public function resolve(Request $request): string
    {
        foreach ((array) config('themes.areas', []) as $area => $definition) {
            if ($this->matchesRouteName($request, (array) ($definition['route_names'] ?? []))) {
                return $area;
            }

            if ($this->matchesPrefix($request, (array) ($definition['route_prefixes'] ?? []))) {
                return $area;
            }
        }

        return config('themes.default_area', 'guest');
    }

    protected function matchesRouteName(Request $request, array $patterns): bool
    {
        $route = $request->route();
        $name = $route?->getName();

        if (! $name) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesPrefix(Request $request, array $prefixes): bool
    {
        $segment = (string) $request->segment(1);

        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && $segment === $prefix) {
                return true;
            }
        }

        return false;
    }
}

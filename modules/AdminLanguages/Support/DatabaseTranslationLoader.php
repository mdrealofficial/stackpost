<?php

namespace Modules\AdminLanguages\Support;

use Illuminate\Contracts\Translation\Loader;

class DatabaseTranslationLoader implements Loader
{
    public function __construct(
        protected Loader $inner,
        protected TranslationCatalog $catalog,
    ) {}

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->inner->load($locale, $group, $namespace);

        if ($group === '*' && $namespace === '*') {
            return array_replace($lines, $this->catalog->overridesForLocale((string) $locale));
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return $this->inner->namespaces();
    }
}

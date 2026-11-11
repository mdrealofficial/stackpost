<?php

namespace Modules\AppChannels\Support;

class IntegrationCatalog extends ChannelCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        return $this->capabilities();
    }
}

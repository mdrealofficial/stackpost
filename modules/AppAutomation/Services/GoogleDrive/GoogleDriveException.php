<?php

namespace Modules\AppAutomation\Services\GoogleDrive;

use RuntimeException;

class GoogleDriveException extends RuntimeException
{
    public static function configurationMissing(array $missing = []): self
    {
        $detail = $missing === [] ? '' : ' '.__('Missing: :fields.', ['fields' => implode(', ', $missing)]);

        return new self(__('Google Drive automation OAuth is not configured.').$detail);
    }

    public static function requestFailed(string $operation, string $detail): self
    {
        return new self(__(':operation failed: :detail', [
            'operation' => $operation,
            'detail' => $detail !== '' ? $detail : __('Unknown Google API error'),
        ]));
    }
}

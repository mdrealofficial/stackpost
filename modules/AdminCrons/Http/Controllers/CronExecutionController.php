<?php

namespace Modules\AdminCrons\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AdminSettings\Support\OptionStore;
use Symfony\Component\HttpFoundation\Response;

class CronExecutionController extends Controller
{
    public function __invoke(string $task, Request $request, OptionStore $options, SystemCronRegistry $registry): Response
    {
        $providedKey = trim((string) $request->query('key', ''));
        $expectedKey = trim((string) $options->get('system_cron_secure_key', ''));

        abort_if($expectedKey === '' || $providedKey === '' || ! hash_equals($expectedKey, $providedKey), 403);

        $result = $registry->execute($task);
        $body = collect([
            '['.now()->toDateTimeString().'] '.$result['task']['name'],
            'Exit code: '.$result['exit_code'],
            $result['output'] !== '' ? $result['output'] : 'Completed without console output.',
        ])->implode(PHP_EOL);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

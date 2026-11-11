<?php

namespace App\Installer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Installer\Support\InstallerService;
use App\Installer\Support\InstallerState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class InstallerController extends Controller
{
    public function __construct(
        protected InstallerState $state,
        protected InstallerService $installer,
    ) {}

    public function index(Request $request)
    {
        if ($this->state->isInstalled() && (string) $request->query('step') !== 'finish') {
            return redirect()->route('home');
        }

        return view('installer::index', $this->buildViewData(
            step: (string) $request->query('step', 'requirements'),
        ));
    }

    public function store(Request $request)
    {
        if ($this->state->isInstalled()) {
            return redirect()->route('home');
        }

        $validator = Validator::make($request->all(), $this->rules($request));

        if ($validator->fails()) {
            return response()->view('installer::index', $this->buildViewData(
                step: 'setup',
                errors: $validator->errors(),
                formData: $request->all(),
            ), 422);
        }

        try {
            $this->installer->install($validator->validated(), $request);
        } catch (ValidationException $exception) {
            return response()->view('installer::index', $this->buildViewData(
                step: 'setup',
                errors: $exception->validator?->errors() ?? new MessageBag($exception->errors()),
                formData: $request->all(),
            ), 422);
        } catch (Throwable $exception) {
            Log::error('Installer failed unexpectedly.', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return response()->view('installer::index', $this->buildViewData(
                step: 'setup',
                errors: new MessageBag([
                    'installer' => __('Installation failed unexpectedly. :message', ['message' => $exception->getMessage()]),
                ]),
                formData: $request->all(),
            ), 500);
        }

        return redirect()
            ->route('installer.index', ['step' => 'finish']);
    }

    protected function rules(Request $request): array
    {
        $purchaseRequired = (bool) config('installer.purchase_code_required', true);

        return [
            'purchase_code' => [$purchaseRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'website_title' => ['required', 'string', 'max:255'],
            'website_description' => ['nullable', 'string', 'max:500'],
            'website_keywords' => ['nullable', 'string', 'max:500'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'string', 'max:20'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'admin_password' => ['required', 'string', 'confirmed', 'min:8'],
            'admin_timezone' => ['required', 'string', 'timezone:all', Rule::in(timezone_options())],
        ];
    }

    protected function buildViewData(string $step = 'requirements', ?MessageBag $errors = null, array $formData = []): array
    {
        $requirements = $this->state->requirements();
        $canProceed = collect($requirements)->every(fn (array $check) => (bool) ($check['ok'] ?? false));
        $currentStep = match ($step) {
            'finish' => 'finish',
            'setup' => $canProceed ? 'setup' : 'requirements',
            default => 'requirements',
        };

        return [
            'requirements' => $requirements,
            'canProceed' => $canProceed,
            'currentStep' => $currentStep,
            'timezoneOptions' => timezone_select_options(),
            'purchaseRequired' => (bool) config('installer.purchase_code_required', true),
            'validationErrors' => $errors ?? new MessageBag(),
            'formData' => $formData,
        ];
    }
}

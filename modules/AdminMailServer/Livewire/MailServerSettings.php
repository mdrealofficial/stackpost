<?php

namespace Modules\AdminMailServer\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminMailServer\Support\MailServerConfigurator;
use Modules\AdminSettings\Support\OptionStore;
use Throwable;

#[Title('Mail Server')]
class MailServerSettings extends Component
{
    protected OptionStore $options;

    public string $mail_protocol = 'log';

    public string $mail_sender_email = '';

    public string $mail_sender_name = '';

    public string $smtp_server = '';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_port = '587';

    public string $smtp_encryption = 'tls';

    public string $mail_timeout = '30';

    public string $mail_ehlo_domain = '';

    public string $sendmail_path = '/usr/sbin/sendmail -bs -i';

    public string $test_email = '';

    public ?string $testStatus = null;

    public ?string $testMessage = null;

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        foreach (MailServerConfigurator::fromOptions($this->options) as $key => $value) {
            $this->{$key} = (string) $value;
        }

        $this->mail_protocol = MailServerConfigurator::normalizeTransport($this->mail_protocol);
        $this->smtp_encryption = $this->normalizeEncryptionForState($this->smtp_encryption);
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        foreach ($this->persistableKeys() as $key) {
            $this->options->set($key, $validated[$key]);
        }

        MailServerConfigurator::apply($this->currentState());

        $this->testStatus = null;
        $this->testMessage = null;

        $this->dispatch('settings-saved');
    }

    public function sendTestEmail(): void
    {
        $validated = $this->validate(array_merge($this->rules(), [
            'test_email' => ['required', 'email', 'max:255'],
        ]));

        MailServerConfigurator::apply($this->currentState());

        try {
            Mail::raw(
                __('This is a test email sent from the :app mail server configuration on :date.', [
                    'app' => config('app.name', 'Stackposts'),
                    'date' => now()->format('Y-m-d H:i:s'),
                ]),
                function ($message) use ($validated): void {
                    $message
                        ->to($validated['test_email'])
                        ->subject(__('Test email from :app', ['app' => config('app.name', 'Stackposts')]));
                }
            );

            $this->testStatus = 'success';
            $this->testMessage = $this->mail_protocol === 'log'
                ? __('Test message queued into the log mailer. Check your mail log channel for the rendered email.')
                : __('Test email sent using the current configuration.');
        } catch (Throwable $exception) {
            $this->testStatus = 'danger';
            $this->testMessage = str($exception->getMessage())->squish()->limit(280)->toString();
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'mail_protocol' => ['required', 'in:smtp,sendmail,log'],
            'mail_sender_email' => ['required', 'email', 'max:255'],
            'mail_sender_name' => ['required', 'string', 'max:255'],
            'smtp_server' => [Rule::requiredIf($this->mail_protocol === 'smtp'), 'nullable', 'string', 'max:255'],
            'smtp_username' => [Rule::requiredIf($this->mail_protocol === 'smtp'), 'nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_port' => [Rule::requiredIf($this->mail_protocol === 'smtp'), 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', 'in:none,tls,ssl'],
            'mail_timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
            'mail_ehlo_domain' => ['nullable', 'string', 'max:255'],
            'sendmail_path' => [Rule::requiredIf($this->mail_protocol === 'sendmail'), 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function persistableKeys(): array
    {
        return [
            'mail_protocol',
            'mail_sender_email',
            'mail_sender_name',
            'smtp_server',
            'smtp_username',
            'smtp_password',
            'smtp_port',
            'smtp_encryption',
            'mail_timeout',
            'mail_ehlo_domain',
            'sendmail_path',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function currentState(): array
    {
        return [
            'mail_protocol' => $this->mail_protocol,
            'mail_sender_email' => $this->mail_sender_email,
            'mail_sender_name' => $this->mail_sender_name,
            'smtp_server' => $this->smtp_server,
            'smtp_username' => $this->smtp_username,
            'smtp_password' => $this->smtp_password,
            'smtp_port' => $this->smtp_port,
            'smtp_encryption' => $this->smtp_encryption,
            'mail_timeout' => $this->mail_timeout,
            'mail_ehlo_domain' => $this->mail_ehlo_domain,
            'sendmail_path' => $this->sendmail_path,
        ];
    }

    protected function normalizeEncryptionForState(string $scheme): string
    {
        $normalized = MailServerConfigurator::normalizeScheme($scheme);

        return $normalized ?? 'none';
    }

    /**
     * @return array<int, array{value:string,label:string,description:string}>
     */
    protected function transportOptions(): array
    {
        return [
            ['value' => 'smtp', 'label' => 'SMTP', 'description' => 'Best fit for production SaaS delivery through external transactional providers.'],
            ['value' => 'sendmail', 'label' => 'Sendmail', 'description' => 'Use the server sendmail / PHP mail stack when SMTP is not available.'],
            ['value' => 'log', 'label' => 'Log only', 'description' => 'Do not deliver externally. Useful for local development and diagnostics.'],
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function encryptionOptions(): array
    {
        return [
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'tls', 'label' => 'TLS'],
            ['value' => 'ssl', 'label' => 'SSL'],
        ];
    }

    public function render(): View
    {
        return view('adminmailserver::index', [
            'transportOptions' => $this->transportOptions(),
            'encryptionOptions' => $this->encryptionOptions(),
            'effectiveTransport' => strtoupper($this->mail_protocol),
            'effectiveSender' => trim(sprintf('%s <%s>', $this->mail_sender_name, $this->mail_sender_email)),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Mail Server'),
        ]);
    }
}

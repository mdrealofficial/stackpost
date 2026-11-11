<?php

namespace Modules\AdminUser\Models;

use App\Support\Storage\StorageDriverManager;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminPaymentSubscriptions\Models\PaymentSubscription;
use Modules\AppAffiliate\Models\AffiliateCommission;
use Modules\AppAffiliate\Models\AffiliateProfile;
use Modules\AppAffiliate\Models\AffiliateWithdrawal;
use Modules\AppCredits\Support\CreditService;
use Modules\AdminUser\Support\AdminPermissionCatalog;

#[Fillable(['name', 'username', 'email', 'referral_code', 'referred_by_user_id', 'avatar_path', 'avatar_disk', 'locale', 'timezone', 'role_id', 'is_super_admin', 'plan_id', 'next_plan_id', 'plan_started_at', 'plan_expires_at', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'bool',
            'plan_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'next_plan_id' => 'integer',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! filled($this->avatar_path)) {
                return null;
            }

            $path = trim((string) $this->avatar_path);

            if ($path === '') {
                return null;
            }

            if (Str::startsWith($path, ['http://', 'https://', '//'])) {
                return $path;
            }

            if (filled($this->avatar_disk)) {
                $url = app(StorageDriverManager::class)->publicUrl((string) $this->avatar_disk, $path);

                if (! filled($url)) {
                    return null;
                }

                $version = md5('avatar-v2|'.($this->avatar_disk ?? 'public').'|'.$path.'|'.($this->updated_at?->timestamp ?? 0));

                return str_contains($url, '?') ? $url.'&v='.$version : $url.'?v='.$version;
            }

            $path = ltrim($path, '/');
            $path = Str::replaceStart('public/storage/', '', $path);
            $path = Str::replaceStart('storage/', '', $path);
            $path = Str::replaceStart('public/', '', $path);

            $url = app(StorageDriverManager::class)->publicUrl('public', $path);

            if (! filled($url)) {
                return null;
            }

            $version = md5('avatar-v2|public|'.$path.'|'.($this->updated_at?->timestamp ?? 0));

            return str_contains($url, '?') ? $url.'&v='.$version : $url.'?v='.$version;
        });
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'permissions', 'managed_account_ids'])
            ->withTimestamps();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AdminPlan::class, 'plan_id');
    }

    public function nextPlan(): BelongsTo
    {
        return $this->belongsTo(AdminPlan::class, 'next_plan_id');
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_user_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_user_id');
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class, 'uid');
    }

    public function affiliateProfile()
    {
        return $this->hasOne(AffiliateProfile::class, 'user_id');
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'affiliate_user_id');
    }

    public function affiliateWithdrawals(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawal::class, 'affiliate_user_id');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->canAccessAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = collect($this->role?->permissions ?? [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values();

        return $permissions->contains($permission)
            || $permissions->contains('*')
            || $permissions->contains(str($permission)->beforeLast('.')->append('.*')->value());
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin
            || $this->username === 'admin'
            || $this->role?->slug === 'super-admin';
    }

    public function canAccessAdmin(): bool
    {
        return $this->isSuperAdmin() || filled($this->role_id);
    }

    public function canAccessAdminRoute(?string $routeName): bool
    {
        if (! $this->canAccessAdmin()) {
            return false;
        }

        if ($routeName === null || $routeName === '') {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $permission = $this->permissionForRoute($routeName);

        return $permission ? $this->hasPermission($permission) : true;
    }

    public function permissionForRoute(string $routeName): ?string
    {
        return AdminPermissionCatalog::permissionForRoute($routeName);
    }

    public function canImpersonate(): bool
    {
        return $this->hasPermission('admin-users.edit');
    }

    public function canBeImpersonatedBy(?self $actor): bool
    {
        if (! $actor || ! $actor->canImpersonate()) {
            return false;
        }

        return $actor->getKey() !== $this->getKey();
    }

    public function isImpersonating(): bool
    {
        return session()->has('impersonator_id');
    }

    public function hasActivePlan(): bool
    {
        if (! $this->plan || ! $this->plan->status) {
            return false;
        }

        return $this->plan_expires_at === null || $this->plan_expires_at->isFuture();
    }

    public function isInPlanTrial(): bool
    {
        if (! $this->hasActivePlan() || ! $this->plan || $this->plan->free_plan) {
            return false;
        }

        if ((int) ($this->plan->trial_day ?? 0) <= 0) {
            return false;
        }

        if (! $this->plan_started_at || ! $this->plan_expires_at) {
            return false;
        }

        $expectedTrialEnd = Carbon::parse($this->plan_started_at)->addDays((int) $this->plan->trial_day);

        return abs($this->plan_expires_at->diffInSeconds($expectedTrialEnd, false)) <= 60
            && $this->plan_expires_at->isFuture();
    }

    public function trialEndsAt(): ?Carbon
    {
        return $this->isInPlanTrial() ? Carbon::parse($this->plan_expires_at) : null;
    }

    public function hasPlanFeature(string $feature): bool
    {
        $value = $this->plan?->permissions[$feature] ?? false;

        return $value === true || $value === 1 || $value === '1';
    }

    public function canUsePlanFeature(string $feature): bool
    {
        return $this->hasActivePlan() && $this->hasPlanFeature($feature);
    }

    public function canUseAnyPlanFeature(array $features): bool
    {
        if (! $this->hasActivePlan()) {
            return false;
        }

        foreach ($features as $feature) {
            if (is_string($feature) && $feature !== '' && $this->hasPlanFeature($feature)) {
                return true;
            }
        }

        return false;
    }

    public function preferredLocale(): ?string
    {
        $locale = trim((string) ($this->locale ?: ''));

        return $locale !== ''
            ? strtolower($locale)
            : (string) config('app.locale', config('app.fallback_locale', 'en'));
    }

    public function planLimit(string $key, mixed $default = null): mixed
    {
        return $this->plan?->permissions[$key] ?? $default;
    }

    public function creditSummary(): array
    {
        return app(CreditService::class)->summary($this);
    }

    public function hasAvailableCredits(string $actionKey, int $quantity = 1): bool
    {
        return app(CreditService::class)->canConsume($this, $actionKey, $quantity);
    }
}

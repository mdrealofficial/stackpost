<?php

namespace Modules\AdminUser\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Modules\AdminPlans\Support\DefaultSignupPlanResolver;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AdminUser\Concerns\ProfileValidationRules;
use Modules\AdminUser\Models\User;
use Modules\AdminUser\Support\PersonalTeamProvisioner;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        protected AffiliateService $affiliate,
        protected PersonalTeamProvisioner $personalTeamProvisioner,
        protected DefaultSignupPlanResolver $defaultSignupPlanResolver,
    ) {}

    public function create(array $input): User
    {
        $rules = [
            ...$this->profileRules(),
            'timezone' => ['required', 'string', 'timezone:all', Rule::in(timezone_options())],
            'accept_terms' => ['accepted'],
            'password' => $this->passwordRules(),
        ];

        Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($input): User {
            $defaultSignupPlan = $this->defaultSignupPlanResolver->resolve();
            $planStartedAt = $defaultSignupPlan ? now() : null;

            $user = User::create([
                'name' => $input['name'],
                'username' => strtolower($input['username']),
                'email' => $input['email'],
                'timezone' => $input['timezone'],
                'plan_id' => $defaultSignupPlan?->id,
                'plan_started_at' => $planStartedAt,
                'plan_expires_at' => $this->defaultSignupPlanResolver->expiresAt($defaultSignupPlan, $planStartedAt),
                'referral_code' => $this->affiliate->generateReferralCode((string) $input['username']),
                'referred_by_user_id' => $this->affiliate->referredByUserIdFromSession(),
                'password' => $input['password'],
            ]);

            $this->affiliate->ensureProfile($user);

            $team = $this->personalTeamProvisioner->ensureForUser($user);

            log_activity('auth.register', 'Registered a new account and default team.', [
                'causer_user_id' => $user->id,
                'subject' => $user,
                'area' => 'user',
                'metadata' => [
                    'username' => $user->username,
                    'team' => $team->name,
                    'plan' => $defaultSignupPlan?->name,
                ],
            ]);

            return $user;
        });
    }
}

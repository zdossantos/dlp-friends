<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $email
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $birth_date
 * @property UserStatus $status
 * @property-read int|null $age
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Profile|null $profile
 * @property-read ProductOnboarding|null $productOnboarding
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Swipe> $sentSwipes
 * @property-read Collection<int, Swipe> $receivedSwipes
 * @property-read Collection<int, MemberMatch> $lowMatches
 * @property-read Collection<int, MemberMatch> $highMatches
 * @property-read Collection<int, Block> $blocksCreated
 * @property-read Collection<int, Block> $blocksReceived
 * @property-read Collection<int, Message> $authoredMessages
 * @property-read Collection<int, SocialAccount> $socialAccounts
 * @property-read Collection<int, TermsAcceptance> $termsAcceptances
 */
#[Fillable(['email', 'locale', 'birth_date', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** @return HasOne<ProductOnboarding, $this> */
    public function productOnboarding(): HasOne
    {
        return $this->hasOne(ProductOnboarding::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /** @return HasMany<Swipe, $this> */
    public function sentSwipes(): HasMany
    {
        return $this->hasMany(Swipe::class, 'actor_user_id');
    }

    /** @return HasMany<Swipe, $this> */
    public function receivedSwipes(): HasMany
    {
        return $this->hasMany(Swipe::class, 'target_user_id');
    }

    /** @return HasMany<MemberMatch, $this> */
    public function lowMatches(): HasMany
    {
        return $this->hasMany(MemberMatch::class, 'user_low_id');
    }

    /** @return HasMany<MemberMatch, $this> */
    public function highMatches(): HasMany
    {
        return $this->hasMany(MemberMatch::class, 'user_high_id');
    }

    /** @return HasMany<Block, $this> */
    public function blocksCreated(): HasMany
    {
        return $this->hasMany(Block::class, 'blocker_user_id');
    }

    /** @return HasMany<Block, $this> */
    public function blocksReceived(): HasMany
    {
        return $this->hasMany(Block::class, 'blocked_user_id');
    }

    /** @return HasMany<Message, $this> */
    public function authoredMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'author_user_id');
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<TermsAcceptance, $this> */
    public function termsAcceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    public function hasBlockedRelationshipWith(User $other): bool
    {
        return Block::query()
            ->where(function (Builder $query) use ($other): void {
                $query->where('blocker_user_id', $this->id)
                    ->where('blocked_user_id', $other->id);
            })
            ->orWhere(function (Builder $query) use ($other): void {
                $query->where('blocker_user_id', $other->id)
                    ->where('blocked_user_id', $this->id);
            })
            ->exists();
    }

    public function hasRole(string|RoleName $role): bool
    {
        $value = $role instanceof RoleName ? $role->value : $role;

        return $this->roles->contains(
            fn (Role $assignedRole): bool => $assignedRole->name->value === $value,
        );
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.fallback_locale', 'fr');
    }

    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        Mail::to($this->email)->send(
            (new VerifyEmailMail($url))->locale($this->mailLocale()),
        );
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = route('password.reset', [
            'token' => $token,
            'email' => $this->getEmailForPasswordReset(),
        ]);

        Mail::to($this->email)->send(
            (new ResetPasswordMail($token, $url))->locale($this->mailLocale()),
        );
    }

    private function mailLocale(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    /**
     * Calculate the user's current age from their birth date.
     *
     * @return Attribute<covariant int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birth_date?->age);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'status' => UserStatus::class,
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}

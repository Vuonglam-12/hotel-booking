<?php

namespace App\Support;

use App\Models\Customer;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final class CustomerSessions
{
    public const PASSWORD_TOKEN = 'customer_password_token';
    public const GOOGLE_TOKEN = 'customer_google_token';

    public static function issuePasswordToken(Customer $customer): NewAccessToken
    {
        return $customer->createToken(self::PASSWORD_TOKEN, ['customer', 'auth:password'], now()->addDays(7));
    }

    public static function issueGoogleToken(Customer $customer): NewAccessToken
    {
        return $customer->createToken(self::GOOGLE_TOKEN, ['customer', 'auth:google'], now()->addDays(7));
    }

    public static function isAdminToken(Customer $customer, mixed $token): bool
    {
        // Match the existing Admin boundary, including legacy wildcard Admin tokens.
        return $customer->isAdmin() && $token instanceof PersonalAccessToken && $token->name === 'admin_token'
            && is_array($token->abilities)
            && (in_array('admin', $token->abilities, true) || in_array('*', $token->abilities, true));
    }

    public static function revokePasswordTokens(Customer $customer, ?int $exceptId = null): int
    {
        // Used only when rotating known password logins. auth_token remains unclassified.
        return $customer->tokens()->whereIn('name', [self::PASSWORD_TOKEN, 'customer_token'])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->delete();
    }

    public static function revokeCustomerTokens(Customer $customer, ?int $exceptId = null): int
    {
        // Call inside the transaction holding this Customer row lock. Revoke every
        // non-Admin token, including Google, unknown names and ambiguous auth_token.
        $ids = $customer->tokens()->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->get()->reject(fn ($token) => self::isAdminToken($customer, $token))->modelKeys();

        return $customer->tokens()->whereIn('id', $ids)->delete();
    }
}

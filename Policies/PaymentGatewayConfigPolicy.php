<?php

declare(strict_types=1);

namespace Modules\Payment\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Payment\Models\PaymentGatewayConfig;

class PaymentGatewayConfigPolicy
{
    use HandlesAuthorization;

    public function before(AuthUser $authUser, string $ability): ?bool
    {
        if (method_exists($authUser, 'hasRole') && ($authUser->hasRole('super_admin') || $authUser->hasRole('super-admin'))) {
            return true;
        }

        return null;
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PaymentGatewayConfig') || $authUser->can('ViewAny:PaymentGateway');
    }

    public function view(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('View:PaymentGatewayConfig') || $authUser->can('View:PaymentGateway');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PaymentGatewayConfig') || $authUser->can('Create:PaymentGateway');
    }

    public function update(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('Update:PaymentGatewayConfig') || $authUser->can('Update:PaymentGateway');
    }

    public function delete(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('Delete:PaymentGatewayConfig') || $authUser->can('Delete:PaymentGateway');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PaymentGatewayConfig') || $authUser->can('DeleteAny:PaymentGateway');
    }

    public function restore(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('Restore:PaymentGatewayConfig') || $authUser->can('Restore:PaymentGateway');
    }

    public function forceDelete(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('ForceDelete:PaymentGatewayConfig') || $authUser->can('ForceDelete:PaymentGateway');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PaymentGatewayConfig') || $authUser->can('ForceDeleteAny:PaymentGateway');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PaymentGatewayConfig') || $authUser->can('RestoreAny:PaymentGateway');
    }

    public function replicate(AuthUser $authUser, PaymentGatewayConfig $config): bool
    {
        return $authUser->can('Replicate:PaymentGatewayConfig') || $authUser->can('Replicate:PaymentGateway');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PaymentGatewayConfig') || $authUser->can('Reorder:PaymentGateway');
    }
}

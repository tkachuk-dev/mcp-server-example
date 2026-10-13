<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityEventType: string
{
    case FailedLoginBurst = 'failed_login_burst';
    case AccountLocked = 'account_locked';
    case TwoFactorDisabled = '2fa_disabled';
    case PasswordReset = 'password_reset';
    case RoleChanged = 'role_changed';
}

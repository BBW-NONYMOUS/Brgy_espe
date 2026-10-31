<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';
    case ViewedSensitive = 'viewed_sensitive';
    case Exported = 'exported';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Locked = 'locked';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case Backup = 'backup';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Archived',
            self::Restored => 'Restored',
            self::ForceDeleted => 'Permanently deleted',
            self::ViewedSensitive => 'Viewed sensitive data',
            self::Exported => 'Exported',
            self::Login => 'Logged in',
            self::Logout => 'Logged out',
            self::LoginFailed => 'Failed login',
            self::Locked => 'Account locked',
            self::PasswordChanged => 'Password changed',
            self::PasswordReset => 'Password reset by admin',
            self::Backup => 'Backup',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}

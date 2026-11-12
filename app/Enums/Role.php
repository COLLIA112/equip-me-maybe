<?php

namespace App\Enums;

enum Role: string
{
    case DepartmentHead = 'department_head';
    case LabManager = 'lab_manager';
    case Student = 'student';
    case Faculty = 'faculty';
    case SecurityAuditor = 'security_auditor';

    public function label(): string
    {
        return match ($this) {
            self::DepartmentHead => 'Department Head',
            self::LabManager => 'Lab Manager',
            self::Student => 'Student Borrower',
            self::Faculty => 'Faculty Member',
            self::SecurityAuditor => 'Campus Security Auditor',
        };
    }

    /** Roles a Department Head is allowed to hand out. */
    public static function assignable(): array
    {
        return [self::LabManager, self::Faculty, self::SecurityAuditor, self::Student];
    }
}

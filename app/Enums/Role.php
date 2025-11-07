<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN_HR = 'admin_hr';
    case MANAGER = 'manager';
    case EMPLOYEE = 'employee';
}

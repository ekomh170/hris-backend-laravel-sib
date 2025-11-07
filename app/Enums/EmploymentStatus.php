<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case PERMANENT = 'permanent';
    case CONTRACT = 'contract';
    case INTERN = 'intern';
    case RESIGNED = 'resigned';
}

<?php

namespace App\Enums;

enum PatientStatus: string
{
    case Critical = 'critical';
    case Stable = 'stable';
    case UnderReview = 'under review';
}

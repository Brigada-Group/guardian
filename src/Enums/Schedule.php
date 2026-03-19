<?php

namespace Brigada\Guardian\Enums;

enum Schedule: string
{
    case EveryFiveMinutes = 'every_5_min';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}

<?php

namespace Brigada\Guardian\Enums;

enum Severity: string
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';
}

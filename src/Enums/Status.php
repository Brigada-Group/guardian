<?php

namespace Brigada\Guardian\Enums;

enum Status: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Error = 'error';
}

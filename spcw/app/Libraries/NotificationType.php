<?php

namespace App\Libraries;

enum NotificationType: string
{
    case INFO = 'wa-neutral';
    case BRAND = 'wa-brand';
    case SUCCESS = 'wa-success';
    case WARNING = 'wa-warning';
    case ERROR = 'wa-danger';
}

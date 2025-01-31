<?php

namespace App\Cells;

use CodeIgniter\View\Cells\Cell;

use App\Libraries\NotificationType;

class NotificationCell extends Cell
{
    public NotificationType $type = NotificationType::INFO;
    public string $body = '';
    public bool $persistent = false;
    public bool $showDismissButton = false;

    public array $classes = ['wa-outlined', 'wa-filled'];

    public function mount(): void
    {
        $this->classes[] = $this->type->value;
        if ($this->persistent) {
            $this->classes[] = 'msg-persistent';
            $this->showDismissButton = true;
        }
    }
}

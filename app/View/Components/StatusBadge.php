<?php

namespace App\View\Components;

use App\Support\StatusBadges;
use Illuminate\View\Component;
use Illuminate\View\View;

class StatusBadge extends Component
{
    public string $label;

    public string $badgeClass;

    public function __construct(public string $type, public ?string $status)
    {
        ['label' => $this->label, 'class' => $this->badgeClass] = StatusBadges::for($type, $status);
    }

    public function render(): View
    {
        return view('components.status-badge');
    }
}

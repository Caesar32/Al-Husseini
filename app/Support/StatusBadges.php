<?php

namespace App\Support;

/**
 * The single status -> badge mapping (label + Bootstrap classes) for server-rendered
 * views (<x-status-badge>) and, through all(), for page scripts.
 * Labels and classes are copied verbatim from the views they replace.
 */
final class StatusBadges
{
    /** @var array<string, array<string, array{label: string, class: string}>> */
    private const MAP = [
        'invoice' => [
            'paid' => ['label' => 'مدفوعة بالكامل', 'class' => 'bg-success'],
            'partially_paid' => ['label' => 'مدفوعة جزئياً (آجل)', 'class' => 'bg-warning text-dark'],
            'unpaid' => ['label' => 'آجل غير مسدد', 'class' => 'bg-danger'],
            'refunded' => ['label' => 'مرتجع', 'class' => 'bg-secondary'],
            'partially_refunded' => ['label' => 'مرتجع جزئي', 'class' => 'bg-warning text-dark'],
            'cancelled' => ['label' => 'ملغاة', 'class' => 'bg-dark'],
        ],
    ];

    /** Unknown types or statuses fall back to the raw value on bg-info (the previous default). */
    public static function for(string $type, ?string $status): array
    {
        return self::MAP[$type][$status ?? ''] ?? ['label' => (string) $status, 'class' => 'bg-info'];
    }

    /** @return array<string, array<string, array{label: string, class: string}>> */
    public static function all(): array
    {
        return self::MAP;
    }
}

<?php

namespace App\Enums;

enum PriorityLevel: int
{
    case Urgent = 1;
    case High = 2;
    case Normal = 3;
    case Low = 4;

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent',
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Urgent => '#EF4444',
            self::High => '#F59E0B',
            self::Normal => '#3B82F6',
            self::Low => '#6B7280',
        };
    }

    public function toArray(): array
    {
        return [
            'level' => $this->value,
            'name' => $this->label(),
            'color' => $this->color(),
        ];
    }

    public static function allToArray(): array
    {
        return array_map(fn (self $p) => $p->toArray(), self::cases());
    }
}

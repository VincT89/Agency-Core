<?php

namespace App\Enums\Shooting;

enum ShootSlotPeriod: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    
    public function label(): string
    {
        return match($this) {
            self::Morning => 'Mattina',
            self::Afternoon => 'Pomeriggio',
        };
    }
    
    public function getStartTime(): string
    {
        return match($this) {
            self::Morning => '09:00:00',
            self::Afternoon => '15:00:00',
        };
    }
    
    public function getEndTime(): string
    {
        return match($this) {
            self::Morning => '13:00:00',
            self::Afternoon => '20:00:00',
        };
    }
}

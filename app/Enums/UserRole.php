<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin          = 'admin';
    case Administration = 'administration';
    case Developer      = 'developer';
    case Marketing      = 'marketing';
    case Photographer   = 'photographer';
    case GraphicDesigner= 'graphic_designer';

    public function label(): string
    {
        return match($this) {
            self::Admin             => 'Amministratore',
            self::Administration    => 'Amministrazione',
            self::Developer         => 'Developer',
            self::Marketing         => 'Marketing',
            self::Photographer      => 'Fotografo',
            self::GraphicDesigner   => 'Grafica',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Admin             => 'var(--gray)',
            self::Administration    => 'var(--indigo)', // Indigo
            self::Developer         => 'var(--teal)',   // Teal
            self::Marketing         => 'var(--amber)',  // Amber soft
            self::Photographer      => 'var(--cyan)',   // Cyan muted
            self::GraphicDesigner   => 'var(--magenta)',// Magenta
        };
    }
}

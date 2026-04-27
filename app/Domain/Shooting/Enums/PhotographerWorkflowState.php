<?php

namespace App\Domain\Shooting\Enums;

enum PhotographerWorkflowState: string
{
    case NEEDS_SYNC = 'needs_sync';
    case IMPORTED_WITH_WARNINGS = 'imported_with_warnings';
    case NEEDS_SELECTION = 'needs_selection';
    case SELECTION_IN_PROGRESS = 'selection_in_progress';
    case NEEDS_DELIVERY = 'needs_delivery';
    case DELIVERY_IN_PROGRESS = 'delivery_in_progress';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match($this) {
            self::NEEDS_SYNC => 'Da Sincronizzare',
            self::IMPORTED_WITH_WARNINGS => 'Importato con Avvisi',
            self::NEEDS_SELECTION => 'Da Selezionare',
            self::SELECTION_IN_PROGRESS => 'Selezione in Corso',
            self::NEEDS_DELIVERY => 'Da Consegnare',
            self::DELIVERY_IN_PROGRESS => 'Consegna in Preparazione',
            self::COMPLETED => 'Completato',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NEEDS_SYNC => 'var(--orange)',
            self::IMPORTED_WITH_WARNINGS => 'var(--red)',
            self::NEEDS_SELECTION => 'var(--blue)',
            self::SELECTION_IN_PROGRESS => 'var(--blue)',
            self::NEEDS_DELIVERY => 'var(--purple)',
            self::DELIVERY_IN_PROGRESS => 'var(--purple)',
            self::COMPLETED => 'var(--green)',
        };
    }
}

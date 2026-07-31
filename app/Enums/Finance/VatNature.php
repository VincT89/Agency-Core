<?php

namespace App\Enums\Finance;

enum VatNature: string
{
    case N1 = 'N1';
    case N21 = 'N2.1';
    case N22 = 'N2.2';
    case N31 = 'N3.1';
    case N32 = 'N3.2';
    case N33 = 'N3.3';
    case N34 = 'N3.4';
    case N35 = 'N3.5';
    case N36 = 'N3.6';
    case N4 = 'N4';
    case N5 = 'N5';
    case N61 = 'N6.1';
    case N62 = 'N6.2';
    case N63 = 'N6.3';
    case N64 = 'N6.4';
    case N65 = 'N6.5';
    case N66 = 'N6.6';
    case N67 = 'N6.7';
    case N68 = 'N6.8';
    case N69 = 'N6.9';
    case N7 = 'N7';

    public function label(): string
    {
        return match ($this) {
            self::N1 => 'N1 - Escluse',
            self::N21 => 'N2.1 - Non soggette, articoli 7-7-septies',
            self::N22 => 'N2.2 - Non soggette, altri casi',
            self::N31 => 'N3.1 - Non imponibili, esportazioni',
            self::N32 => 'N3.2 - Non imponibili, cessioni intracomunitarie',
            self::N33 => 'N3.3 - Non imponibili, cessioni verso San Marino',
            self::N34 => 'N3.4 - Non imponibili, operazioni assimilate',
            self::N35 => 'N3.5 - Non imponibili, dichiarazioni d’intento',
            self::N36 => 'N3.6 - Non imponibili, altre operazioni',
            self::N4 => 'N4 - Esenti',
            self::N5 => 'N5 - Regime del margine o IVA non esposta',
            self::N61 => 'N6.1 - Inversione contabile, rottami',
            self::N62 => 'N6.2 - Inversione contabile, oro e argento',
            self::N63 => 'N6.3 - Inversione contabile, subappalto edile',
            self::N64 => 'N6.4 - Inversione contabile, fabbricati',
            self::N65 => 'N6.5 - Inversione contabile, telefoni cellulari',
            self::N66 => 'N6.6 - Inversione contabile, prodotti elettronici',
            self::N67 => 'N6.7 - Inversione contabile, comparto edile',
            self::N68 => 'N6.8 - Inversione contabile, settore energetico',
            self::N69 => 'N6.9 - Inversione contabile, altri casi',
            self::N7 => 'N7 - IVA assolta in altro Stato UE',
        };
    }
}

<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case UnderRepair = 'under_repair';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::UnderRepair => 'Under repair',
        };
    }

    /** The repair gate: items under repair cannot be reserved. */
    public function blocksReservation(): bool
    {
        return $this === self::UnderRepair;
    }
}

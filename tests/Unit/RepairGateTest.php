<?php

namespace Tests\Unit;

use App\Enums\EquipmentStatus;
use PHPUnit\Framework\TestCase;

class RepairGateTest extends TestCase
{
    public function test_only_items_under_repair_block_reservations(): void
    {
        $this->assertTrue(EquipmentStatus::UnderRepair->blocksReservation());
        $this->assertFalse(EquipmentStatus::Available->blocksReservation());
        $this->assertFalse(EquipmentStatus::Reserved->blocksReservation());
    }
}

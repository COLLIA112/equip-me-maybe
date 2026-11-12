<?php

namespace Tests\Unit;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use PHPUnit\Framework\TestCase;

class EquipmentStatusLabelTest extends TestCase
{
    public function test_status_label_accessor_formats_status_for_display(): void
    {
        $item = new Equipment(['status' => EquipmentStatus::UnderRepair]);

        $this->assertSame('Under repair', $item->status_label);
    }

    public function test_new_equipment_defaults_to_available(): void
    {
        $this->assertSame('Available', (new Equipment)->status_label);
    }
}

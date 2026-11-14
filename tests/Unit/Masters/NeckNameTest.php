<?php

namespace Tests\Unit\Masters;

use App\Models\Neck;
use PHPUnit\Framework\TestCase;

class NeckNameTest extends TestCase
{
    public function test_name_lists_every_parameter_given(): void
    {
        $this->assertSame('28 mm PCO 1881 – H 21 mm – ID 21.7 mm – OD 27.4 mm', Neck::composeName([
            'neck_size_mm' => '28.00', 'neck_type' => 'PCO 1881', 'neck_height_mm' => 21, 'inner_dia_mm' => 21.7, 'outer_dia_mm' => '27.40',
        ]));
    }

    public function test_name_leaves_out_blank_parameters(): void
    {
        $this->assertSame('38 mm', Neck::composeName(['neck_size_mm' => 38]));
        $this->assertSame('38 mm – H 18 mm', Neck::composeName(['neck_size_mm' => 38, 'neck_type' => '  ', 'neck_height_mm' => 18, 'inner_dia_mm' => null]));
        $this->assertSame('3-Start – H 10 mm', Neck::composeName(['neck_type' => '3-Start', 'neck_height_mm' => 10]));
        $this->assertSame('', Neck::composeName([]));
    }

    public function test_necks_differing_in_any_parameter_get_different_names(): void
    {
        $base = ['neck_size_mm' => 28, 'neck_type' => 'PCO 1881', 'neck_height_mm' => 21, 'inner_dia_mm' => 21.7, 'outer_dia_mm' => 27.4];
        $names = [Neck::composeName($base)];

        foreach (['neck_size_mm' => 38, 'neck_type' => 'Alaska', 'neck_height_mm' => 22, 'inner_dia_mm' => 22, 'outer_dia_mm' => 28] as $field => $value) {
            $names[] = Neck::composeName([$field => $value] + $base);
        }

        $this->assertCount(6, array_unique($names));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Machine;
use App\Models\Mould;
use App\Models\Neck;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NeckMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    protected function subAdminWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $module => $actions) {
            $row = ['can_show' => true];
            foreach ($actions as $action) {
                $row["can_$action"] = true;
            }
            $user->permissions()->create(['module' => $module] + $row);
        }

        return $user;
    }

    protected function neck(float $size = 28, array $extra = []): Neck
    {
        return Neck::create($extra + ['neck_size_mm' => $size, 'neck_type' => 'PCO 1881', 'status' => 'active']);
    }

    protected function mould(?Neck $neck = null, array $extra = []): Mould
    {
        return Mould::create($extra + ['mould_type' => 'blow', 'capacity_ml' => 1000, 'shape' => 'round', 'neck_id' => $neck?->id, 'weight_grams' => 32, 'cavities' => 2, 'status' => 'active']);
    }

    protected function payload(Mould $mould, array $extra = []): array
    {
        return $extra + [
            'bill_name' => '1 L Bottle', 'owner_type' => 'company', 'product_type' => 'bottle', 'business_type' => 'manufacturing',
            'mould_id' => $mould->id, 'material' => 'PET', 'colour' => 'Clear', 'unit' => 'pcs', 'status' => 'active',
        ];
    }

    /** A product saved through the real form. */
    protected function product(Mould $mould, array $extra = []): Product
    {
        $this->post('/masters/products', $this->payload($mould, $extra))->assertSessionHasNoErrors()->assertRedirect();

        return Product::latest('id')->first();
    }

    // ------------------------------------------------------------- Serial numbers

    public function test_neck_mould_machine_and_product_each_have_their_own_serial_series(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $mould = $this->mould($neck);
        $product = $this->product($mould);
        $machine = Machine::create(['machine_type' => 'blow', 'status' => 'active']);

        $this->assertSame('NCK-0001', $neck->code);
        $this->assertSame('MLD-0001', $mould->code);
        $this->assertSame('PRD-0001', $product->code);
        $this->assertSame('MCH-0001', $machine->code);

        foreach (['necks', 'moulds', 'products', 'machines'] as $master) {
            $this->get("/masters/$master")->assertOk()->assertSee('Serial no.');
            $this->get("/masters/$master/create")->assertOk()->assertSee('serial no.');
        }
    }

    public function test_a_serial_number_cannot_be_supplied_from_the_form(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/masters/necks', ['code' => 'HACK-1', 'neck_size_mm' => 28, 'status' => 'active'])->assertSessionHasNoErrors();

        $this->assertSame('NCK-0001', Neck::sole()->code);
    }

    // ------------------------------------------------------------- Neck Master

    public function test_neck_is_recorded_with_all_its_parameters(): void
    {
        $this->actingAs($admin = $this->superAdmin());

        $this->post('/masters/necks', ['neck_size_mm' => 28, 'neck_type' => 'PCO 1881', 'neck_height_mm' => 21, 'inner_dia_mm' => 21.7, 'outer_dia_mm' => 27.4, 'status' => 'active'])->assertRedirect();

        $neck = Neck::sole();
        $this->assertSame('28 mm PCO 1881 – H 21 mm – ID 21.7 mm – OD 27.4 mm', $neck->name);
        $this->assertSame($admin->id, $neck->created_by);
        $this->get('/masters/necks')->assertOk()->assertSee('NCK-0001')->assertSee('PCO 1881');
        $this->get("/masters/necks/{$neck->id}")->assertOk()->assertSee('21.7 mm');
    }

    public function test_neck_validation(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/masters/necks', ['status' => 'active'])->assertSessionHasErrors('neck_size_mm');
        $this->post('/masters/necks', ['neck_size_mm' => -1, 'status' => 'active'])->assertSessionHasErrors('neck_size_mm');
        $this->post('/masters/necks', ['neck_size_mm' => 28, 'neck_height_mm' => 0, 'status' => 'active'])->assertSessionHasErrors('neck_height_mm');
        $this->post('/masters/necks', ['neck_size_mm' => 28, 'status' => 'gone'])->assertSessionHasErrors('status');
        $this->assertSame(0, Neck::count());
    }

    public function test_an_identical_neck_cannot_have_a_second_serial_number(): void
    {
        $this->actingAs($this->superAdmin());
        $first = $this->neck(28, ['neck_height_mm' => 21]);

        $this->post('/masters/necks', ['neck_size_mm' => '28.00', 'neck_type' => 'PCO 1881', 'neck_height_mm' => 21, 'status' => 'active'])
            ->assertSessionHasErrors(['neck_size_mm' => "This neck already exists as {$first->code}."]);
        $this->assertSame(1, Neck::count());

        // Differing in any one parameter makes it another neck.
        $this->post('/masters/necks', ['neck_size_mm' => 28, 'neck_type' => 'PCO 1881', 'neck_height_mm' => 22, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->post('/masters/necks', ['neck_size_mm' => 28, 'neck_type' => 'PCO 1881', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame(3, Neck::count());
    }

    public function test_a_neck_in_use_keeps_its_parameters_but_an_unused_one_can_be_corrected(): void
    {
        $this->actingAs($this->superAdmin());
        $used = $this->neck(28);
        $product = $this->product($this->mould($used));
        $free = $this->neck(38);

        $this->get("/masters/necks/{$used->id}/edit")->assertOk()->assertSee('This neck is in use');
        $this->put("/masters/necks/{$used->id}", ['neck_size_mm' => 99, 'neck_type' => 'Other', 'status' => 'inactive', 'remarks' => 'Old'])->assertSessionHasNoErrors();

        $used->refresh();
        $this->assertEquals(28, $used->neck_size_mm, 'Parameters are ignored once in use');
        $this->assertSame('inactive', $used->status);
        $this->assertSame('Old', $used->remarks);
        $this->assertEquals(28, $product->fresh()->neck_size_mm);

        $this->put("/masters/necks/{$free->id}", ['neck_size_mm' => 40, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertEquals(40, $free->fresh()->neck_size_mm);
        $this->assertNull($free->fresh()->neck_type);
    }

    public function test_a_neck_in_use_cannot_be_deleted(): void
    {
        $this->actingAs($this->superAdmin());
        $onMould = $this->neck(28);
        $this->mould($onMould);
        $onProduct = $this->neck(38);
        Product::create(['bill_name' => 'x', 'product_type' => 'jar', 'business_type' => 'trading', 'neck_id' => $onProduct->id, 'unit' => 'pcs', 'status' => 'active']);
        $free = $this->neck(53);

        $this->delete("/masters/necks/{$onMould->id}")->assertSessionHas('error');
        $this->delete("/masters/necks/{$onProduct->id}")->assertSessionHas('error');
        $this->assertSame(3, Neck::count());

        $this->delete("/masters/necks/{$free->id}")->assertRedirect('/masters/necks');
        $this->assertDatabaseMissing('necks', ['id' => $free->id]);
    }

    public function test_inactive_necks_are_not_offered_but_stay_on_records_already_using_them(): void
    {
        $this->actingAs($this->superAdmin());
        $old = $this->neck(28, ['status' => 'inactive']);
        $mould = $this->mould($old);

        $this->get('/masters/moulds/create')->assertDontSee($old->code);
        $this->get("/masters/moulds/{$mould->id}/edit")->assertSee($old->code);
    }

    public function test_neck_page_lists_what_uses_it_and_list_filters_work(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(28);
        $mould = $this->mould($neck);
        $product = $this->product($mould);
        $this->neck(38, ['neck_type' => 'Alaska', 'status' => 'inactive']);

        $this->get("/masters/necks/{$neck->id}")->assertOk()->assertSee($mould->code)->assertSee($product->code)->assertDontSee('>Delete', false);
        $this->get('/dashboard');
        $this->get('/masters/necks?search=Alaska')->assertSee('NCK-0002')->assertDontSee('NCK-0001');
        $this->get('/masters/necks?status=inactive')->assertSee('NCK-0002')->assertDontSee('NCK-0001');
    }

    public function test_neck_rights_are_enforced(): void
    {
        $neck = $this->neck();

        $this->actingAs($this->subAdminWith([]));
        foreach (['/masters/necks', '/masters/necks/create', "/masters/necks/{$neck->id}", "/masters/necks/{$neck->id}/edit"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/masters/necks', [])->assertForbidden();
        $this->delete("/masters/necks/{$neck->id}")->assertForbidden();

        $this->actingAs($this->subAdminWith(['neck_master' => []]));
        $this->get('/masters/necks')->assertOk()->assertDontSee($neck->code);

        $this->actingAs($this->subAdminWith(['neck_master' => ['view']]));
        $this->get('/masters/necks')->assertOk()->assertSee($neck->code)->assertDontSee('Add Neck');
        $this->get("/masters/necks/{$neck->id}/edit")->assertForbidden();
    }

    public function test_migration_builds_necks_from_existing_data_and_copies_mould_rights(): void
    {
        $migration = require database_path('migrations/2026_11_07_000003_create_neck_master.php');
        $user = User::factory()->create();
        $migration->down();

        $now = now();
        $mould = fn ($code, $size, $type, $height) => DB::table('moulds')->insertGetId(['code' => $code, 'name' => $code, 'mould_type' => 'blow', 'neck_size_mm' => $size, 'neck_type' => $type, 'neck_height_mm' => $height, 'cavities' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $a = $mould('MLD-0001', 28, 'PCO', null);
        $b = $mould('MLD-0002', 28, 'PCO', null);
        $c = $mould('MLD-0003', 28, 'PCO', 21);
        $none = $mould('MLD-0004', null, null, null);
        $noSize = $mould('MLD-0005', null, '3-Start', 10);
        $product = DB::table('products')->insertGetId(['code' => 'PRD-0001', 'name' => 'P', 'bill_name' => 'P', 'neck_size_mm' => 50, 'product_type' => 'other', 'business_type' => 'trading', 'unit' => 'pcs', 'created_at' => $now, 'updated_at' => $now]);
        $same = DB::table('products')->insertGetId(['code' => 'PRD-0002', 'name' => 'Q', 'bill_name' => 'Q', 'neck_size_mm' => 28, 'neck_type' => 'PCO', 'product_type' => 'bottle', 'business_type' => 'both', 'unit' => 'pcs', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('user_permissions')->insert(['user_id' => $user->id, 'module' => 'mould_master', 'can_show' => 1, 'can_view' => 1, 'can_create' => 1, 'can_edit' => 0, 'can_delete' => 0]);

        $migration->up();

        $neckOf = fn ($table, $id) => DB::table($table)->where('id', $id)->value('neck_id');
        $this->assertSame(4, Neck::count());
        $this->assertSame($neckOf('moulds', $a), $neckOf('moulds', $b), 'Identical necks share one serial');
        $this->assertNotSame($neckOf('moulds', $a), $neckOf('moulds', $c), 'A different height is a different neck');
        $this->assertSame($neckOf('moulds', $a), $neckOf('products', $same), 'Moulds and products share necks');
        $this->assertNull($neckOf('moulds', $none));
        $this->assertSame('3-Start – H 10 mm', Neck::find($neckOf('moulds', $noSize))->name);
        $this->assertEquals(50, Neck::find($neckOf('products', $product))->neck_size_mm);
        $this->assertSame('NCK-0005', Neck::nextCode());
        $this->assertDatabaseHas('user_permissions', ['user_id' => $user->id, 'module' => 'neck_master', 'can_create' => 1, 'can_edit' => 0]);
        $this->assertDatabaseHas('moulds', ['id' => $a, 'name' => 'MLD-0001', 'neck_size_mm' => 28]);
    }

    // ------------------------------------------------------------- Mould's default neck

    public function test_mould_takes_its_default_neck_from_neck_master(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(28, ['neck_height_mm' => 21]);

        $this->post('/masters/moulds', ['mould_type' => 'blow', 'shape' => 'round', 'capacity_value' => 500, 'capacity_unit' => 'ml', 'neck_id' => $neck->id, 'weight_grams' => 18, 'cavities' => 2, 'status' => 'active',
            'neck_size_mm' => 99, 'neck_type' => 'ignored'])->assertSessionHasNoErrors();

        $mould = Mould::sole();
        $this->assertEquals(28, $mould->neck_size_mm);
        $this->assertSame('PCO 1881', $mould->neck_type);
        $this->assertEquals(21, $mould->neck_height_mm);
        $this->assertSame('500 ml Round Blow Mould – 28 mm PCO 1881 – 18 g – 2 Cav', $mould->name);
        $this->get("/masters/moulds/{$mould->id}")->assertOk()->assertSee($neck->code);

        $this->post('/masters/moulds', ['mould_type' => 'blow', 'neck_id' => 99999, 'cavities' => 2, 'status' => 'active'])->assertSessionHasErrors('neck_id');
    }

    public function test_new_neck_can_be_added_from_the_mould_form(): void
    {
        $this->actingAs($this->superAdmin());
        $post = ['mould_type' => 'blow', 'cavities' => 2, 'status' => 'active', 'neck_id' => Neck::NEW];

        $this->get('/masters/moulds/create')->assertOk()->assertSee('+ Add new neck');
        $this->post('/masters/moulds', $post)->assertSessionHasErrors('new_neck_size_mm');
        $this->assertSame(0, Neck::count());

        $this->post('/masters/moulds', $post + ['new_neck_size_mm' => 38, 'new_neck_type' => 'Alaska', 'new_neck_height_mm' => 18])->assertSessionHasNoErrors();

        $neck = Neck::sole();
        $this->assertSame('38 mm Alaska – H 18 mm', $neck->name);
        $this->assertSame($neck->id, Mould::sole()->neck_id);
    }

    // ------------------------------------------------------------- Product identity

    public function test_product_starts_with_the_moulds_neck_and_weight(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(28);
        $mould = $this->mould($neck, ['weight_grams' => 32]);

        $product = $this->product($mould);

        $this->assertSame($neck->id, $product->neck_id);
        $this->assertEquals(28, $product->neck_size_mm);
        $this->assertEquals(32, $product->weight_grams);
        $this->assertTrue($product->usesMouldNeck());
        $this->assertTrue($product->usesMouldWeight());
        $this->assertSame('1 L Round PET Bottle – 28 mm PCO 1881 – 32 g – Clear', $product->name);
    }

    public function test_client_can_get_a_different_neck_and_weight_than_the_mould_default(): void
    {
        $this->actingAs($this->superAdmin());
        $default = $this->neck(28);
        $clientNeck = $this->neck(38, ['neck_type' => 'Alaska']);
        $mould = $this->mould($default);

        $product = $this->product($mould, ['neck_id' => $clientNeck->id, 'weight_grams' => 40, 'owner_type' => 'client', 'client_name' => 'Asha Oils']);

        $this->assertSame($clientNeck->id, $product->neck_id);
        $this->assertEquals(38, $product->neck_size_mm);
        $this->assertSame('Alaska', $product->neck_type);
        $this->assertEquals(40, $product->weight_grams);
        $this->assertSame('1 L Round PET Bottle – 38 mm Alaska – 40 g – Clear – Client: Asha Oils', $product->name);
        $this->assertSame($mould->id, $product->mould_id, 'Still made with the same mould');
        $this->assertFalse($product->usesMouldNeck());
        $this->assertFalse($product->usesMouldWeight());

        $this->get("/masters/products/{$product->id}")->assertOk()->assertSee($clientNeck->code)->assertSee("Not the mould's default", false);
    }

    public function test_volume_and_shape_stay_locked_to_the_mould(): void
    {
        $this->actingAs($this->superAdmin());
        $product = $this->product($this->mould($this->neck()), ['capacity_value' => 5, 'capacity_unit' => 'l', 'shape' => 'square']);

        $this->assertEquals(1000, $product->capacity_ml);
        $this->assertSame('round', $product->shape);
    }

    public function test_changing_the_weight_makes_a_new_product_and_leaves_the_old_one_alone(): void
    {
        $this->actingAs($admin = $this->superAdmin());
        $neck = $this->neck();
        $old = $this->product($this->mould($neck), ['hsn_code' => '3923', 'remarks' => 'Keep']);
        $before = $old->fresh()->getAttributes();

        $this->put("/masters/products/{$old->id}", $this->payload($old->mould, ['weight_grams' => 35, 'neck_id' => $neck->id, 'hsn_code' => '3923', 'remarks' => 'Keep']))
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'new product: PRD-0002') && str_contains($m, 'PRD-0001 stays as it was'));

        $this->assertSame($before, $old->fresh()->getAttributes(), 'The original is not touched at all');

        $new = Product::where('code', 'PRD-0002')->sole();
        $this->assertEquals(35, $new->weight_grams);
        $this->assertSame($neck->id, $new->neck_id);
        $this->assertSame($old->id, $new->based_on_id);
        $this->assertSame('3923', $new->hsn_code);
        $this->assertSame($admin->id, $new->created_by);
        $this->assertSame('1 L Round PET Bottle – 28 mm PCO 1881 – 35 g – Clear', $new->name);

        $this->get("/masters/products/{$new->id}")->assertOk()->assertSee('Made from')->assertSee('PRD-0001');
    }

    public function test_changing_the_neck_makes_a_new_product_and_leaves_the_old_one_alone(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(28);
        $other = $this->neck(38);
        $old = $this->product($this->mould($neck));

        $this->put("/masters/products/{$old->id}", $this->payload($old->mould, ['neck_id' => $other->id, 'weight_grams' => 32]))->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame($neck->id, $old->neck_id);
        $this->assertEquals(28, $old->neck_size_mm);

        $new = Product::where('code', 'PRD-0002')->sole();
        $this->assertSame($other->id, $new->neck_id);
        $this->assertEquals(38, $new->neck_size_mm);
    }

    public function test_a_neck_typed_on_the_product_form_also_makes_a_new_product(): void
    {
        $this->actingAs($this->superAdmin());
        $old = $this->product($this->mould($this->neck(28)));

        $this->put("/masters/products/{$old->id}", $this->payload($old->mould, ['neck_id' => Neck::NEW, 'new_neck_size_mm' => 33, 'new_neck_type' => 'Client special', 'weight_grams' => 32]))->assertSessionHasNoErrors();

        $this->assertSame(2, Neck::count());
        $new = Product::where('code', 'PRD-0002')->sole();
        $this->assertSame('NCK-0002', $new->neck->code);
        $this->assertEquals(33, $new->neck_size_mm);
        $this->assertEquals(28, $old->fresh()->neck_size_mm);
    }

    public function test_changing_other_details_keeps_the_same_serial_number(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $product = $this->product($this->mould($neck));

        $this->put("/masters/products/{$product->id}", $this->payload($product->mould, ['neck_id' => $neck->id, 'weight_grams' => '32.00', 'colour' => 'Amber', 'bill_name' => 'New bill name', 'hsn_code' => '3923']))->assertSessionHasNoErrors();

        $this->assertSame(1, Product::count());
        $product->refresh();
        $this->assertSame('PRD-0001', $product->code);
        $this->assertSame('Amber', $product->colour);
        $this->assertSame('New bill name', $product->bill_name);
        $this->assertNull($product->based_on_id);
    }

    public function test_same_specs_cannot_have_two_serial_numbers(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $mould = $this->mould($neck);
        $product = $this->product($mould);

        // Created directly...
        $this->post('/masters/products', $this->payload($mould, ['bill_name' => 'Another name']))
            ->assertSessionHasErrors(['weight_grams' => "A product with exactly these specs already exists: {$product->code} – {$product->name}."]);

        // ...or by editing another product's weight onto it.
        $other = $this->product($mould, ['weight_grams' => 40]);
        $this->put("/masters/products/{$other->id}", $this->payload($mould, ['neck_id' => $neck->id, 'weight_grams' => 32]))->assertSessionHasErrors('weight_grams');
        $this->assertEquals(40, $other->fresh()->weight_grams);

        // ...or by changing another detail so that it matches.
        $amber = $this->product($mould, ['colour' => 'Amber']);
        $this->put("/masters/products/{$amber->id}", $this->payload($mould, ['neck_id' => $neck->id, 'weight_grams' => 32, 'colour' => 'Clear']))->assertSessionHasErrors('weight_grams');

        $this->assertSame(3, Product::count());

        // A different client, weight or neck is allowed.
        $this->product($mould, ['owner_type' => 'client', 'client_name' => 'Asha']);
        $this->assertSame(4, Product::count());
    }

    public function test_saving_a_product_with_no_changes_does_not_trip_any_rule(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $product = $this->product($this->mould($neck));

        $this->get("/masters/products/{$product->id}/edit")->assertOk()->assertSee('data-new-serial', false);
        $this->put("/masters/products/{$product->id}", $this->payload($product->mould, ['neck_id' => $neck->id, 'weight_grams' => 32]))->assertSessionHasNoErrors();

        $this->assertSame(1, Product::count());
    }

    public function test_new_product_gets_its_own_copy_of_the_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $old = $this->product($this->mould($neck), ['image' => UploadedFile::fake()->image('bottle.jpg')]);
        $this->assertNotNull($old->image_path);

        $this->put("/masters/products/{$old->id}", $this->payload($old->mould, ['neck_id' => $neck->id, 'weight_grams' => 35]))->assertSessionHasNoErrors();

        $new = Product::where('code', 'PRD-0002')->sole();
        $this->assertNotNull($new->image_path);
        $this->assertNotSame($old->fresh()->image_path, $new->image_path);
        Storage::disk('public')->assertExists($new->image_path);

        // Deleting the new one must not take the old one's picture with it.
        $new->delete();
        Storage::disk('public')->assertExists($old->fresh()->image_path);
    }

    public function test_changing_the_moulds_default_neck_or_weight_does_not_touch_existing_products(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(28);
        $other = $this->neck(38);
        $mould = $this->mould($neck, ['weight_grams' => 32]);
        $product = $this->product($mould);
        $name = $product->name;

        $this->put("/masters/moulds/{$mould->id}", ['mould_type' => 'blow', 'shape' => 'round', 'capacity_value' => 1, 'capacity_unit' => 'l', 'neck_id' => $other->id, 'weight_grams' => 50, 'cavities' => 2, 'status' => 'active'])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('PRD-0001', $product->code);
        $this->assertSame($neck->id, $product->neck_id);
        $this->assertEquals(28, $product->neck_size_mm);
        $this->assertEquals(32, $product->weight_grams);
        $this->assertSame($name, $product->name);
        $this->assertSame(1, Product::count());

        // New products pick up the new defaults.
        $next = $this->product($mould->fresh(), ['colour' => 'Amber']);
        $this->assertSame($other->id, $next->neck_id);
        $this->assertEquals(50, $next->weight_grams);
    }

    public function test_changing_the_moulds_volume_still_reaches_its_products(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck();
        $mould = $this->mould($neck);
        $product = $this->product($mould);

        $this->put("/masters/moulds/{$mould->id}", ['mould_type' => 'blow', 'shape' => 'round', 'capacity_value' => 2, 'capacity_unit' => 'l', 'neck_id' => $neck->id, 'weight_grams' => 32, 'cavities' => 2, 'status' => 'active'])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertEquals(2000, $product->capacity_ml);
        $this->assertEquals(32, $product->weight_grams);
        $this->assertStringStartsWith('2 L Round', $product->name);
    }

    public function test_trading_product_picks_a_neck_without_any_mould(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = $this->neck(70, ['neck_type' => 'Wide mouth']);

        $this->post('/masters/products', ['bill_name' => 'Bought jar', 'owner_type' => 'company', 'product_type' => 'jar', 'business_type' => 'trading', 'neck_id' => $neck->id, 'weight_grams' => 22, 'unit' => 'pcs', 'status' => 'active'])->assertSessionHasNoErrors();

        $product = Product::sole();
        $this->assertNull($product->mould_id);
        $this->assertEquals(70, $product->neck_size_mm);
    }

    public function test_adding_a_neck_from_a_product_needs_neck_rights(): void
    {
        $mould = $this->mould($this->neck());
        $post = $this->payload($mould, ['neck_id' => Neck::NEW, 'new_neck_size_mm' => 33]);

        $this->actingAs($this->subAdminWith(['product_master' => ['view', 'create']]));
        $this->get('/masters/products/create')->assertOk()->assertDontSee('+ Add new neck');
        $this->post('/masters/products', $post)->assertForbidden();
        $this->assertSame(1, Neck::count());

        $this->actingAs($this->subAdminWith(['product_master' => ['view', 'create'], 'neck_master' => ['view', 'create']]));
        $this->get('/masters/products/create')->assertOk()->assertSee('+ Add new neck');
        $this->post('/masters/products', $post)->assertSessionHasNoErrors();
        $this->assertSame(2, Neck::count());
    }

    public function test_a_neck_typed_twice_reuses_the_same_serial_number(): void
    {
        $this->actingAs($this->superAdmin());
        $existing = $this->neck(28);
        $mould = $this->mould($existing);

        $product = $this->product($mould, ['neck_id' => Neck::NEW, 'new_neck_size_mm' => 28, 'new_neck_type' => 'PCO 1881']);

        $this->assertSame(1, Neck::count());
        $this->assertSame($existing->id, $product->neck_id);
    }

    // ------------------------------------------------------------- Knock-on to sales

    public function test_sales_lines_use_the_products_own_neck_and_keep_their_snapshot(): void
    {
        $this->actingAs($this->superAdmin());
        $default = $this->neck(28);
        $mould = $this->mould($default);
        $old = $this->product($mould);
        $client = $this->product($mould, ['neck_id' => $this->neck(38)->id, 'colour' => 'Amber']);

        $lead = Lead::create(['enquiry_date' => '2026-11-01', 'company_name' => 'Asha', 'phone' => '9000000001', 'source' => 'website', 'status' => 'new']);
        $item = $lead->items()->create(['product_id' => $client->id, 'quantity' => 100, 'qty_basis' => 'per_month']);
        $this->assertEquals(38, $item->neck_size_mm);
        $this->assertSame('existing', $item->mould_status);

        // A new serial for the old product leaves the enquiry pointing at what the buyer asked for.
        $oldLine = $lead->items()->create(['product_id' => $old->id, 'quantity' => 50, 'qty_basis' => 'per_month']);
        $this->put("/masters/products/{$old->id}", $this->payload($mould, ['neck_id' => $default->id, 'weight_grams' => 35]))->assertSessionHasNoErrors();

        $oldLine->refresh();
        $this->assertSame($old->id, $oldLine->product_id);
        $this->assertStringContainsString('32 g', $oldLine->description);
    }
}

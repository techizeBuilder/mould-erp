<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Mould;
use App\Models\Neck;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MastersTest extends TestCase
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

    protected function machine(string $type, array $extra = []): Machine
    {
        return Machine::create($extra + ['name' => ucfirst($type).' machine', 'machine_type' => $type, 'status' => 'active']);
    }

    protected function mould(string $type, array $extra = []): Mould
    {
        return Mould::create($extra + ['name' => ucfirst($type).' mould', 'mould_type' => $type, 'cavities' => 4, 'status' => 'active']);
    }

    public function test_full_bottle_flow_from_machines_to_product_route(): void
    {
        $this->actingAs($this->superAdmin());

        // Machines — code is generated, never taken from the request.
        $this->post('/masters/machines', [
            'code' => 'HACK-1', 'name' => 'Preform Line 1', 'machine_type' => 'preform', 'status' => 'active',
            'make' => 'Haitian', 'tonnage' => 250, 'cavities' => 8,
        ])->assertRedirect();
        $this->post('/masters/machines', [
            'name' => 'Blower 1', 'machine_type' => 'blow', 'make' => 'Chumpower', 'status' => 'active',
            'min_size_value' => 200, 'min_size_unit' => 'ml', 'max_size_value' => 2, 'max_size_unit' => 'l',
        ])->assertRedirect();
        [$preformMachine, $blower] = [Machine::firstWhere('machine_type', 'preform'), Machine::firstWhere('machine_type', 'blow')];
        $this->assertSame('Preform Machine – 250 T – Haitian – 8 Cav', $preformMachine->name);
        $this->assertSame('Blow Moulding Machine – Chumpower – 200 ml – 2 L', $blower->name);
        $this->assertEquals(250, $preformMachine->tonnage);
        $this->assertSame('MCH-0001', $preformMachine->code);
        $this->assertEquals(2000, $blower->max_capacity_ml);

        // Moulds, each linked to the machine type it fits.
        $neck = Neck::create(['neck_size_mm' => 28, 'status' => 'active']);
        $this->post('/masters/moulds', [
            'name' => '28 mm 32 g preform', 'mould_type' => 'preform', 'cavities' => 8, 'status' => 'active',
            'neck_id' => $neck->id, 'machine_ids' => [$preformMachine->id],
        ])->assertRedirect();
        $this->post('/masters/moulds', [
            'name' => '1 L round bottle', 'mould_type' => 'blow', 'shape' => 'round', 'cavities' => 2, 'status' => 'active',
            'capacity_value' => 1, 'capacity_unit' => 'l', 'neck_id' => $neck->id, 'machine_ids' => [$blower->id],
        ])->assertRedirect();
        [$preformMould, $blowMould] = [Mould::firstWhere('mould_type', 'preform'), Mould::firstWhere('mould_type', 'blow')];
        $this->assertSame(['MLD-0001', 'MLD-0002'], [$preformMould->code, $blowMould->code]);
        $this->assertEquals(1000, $blowMould->capacity_ml);
        $this->assertTrue($blower->moulds()->whereKey($blowMould->id)->exists());

        // Product made with the blow mould: its volume and shape override what was posted; the neck starts as the mould's.
        $this->post('/masters/products', [
            'name' => 'ignored', 'bill_name' => '1 Ltr PET Bottle', 'owner_type' => 'company', 'product_type' => 'bottle',
            'business_type' => 'manufacturing', 'mould_id' => $blowMould->id,
            'capacity_value' => 500, 'capacity_unit' => 'ml', 'shape' => 'square', 'neck_size_mm' => 38,
            'weight_grams' => 32, 'material' => 'PET', 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 100,
            'status' => 'active',
        ])->assertRedirect();
        $product = Product::firstWhere('code', 'PRD-0001');
        $this->assertTrue($product->mould->is($blowMould));
        $this->assertEquals([1000, 'round', 28, 32], [$product->capacity_ml, $product->shape, $product->neck_size_mm, $product->weight_grams]);
        $this->assertSame('1 L Round PET Bottle – 28 mm – 32 g – Clear', $product->name);
        $this->assertSame('1 Ltr PET Bottle', $product->bill_name);
        $this->assertSame('Carton of 100', $product->unitLabel());

        $this->get("/masters/products/{$product->id}")->assertOk()
            ->assertSee('1 Ltr PET Bottle')->assertSee($blowMould->name)->assertSee('Blow Moulding Machine – Chumpower');
        $this->get("/masters/moulds/{$blowMould->id}")->assertOk()->assertSee('Blow Moulding Machine – Chumpower')->assertSee($product->name);

        // Changing the mould's volume passes on to its products (and renames them).
        $this->put("/masters/moulds/{$blowMould->id}", ['mould_type' => 'blow', 'shape' => 'round', 'cavities' => 2, 'status' => 'active',
            'capacity_value' => 2, 'capacity_unit' => 'l', 'neck_id' => $neck->id, 'machine_ids' => [$blower->id]])->assertSessionHasNoErrors();
        $this->assertSame('2 L Round PET Bottle – 28 mm – 32 g – Clear', $product->fresh()->name);
        $this->get("/masters/machines/{$blower->id}")->assertOk()->assertSee('2 L Round Blow Mould – 28 mm – 2 Cav');

        foreach (['/masters/machines', '/masters/moulds', '/masters/products', '/masters/machines/create', '/masters/moulds/create',
            '/masters/products/create', "/masters/machines/{$blower->id}/edit", "/masters/moulds/{$blowMould->id}/edit",
            "/masters/products/{$product->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }

        // Deleting a mould unlinks it but keeps the product.
        $this->delete("/masters/moulds/{$blowMould->id}")->assertRedirect('/masters/moulds');
        $this->assertNull($product->fresh()->mould_id);
        $this->get("/masters/products/{$product->id}")->assertOk()->assertSee('No mould linked');
    }

    public function test_moulds_and_machines_must_be_of_matching_types(): void
    {
        $this->actingAs($this->superAdmin());
        $blower = $this->machine('blow');
        $preformMould = $this->mould('preform');

        $this->post('/masters/moulds', ['name' => 'X', 'mould_type' => 'preform', 'cavities' => 1, 'status' => 'active', 'machine_ids' => [$blower->id]])
            ->assertSessionHasErrors('machine_ids');
        $this->post('/masters/machines', ['name' => 'Y', 'machine_type' => 'blow', 'status' => 'active', 'mould_ids' => [$preformMould->id]])
            ->assertSessionHasErrors('mould_ids');
        $this->assertSame(1, Mould::count());
        $this->assertSame(1, Machine::count());

        // "Other" machines accept either kind of mould.
        $this->post('/masters/machines', ['name' => 'Z', 'machine_type' => 'other', 'status' => 'active', 'mould_ids' => [$preformMould->id]])
            ->assertSessionHasNoErrors();
    }

    public function test_product_mould_rules(): void
    {
        $this->actingAs($this->superAdmin());
        $blowMould = $this->mould('blow');
        $preformMould = $this->mould('preform');
        $base = ['bill_name' => 'Preform 28', 'owner_type' => 'company', 'product_type' => 'preform', 'business_type' => 'manufacturing', 'unit' => 'pcs', 'status' => 'active'];

        // A manufactured product needs exactly one mould, of the right type.
        $this->post('/masters/products', $base)->assertSessionHasErrors('mould_id');
        $this->post('/masters/products', $base + ['mould_id' => $blowMould->id])->assertSessionHasErrors('mould_id');
        $this->post('/masters/products', $base + ['mould_id' => $preformMould->id])->assertSessionHasNoErrors();
        $this->post('/masters/products', ['product_type' => 'bottle', 'mould_id' => $preformMould->id] + $base)->assertSessionHasErrors('mould_id');

        // Trading products never keep a mould.
        $this->post('/masters/products', ['bill_name' => 'Bought jar', 'owner_type' => 'company', 'product_type' => 'jar', 'business_type' => 'trading',
            'unit' => 'pcs', 'status' => 'active', 'mould_id' => $blowMould->id])->assertSessionHasNoErrors();
        $this->assertNull(Product::firstWhere('bill_name', 'Bought jar')->mould_id);

        // Packs and cartons need a piece count.
        $this->post('/masters/products', array_merge($base, ['mould_id' => $preformMould->id, 'unit' => 'carton']))->assertSessionHasErrors('pieces_per_pack');

        // A mould can't be switched to a type its linked products can't use.
        $this->put("/masters/moulds/{$preformMould->id}", ['name' => 'P', 'mould_type' => 'blow', 'cavities' => 1, 'status' => 'active'])
            ->assertSessionHasErrors('mould_type');
        $this->assertSame('preform', $preformMould->fresh()->mould_type);
    }

    public function test_machine_size_range_must_be_in_order(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/masters/machines', ['name' => 'M', 'machine_type' => 'blow', 'status' => 'active',
            'min_size_value' => 5, 'min_size_unit' => 'l', 'max_size_value' => 500, 'max_size_unit' => 'ml'])
            ->assertSessionHasErrors('max_size_value');
    }

    public function test_images_are_stored_replaced_and_removed(): void
    {
        Storage::fake('public');
        $this->actingAs($this->superAdmin());

        $this->post('/masters/products', ['bill_name' => 'Jar', 'owner_type' => 'company', 'product_type' => 'jar', 'business_type' => 'trading', 'unit' => 'pcs',
            'status' => 'active', 'image' => UploadedFile::fake()->image('jar.png')])->assertSessionHasNoErrors();
        $product = Product::first();
        Storage::disk('public')->assertExists($first = $product->image_path);

        $this->put("/masters/products/{$product->id}", ['bill_name' => 'Jar', 'owner_type' => 'company', 'product_type' => 'jar', 'business_type' => 'trading',
            'unit' => 'pcs', 'status' => 'active', 'image' => UploadedFile::fake()->image('jar2.jpg')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second = $product->fresh()->image_path);

        $this->post('/masters/moulds', ['name' => 'M', 'mould_type' => 'blow', 'cavities' => 1, 'status' => 'active',
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('image');

        $this->delete("/masters/products/{$product->id}");
        Storage::disk('public')->assertMissing($second);
    }

    public function test_duplicate_prefills_a_variant_with_a_new_code(): void
    {
        $this->actingAs($this->superAdmin());
        $mould = $this->mould('blow');
        $product = Product::create(['bill_name' => '500 ml bottle', 'product_type' => 'bottle', 'business_type' => 'manufacturing',
            'unit' => 'pcs', 'status' => 'active', 'colour' => 'Clear', 'weight_grams' => 18, 'mould_id' => $mould->id]);

        $this->get("/masters/products/create?from={$product->id}")->assertOk()
            ->assertSee('500 ml bottle (variant)')->assertSee('PRD-0002')->assertSee('value="18"', false)
            ->assertSee('<option value="'.$mould->id.'" selected', false);
    }

    public function test_sub_admin_rights_are_enforced_on_every_master_action(): void
    {
        $mould = $this->mould('blow');
        $this->actingAs($this->subAdminWith(['mould_master' => ['view', 'create']]));

        $this->get('/masters/moulds')->assertOk()->assertSee('Add Mould')->assertDontSee("/masters/moulds/{$mould->id}/edit");
        $this->get("/masters/moulds/{$mould->id}")->assertOk()->assertDontSee('Delete');
        $this->post('/masters/moulds', ['name' => 'New', 'mould_type' => 'blow', 'cavities' => 2, 'status' => 'active'])->assertRedirect();
        $this->get("/masters/moulds/{$mould->id}/edit")->assertForbidden();
        $this->put("/masters/moulds/{$mould->id}", ['name' => 'X', 'mould_type' => 'blow', 'cavities' => 1, 'status' => 'active'])->assertForbidden();
        $this->delete("/masters/moulds/{$mould->id}")->assertForbidden();

        $this->get('/masters/products')->assertForbidden();
        $this->post('/masters/machines', [])->assertForbidden();

        $this->get('/dashboard')->assertSee('Masters')->assertSee('Mould Master')
            ->assertDontSee('Product Master')->assertDontSee('Machine Master');
    }

    public function test_show_without_view_hides_records(): void
    {
        $machine = $this->machine('preform', ['name' => 'Secret Line']);
        $this->actingAs($this->subAdminWith(['machine_master' => []]));

        $this->get('/masters/machines')->assertOk()
            ->assertDontSee('Secret Line')
            ->assertSee("You don't have permission to view these records", false);
        $this->get("/masters/machines/{$machine->id}")->assertForbidden();
    }

    public function test_user_without_any_master_sees_no_masters_menu(): void
    {
        $this->actingAs($this->subAdminWith(['sales_leads' => ['view']]));

        $this->get('/dashboard')->assertOk()->assertDontSee('Mould Master')->assertDontSee('data-toggle="group-masters"', false);
    }

    public function test_mould_name_is_generated_from_specs_and_unique(): void
    {
        $this->actingAs($this->superAdmin());
        $neck = Neck::create(['neck_size_mm' => 28, 'neck_type' => 'PCO 1881', 'neck_height_mm' => 21, 'status' => 'active']);
        $specs = ['mould_type' => 'blow', 'shape' => 'round', 'capacity_value' => 500, 'capacity_unit' => 'ml',
            'neck_id' => $neck->id, 'weight_grams' => 18,
            'cavities' => 2, 'status' => 'active'];

        $this->post('/masters/moulds', $specs + ['name' => 'typed name is ignored'])->assertSessionHasNoErrors();
        $this->post('/masters/moulds', $specs)->assertSessionHasNoErrors();
        [$first, $second] = Mould::orderBy('id')->get();

        $this->assertSame('500 ml Round Blow Mould – 28 mm PCO 1881 – 18 g – 2 Cav', $first->name);
        $this->assertSame('500 ml Round Blow Mould – 28 mm PCO 1881 – 18 g – 2 Cav #2', $second->name);
        $this->assertEquals(21, $first->neck_height_mm);

        // Saving again keeps the suffix; changing a spec renames.
        $this->put("/masters/moulds/{$second->id}", $specs)->assertSessionHasNoErrors();
        $this->assertStringEndsWith('#2', $second->fresh()->name);
        $this->put("/masters/moulds/{$second->id}", ['weight_grams' => 20] + $specs)->assertSessionHasNoErrors();
        $this->assertSame('500 ml Round Blow Mould – 28 mm PCO 1881 – 20 g – 2 Cav', $second->fresh()->name);

        $this->get("/masters/moulds/{$first->id}")->assertOk()->assertSee('PCO 1881')->assertSee('21 mm')->assertSee('18 g');
    }

    public function test_client_product_has_client_in_name_and_its_own_bill_name(): void
    {
        $this->actingAs($this->superAdmin());
        $mould = $this->mould('blow', ['capacity_ml' => 500, 'shape' => 'round', 'neck_size_mm' => 28]);
        $base = ['bill_name' => 'Aqua 500', 'product_type' => 'bottle', 'business_type' => 'manufacturing', 'mould_id' => $mould->id,
            'material' => 'PET', 'colour' => 'Blue', 'unit' => 'pcs', 'status' => 'active'];

        $this->post('/masters/products', $base + ['owner_type' => 'client'])->assertSessionHasErrors('client_name');
        $this->post('/masters/products', $base + ['owner_type' => 'nobody'])->assertSessionHasErrors('owner_type');
        $this->post('/masters/products', ['owner_type' => 'company', 'bill_name' => ''] + $base)->assertSessionHasErrors('bill_name');

        // Same specs for VR and for a client are two different products, told apart by name.
        $this->post('/masters/products', $base + ['owner_type' => 'company', 'client_name' => 'dropped'])->assertSessionHasNoErrors();
        $this->post('/masters/products', $base + ['owner_type' => 'client', 'client_name' => 'Aqua Fresh'])->assertSessionHasNoErrors();
        [$own, $client] = Product::orderBy('id')->get();

        $this->assertNull($own->client_name);
        $this->assertSame('500 ml Round PET Bottle – 28 mm – Blue', $own->name);
        $this->assertSame('500 ml Round PET Bottle – 28 mm – Blue – Client: Aqua Fresh', $client->name);
        $this->assertSame('Aqua 500', $client->bill_name);

        $this->get('/masters/products?owner=client')->assertOk()->assertSee('Client: Aqua Fresh')->assertDontSee('/masters/products/'.$own->id.'"', false);
        $this->get("/masters/products/{$client->id}")->assertOk()->assertSee('On bill:')->assertSee('Aqua 500');
    }

    public function test_admin_can_add_a_new_shape_from_the_form(): void
    {
        $this->actingAs($this->superAdmin());
        $base = ['mould_type' => 'blow', 'cavities' => 1, 'status' => 'active'];

        $this->post('/masters/moulds', $base + ['shape' => '__new'])->assertSessionHasErrors('new_shape');
        $this->post('/masters/moulds', $base + ['shape' => 'nonsense'])->assertSessionHasErrors('shape');

        $this->post('/masters/moulds', $base + ['shape' => '__new', 'new_shape' => '  hexagonal  '])->assertSessionHasNoErrors();
        $mould = Mould::first();
        $this->assertSame('hexagonal', $mould->shape);
        $this->assertSame('Hexagonal', $mould->shapeLabel());
        $this->assertSame('Hexagonal Blow Mould – 1 Cav', $mould->name);

        // Typing an existing shape reuses it; the new shape is offered everywhere.
        $this->post('/masters/moulds', $base + ['shape' => '__new', 'new_shape' => 'HEXAGONAL', 'cavities' => 2])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\Shape::where('key', 'hexagonal')->count());
        $this->get('/masters/products/create')->assertSee('<option value="hexagonal"', false);
        $this->get('/masters/moulds/create')->assertSee('Bottle / jar volume')->assertSee('Add new shape');
    }

    public function test_machine_name_is_generated_and_unique(): void
    {
        $this->actingAs($this->superAdmin());
        $specs = ['machine_type' => 'preform', 'make' => 'Husky', 'model_no' => 'HyPET 90', 'tonnage' => 160, 'cavities' => 8, 'status' => 'active'];

        $this->post('/masters/machines', $specs + ['name' => 'ignored', 'capacity_per_hour' => 500])->assertSessionHasNoErrors();
        $this->post('/masters/machines', $specs)->assertSessionHasNoErrors();
        [$first, $second] = Machine::orderBy('id')->get();

        $this->assertSame('Preform Machine – 160 T – Husky HyPET 90 – 8 Cav', $first->name);
        $this->assertSame('Preform Machine – 160 T – Husky HyPET 90 – 8 Cav #2', $second->name);
        $this->put("/masters/machines/{$second->id}", ['tonnage' => 200] + $specs)->assertSessionHasNoErrors();
        $this->assertSame('Preform Machine – 200 T – Husky HyPET 90 – 8 Cav', $second->fresh()->name);

        $this->get("/masters/machines/{$first->id}")->assertOk()->assertSee('160 tonnes')->assertDontSee('pcs / hour');
        $this->get('/masters/machines/create')->assertOk()->assertSee('Machine tonnage')->assertDontSee('Output (pieces / hour)');
    }
}

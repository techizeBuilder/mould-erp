<?php

namespace Database\Seeders;

use App\Models\Machine;
use App\Models\Mould;
use App\Models\Neck;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the Machine, Mould and Product masters (PET/HDPE bottles & jars).
 *
 * WARNING: deletes ALL existing machines, moulds and products first.
 * Not called by DatabaseSeeder — run it on purpose:
 *     php artisan db:seed --class=DemoMastersSeeder
 */
class DemoMastersSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->wipe();

            $createdBy = User::where('is_super_admin', true)->value('id');
            $m = fn (array $attrs) => tap(new Machine($attrs), fn ($x) => $x->forceFill(['created_by' => $createdBy])->save());
            // Necks come from the values in each record, each distinct one getting its serial number.
            $neck = fn (array $attrs) => Neck::findOrCreateBySpec($attrs + ['neck_type' => null], $createdBy)->id;
            $d = fn (array $attrs) => tap(new Mould(['neck_id' => $neck($attrs)] + $attrs), fn ($x) => $x->forceFill(['created_by' => $createdBy])->save());
            $p = fn (array $attrs) => tap(new Product(empty($attrs['mould_id']) ? ['neck_id' => $neck($attrs)] + $attrs : array_diff_key($attrs, array_flip(['neck_size_mm', 'neck_type', 'neck_height_mm']))), fn ($x) => $x->applyMouldSpecs()->forceFill(['created_by' => $createdBy])->save());

            // ---- Machines -------------------------------------------------
            // Machine names are generated from the specs (Machine::baseName).
            $preform1 = $m(['machine_type' => 'preform', 'make' => 'Haitian', 'model_no' => 'MA2500/1000',
                'tonnage' => 250, 'cavities' => 16, 'location' => 'Injection Shop – Bay 1', 'status' => 'active',
                'remarks' => 'Main line for 28 mm preforms.']);
            $preform2 = $m(['machine_type' => 'preform', 'make' => 'Husky', 'model_no' => 'HyPET 90',
                'tonnage' => 160, 'cavities' => 8, 'location' => 'Injection Shop – Bay 2', 'status' => 'active',
                'remarks' => 'Heavy preforms: wide-neck jars and 20 L jars.']);
            $blow1 = $m(['machine_type' => 'blow', 'make' => 'Chumpower', 'model_no' => 'CPSB-LS2',
                'cavities' => 2, 'min_capacity_ml' => 200, 'max_capacity_ml' => 2000,
                'location' => 'Blow Shop – Line A', 'status' => 'active']);
            $blow2 = $m(['machine_type' => 'blow', 'make' => 'Chumpower', 'model_no' => 'CPSB-LS4',
                'cavities' => 4, 'min_capacity_ml' => 20, 'max_capacity_ml' => 500,
                'location' => 'Blow Shop – Line B', 'status' => 'active']);
            $blow3 = $m(['machine_type' => 'blow', 'make' => 'Shree Krishna Engg.', 'model_no' => 'SA-5L',
                'cavities' => 1, 'min_capacity_ml' => 1000, 'max_capacity_ml' => 5000,
                'location' => 'Blow Shop – Line C', 'status' => 'active']);
            $blow4 = $m(['machine_type' => 'blow', 'make' => 'Shree Krishna Engg.', 'model_no' => 'SA-20L',
                'cavities' => 1, 'min_capacity_ml' => 5000, 'max_capacity_ml' => 20000,
                'location' => 'Blow Shop – Line D', 'status' => 'maintenance', 'remarks' => 'Heater bank replacement in progress.']);
            $m(['machine_type' => 'other', 'make' => 'Local', 'model_no' => 'Neck Trimmer',
                'location' => 'Finishing Area', 'status' => 'inactive', 'remarks' => 'Neck trimming unit. Spare; not in regular use.']);

            // Mould names are generated from the specs (Mould::generateName).
            // ---- Preform moulds (stage 1) ---------------------------------
            $pf20 = $d(['weight_grams' => 4, 'mould_type' => 'preform', 'neck_size_mm' => 20, 'cavities' => 16, 'location' => 'Mould Rack A-1', 'status' => 'active']);
            $pf28s = $d(['neck_type' => 'PCO 1881', 'weight_grams' => 18, 'mould_type' => 'preform', 'neck_size_mm' => 28, 'cavities' => 16, 'location' => 'Mould Rack A-2', 'status' => 'active']);
            $pf28m = $d(['neck_type' => 'PCO 1881', 'weight_grams' => 32, 'mould_type' => 'preform', 'neck_size_mm' => 28, 'cavities' => 16, 'location' => 'Mould Rack A-3', 'status' => 'active']);
            $pf28l = $d(['neck_type' => 'PCO 1881', 'weight_grams' => 45, 'mould_type' => 'preform', 'neck_size_mm' => 28, 'cavities' => 8, 'location' => 'Mould Rack A-4', 'status' => 'active']);
            $pf53 = $d(['neck_type' => 'Wide mouth', 'weight_grams' => 55, 'mould_type' => 'preform', 'neck_size_mm' => 53, 'cavities' => 8, 'location' => 'Mould Rack B-1', 'status' => 'active']);
            $pf55 = $d(['weight_grams' => 700, 'mould_type' => 'preform', 'neck_size_mm' => 55, 'cavities' => 1, 'location' => 'Mould Rack B-2', 'status' => 'active']);

            // ---- Blow moulds (stage 2) ------------------------------------
            $bm50 = $d(['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 50, 'neck_size_mm' => 20, 'cavities' => 4, 'location' => 'Mould Rack C-1', 'status' => 'active']);
            $bm250 = $d(['mould_type' => 'blow', 'shape' => 'oval', 'capacity_ml' => 250, 'neck_size_mm' => 28, 'cavities' => 4, 'location' => 'Mould Rack C-2', 'status' => 'maintenance', 'remarks' => 'Vent cleaning due.']);
            $bm500 = $d(['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 500, 'neck_size_mm' => 28, 'cavities' => 2, 'location' => 'Mould Rack C-3', 'status' => 'active']);
            $bm1r = $d(['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 1000, 'neck_size_mm' => 28, 'cavities' => 2, 'location' => 'Mould Rack C-4', 'status' => 'active']);
            $bm1s = $d(['mould_type' => 'blow', 'shape' => 'square', 'capacity_ml' => 1000, 'neck_size_mm' => 28, 'cavities' => 2, 'location' => 'Mould Rack C-5', 'status' => 'active']);
            $bm2 = $d(['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 2000, 'neck_size_mm' => 28, 'cavities' => 2, 'location' => 'Mould Rack C-6', 'status' => 'active']);
            $bmJar = $d(['neck_type' => 'Wide mouth', 'mould_type' => 'blow', 'shape' => 'square', 'capacity_ml' => 1000, 'neck_size_mm' => 53, 'cavities' => 1, 'location' => 'Mould Rack D-1', 'status' => 'active']);
            $bm20 = $d(['mould_type' => 'blow', 'shape' => 'round', 'capacity_ml' => 20000, 'neck_size_mm' => 55, 'cavities' => 1, 'location' => 'Mould Rack D-2', 'status' => 'active']);
            $d(['mould_type' => 'blow', 'shape' => 'square', 'capacity_ml' => 750, 'neck_size_mm' => 28, 'cavities' => 2, 'location' => 'Store Room', 'status' => 'inactive', 'remarks' => 'Old design – discontinued by customer.']);

            // ---- Machine ↔ mould compatibility ----------------------------
            $preform1->moulds()->sync([$pf20->id, $pf28s->id, $pf28m->id, $pf28l->id]);
            $preform2->moulds()->sync([$pf28l->id, $pf53->id, $pf55->id]);
            $blow1->moulds()->sync([$bm250->id, $bm500->id, $bm1r->id, $bm1s->id, $bm2->id]);
            $blow2->moulds()->sync([$bm50->id, $bm250->id, $bm500->id]);
            $blow3->moulds()->sync([$bmJar->id]);
            $blow4->moulds()->sync([$bm20->id]);

            // ---- Products -------------------------------------------------
            $bottle = ['product_type' => 'bottle', 'business_type' => 'manufacturing', 'material' => 'PET', 'hsn_code' => '39233090', 'status' => 'active'];

            $p($bottle + ['bill_name' => '50 ml PET Round Bottle – Clear', 'capacity_ml' => 50, 'shape' => 'round', 'neck_size_mm' => 20, 'weight_grams' => 4, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 1000, 'mould_id' => $bm50->id]);
            $p(['business_type' => 'both'] + $bottle + ['bill_name' => '250 ml PET Oval Bottle – Clear', 'capacity_ml' => 250, 'shape' => 'oval', 'neck_size_mm' => 28, 'weight_grams' => 18, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 400,
                'remarks' => 'Also bought from supplier when the oval mould is under maintenance.', 'mould_id' => $bm250->id]);
            $p($bottle + ['bill_name' => '500 ml PET Round Bottle – Clear', 'capacity_ml' => 500, 'shape' => 'round', 'neck_size_mm' => 28, 'weight_grams' => 18, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 200, 'mould_id' => $bm500->id]);
            $p($bottle + ['bill_name' => '1 L PET Round Bottle – Clear', 'capacity_ml' => 1000, 'shape' => 'round', 'neck_size_mm' => 28, 'weight_grams' => 32, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 100, 'mould_id' => $bm1r->id]);
            $p($bottle + ['bill_name' => '1 L PET Square Bottle – Clear', 'capacity_ml' => 1000, 'shape' => 'square', 'neck_size_mm' => 28, 'weight_grams' => 32, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 100, 'mould_id' => $bm1s->id]);
            $p($bottle + ['bill_name' => '1 L PET Square Bottle – Amber', 'capacity_ml' => 1000, 'shape' => 'square', 'neck_size_mm' => 28, 'weight_grams' => 32, 'colour' => 'Amber', 'unit' => 'carton', 'pieces_per_pack' => 100,
                'remarks' => 'Colour variant of the clear 1 L square bottle.', 'mould_id' => $bm1s->id]);
            $p($bottle + ['bill_name' => '2 L PET Round Bottle – Clear', 'capacity_ml' => 2000, 'shape' => 'round', 'neck_size_mm' => 28, 'weight_grams' => 45, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 60, 'mould_id' => $bm2->id]);
            $p(['product_type' => 'jar'] + $bottle + ['bill_name' => '1 L PET Square Jar 53 mm – Clear', 'capacity_ml' => 1000, 'shape' => 'square', 'neck_size_mm' => 53, 'weight_grams' => 55, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 80, 'mould_id' => $bmJar->id]);
            $p(['product_type' => 'jar'] + $bottle + ['bill_name' => '20 L PET Water Jar – Blue Tint', 'capacity_ml' => 20000, 'shape' => 'round', 'neck_size_mm' => 55, 'weight_grams' => 700, 'colour' => 'Blue tint', 'unit' => 'pcs', 'mould_id' => $bm20->id]);
            $p(['product_type' => 'preform', 'business_type' => 'both'] + $bottle + ['bill_name' => '28 mm PCO 18 g Preform – Clear', 'neck_size_mm' => 28, 'weight_grams' => 18, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 3000,
                'remarks' => 'Used in-house and sold to other blowers.', 'mould_id' => $pf28s->id]);

            // Made for a client on our mould, sold under the client's name.
            $p($bottle + ['owner_type' => 'client', 'client_name' => 'Aqua Fresh Beverages', 'bill_name' => 'Aqua Fresh 500 ml Bottle',
                'weight_grams' => 18, 'colour' => 'Light Blue', 'unit' => 'carton', 'pieces_per_pack' => 200, 'mould_id' => $bm500->id]);

            // Trading only — bought in finished, no moulds.
            $p(['bill_name' => '5 L HDPE Jerry Can – White', 'product_type' => 'other', 'business_type' => 'trading', 'capacity_ml' => 5000, 'shape' => 'rectangular',
                'neck_size_mm' => 50, 'weight_grams' => 180, 'material' => 'HDPE', 'colour' => 'White', 'unit' => 'pcs', 'hsn_code' => '39233090', 'status' => 'active',
                'remarks' => 'Purchased from supplier and resold.']);
            $p(['bill_name' => '200 ml PP Round Jar – Natural', 'product_type' => 'jar', 'business_type' => 'trading', 'capacity_ml' => 200, 'shape' => 'round',
                'neck_size_mm' => 70, 'weight_grams' => 22, 'material' => 'PP', 'colour' => 'Natural', 'unit' => 'pack', 'pieces_per_pack' => 50, 'hsn_code' => '39233090', 'status' => 'active']);
            $p($bottle + ['bill_name' => '750 ml PET Square Bottle – Clear', 'capacity_ml' => 750, 'shape' => 'square', 'neck_size_mm' => 28, 'weight_grams' => 26, 'colour' => 'Clear', 'unit' => 'carton', 'pieces_per_pack' => 150,
                'status' => 'inactive', 'remarks' => 'Discontinued.']);
        });
    }

    /** Removes all master records (through the models, so uploaded images are deleted too). */
    protected function wipe(): void
    {
        Product::all()->each->delete();
        Mould::all()->each->delete();
        Machine::all()->each->delete();
        Neck::query()->delete();
    }
}

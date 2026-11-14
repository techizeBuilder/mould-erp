<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Masters\Concerns\HandlesMasterForms;
use App\Models\Mould;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use HandlesMasterForms;

    public function index(Request $request)
    {
        $products = Product::with('mould:id,code', 'neck:id,code')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term)->orWhere('bill_name', 'like', $term)
                    ->orWhere('client_name', 'like', $term)->orWhere('colour', 'like', $term)->orWhere('hsn_code', 'like', $term));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('product_type', $request->type))
            ->when($request->filled('business'), fn ($q) => $q->where('business_type', $request->business))
            ->when($request->filled('owner'), fn ($q) => $q->where('owner_type', $request->owner))
            ->when($request->filled('material'), fn ($q) => $q->where('material', $request->material))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('masters.products.index', ['products' => $products]);
    }

    public function show(Product $product)
    {
        $product->load(['mould.machines' => fn ($q) => $q->orderBy('code'), 'neck', 'basedOn:id,code,name', 'creator:id,name']);

        return view('masters.products.show', ['product' => $product]);
    }

    /** ?from=ID pre-fills the form from an existing product, to add a variant quickly. */
    public function create(Request $request)
    {
        $source = $request->filled('from') ? Product::find($request->from) : null;

        $product = $source
            ? $source->replicate(['code', 'name', 'image_path', 'created_by', 'based_on_id'])->fill(['bill_name' => $source->bill_name.' (variant)'])
            : new Product(['owner_type' => 'company', 'product_type' => 'bottle', 'business_type' => 'manufacturing', 'unit' => 'pcs', 'status' => 'active']);

        return view('masters.products.form', [
            'product' => $product,
            'nextCode' => Product::nextCode(),
            'moulds' => $this->mouldOptions(),
            'source' => $source,
        ]);
    }

    public function store(Request $request)
    {
        $product = new Product($this->validated($request));
        $product->created_by = $request->user()->id;
        $this->checkMould($product);
        $this->refuseTwin($product);
        $this->syncImage($request, $product, 'products');
        $product->save();

        return redirect()->route('masters.products.show', $product)->with('success', "Product \"{$product->code} – {$product->name}\" created.");
    }

    public function edit(Product $product)
    {
        return view('masters.products.form', [
            'product' => $product,
            'nextCode' => null,
            'moulds' => $this->mouldOptions(),
            'source' => null,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $product->fill($this->validated($request));
        $this->checkMould($product);
        $product->syncSpecs();

        if ($product->identityChanged()) {
            return $this->saveAsNewProduct($request, $product);
        }

        $this->refuseTwin($product);
        $this->syncImage($request, $product, 'products');
        $product->save();

        return redirect()->route('masters.products.show', $product)->with('success', "Product \"{$product->code}\" updated.");
    }

    /**
     * A different neck or weight is a different product: the edits become a new product with its
     * own serial number, and the original stays exactly as it was (it's never saved here).
     */
    protected function saveAsNewProduct(Request $request, Product $original): \Illuminate\Http\RedirectResponse
    {
        $new = $original->replicate(['code', 'name', 'image_path', 'created_at', 'updated_at']);
        $new->based_on_id = $original->id;
        $new->created_by = $request->user()->id;
        $this->refuseTwin($new);

        DB::transaction(function () use ($request, $original, $new) {
            $oldImage = $original->getOriginal('image_path');

            if ($request->hasFile('image') || ! $oldImage || $request->boolean('remove_image')) {
                $this->syncImage($request, $new, 'products');
            } elseif (Storage::disk('public')->exists($oldImage)) {
                $copy = 'masters/products/'.uniqid('copy_').'.'.pathinfo($oldImage, PATHINFO_EXTENSION);
                Storage::disk('public')->copy($oldImage, $copy);
                $new->image_path = $copy;
            }

            $new->save();
        });

        $code = $original->getOriginal('code');

        return redirect()->route('masters.products.show', $new)
            ->with('success', "The neck or weight changed, so this is a new product: {$new->code}. {$code} stays as it was.");
    }

    /** One set of specs has one serial number. */
    protected function refuseTwin(Product $product): void
    {
        if ($twin = $product->twin()) {
            $this->fail('weight_grams', "A product with exactly these specs already exists: {$twin->code} – {$twin->name}.");
        }
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('masters.products.index')->with('success', "Product \"{$product->code}\" deleted.");
    }

    protected function mouldOptions()
    {
        return Mould::with('neck:id,code')->orderBy('mould_type')->orderBy('code')->get();
    }

    /**
     * A manufactured product is made with exactly one mould, of a type that suits the
     * product, and takes the mould's specs. Trading products are bought in, so have none.
     */
    protected function checkMould(Product $product): void
    {
        if (! $product->isManufactured()) {
            $product->mould_id = null;

            return;
        }

        $mould = Mould::find($product->mould_id);
        if (! $mould) {
            $this->fail('mould_id', 'Select the mould this product is made with.');
        }
        if (! in_array($mould->mould_type, Product::MOULD_TYPES_FOR[$product->product_type] ?? [], true)) {
            $this->fail('mould_id', 'A '.strtolower($product->typeLabel()).' cannot be made with a '.strtolower($mould->typeLabel())
                .'. Bottles and jars use a blow mould; preforms use a preform mould.');
        }

        $product->setRelation('mould', $mould)->applyMouldSpecs($mould);
    }

    protected function validated(Request $request): array
    {
        $request->validate([
            'bill_name' => ['required', 'string', 'max:255'],
            'owner_type' => ['required', Rule::in(array_keys(Product::OWNER_TYPES))],
            'client_name' => ['nullable', 'required_if:owner_type,client', 'string', 'max:150'],
            'product_type' => ['required', Rule::in(array_keys(Product::TYPES))],
            'business_type' => ['required', Rule::in(array_keys(Product::BUSINESS_TYPES))],
            'mould_id' => ['nullable', 'integer', 'exists:moulds,id'],
            ...$this->capacityRules('capacity'),
            ...$this->shapeRules(),
            'weight_grams' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'material' => ['nullable', Rule::in(Product::MATERIALS)],
            'colour' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'pieces_per_pack' => ['nullable', 'required_if:unit,pack,carton', 'integer', 'min:1', 'max:1000000'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'remarks' => ['nullable', 'string', 'max:2000'],
            ...$this->imageRules(),
        ], [
            'pieces_per_pack.required_if' => 'Enter how many pieces are in one pack or carton.',
            'client_name.required_if' => 'Enter the client this product belongs to.',
        ], [
            'bill_name' => 'name on bill',
            'owner_type' => 'product owner',
            'client_name' => 'client name',
            'product_type' => 'product type',
            'business_type' => 'business type',
            'mould_id' => 'mould',
            'capacity_value' => 'volume',
            'weight_grams' => 'weight',
            'new_shape' => 'new shape',
        ]);

        // The system name isn't entered: Product generates it from the specs when saved.
        return $request->only(['bill_name', 'owner_type', 'product_type', 'business_type', 'mould_id',
            'material', 'colour', 'unit', 'hsn_code', 'status', 'remarks'])
            + [
                'client_name' => $request->input('owner_type') === 'client' ? $request->input('client_name') : null,
                'neck_id' => $this->neckFromRequest($request),
                'neck_size_mm' => null,
                'neck_height_mm' => null,
                'neck_type' => null,
                'weight_grams' => $request->input('weight_grams') ?: null,
                'capacity_ml' => $this->capacityFromRequest($request, 'capacity'),
                'shape' => $this->shapeFromRequest($request),
                'pieces_per_pack' => $request->input('unit') === 'pcs' ? null : $request->input('pieces_per_pack'),
            ];
    }
}

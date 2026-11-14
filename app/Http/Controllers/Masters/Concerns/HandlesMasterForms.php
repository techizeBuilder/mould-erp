<?php

namespace App\Http\Controllers\Masters\Concerns;

use App\Models\Neck;
use App\Models\Shape;
use App\Support\Capacity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait HandlesMasterForms
{
    /** Rules for a capacity entered as value + ml/L unit. */
    protected function capacityRules(string $field, bool $required = false): array
    {
        return [
            "{$field}_value" => [$required ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:100000'],
            "{$field}_unit" => ['required_with:'.$field.'_value', 'nullable', 'in:ml,l'],
        ];
    }

    protected function capacityFromRequest(Request $request, string $field): ?float
    {
        return Capacity::toMl($request->input("{$field}_value"), $request->input("{$field}_unit"));
    }

    /** Shape is picked from the list, or "__new" with a typed new_shape that gets added to the list. */
    protected function shapeRules(): array
    {
        return [
            'shape' => ['nullable', Rule::in([...array_keys(Shape::options()), Shape::NEW])],
            'new_shape' => ['nullable', 'required_if:shape,'.Shape::NEW, 'string', 'max:50', 'regex:/[A-Za-z0-9]/'],
        ];
    }

    protected function shapeFromRequest(Request $request): ?string
    {
        $shape = $request->input('shape') ?: null;

        return $shape === Shape::NEW ? Shape::findOrCreateByName($request->input('new_shape')) : $shape;
    }

    /** The parameters of a neck; $prefix is "new_" when typed on a mould or product form. */
    protected function neckSpecRules(string $prefix = ''): array
    {
        $number = ['nullable', 'numeric', 'gt:0', 'max:500'];

        return [
            $prefix.'neck_size_mm' => ['required', 'numeric', 'gt:0', 'max:500'],
            $prefix.'neck_type' => ['nullable', 'string', 'max:50'],
            $prefix.'neck_height_mm' => $number,
            $prefix.'inner_dia_mm' => $number,
            $prefix.'outer_dia_mm' => $number,
        ];
    }

    protected function neckSpecAttributes(string $prefix = ''): array
    {
        return [
            $prefix.'neck_size_mm' => 'neck size',
            $prefix.'neck_type' => 'neck type',
            $prefix.'neck_height_mm' => 'neck height',
            $prefix.'inner_dia_mm' => 'inner diameter',
            $prefix.'outer_dia_mm' => 'outer diameter',
        ];
    }

    /** Validates the neck picker; returns the neck id, adding the typed neck to Neck Master if it is new. */
    protected function neckFromRequest(Request $request): ?int
    {
        if ($request->input('neck_id') !== Neck::NEW) {
            $request->validate(['neck_id' => ['nullable', 'integer', 'exists:necks,id']], [], ['neck_id' => 'neck']);

            return $request->input('neck_id') ? $request->integer('neck_id') : null;
        }

        abort_unless($request->user()->canModule('neck_master', 'create'), 403);

        $data = $request->validate($this->neckSpecRules('new_'), [], $this->neckSpecAttributes('new_'));
        $spec = [];
        foreach (Neck::SPEC_FIELDS as $field) {
            $spec[$field] = $data["new_$field"] ?? null;
        }

        return Neck::findOrCreateBySpec($spec, $request->user()->id)->id;
    }

    protected function imageRules(): array
    {
        return [
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /** Stores a newly uploaded image (replacing the old one) or removes it on request. */
    protected function syncImage(Request $request, Model $model, string $folder): void
    {
        $old = $model->image_path;

        if ($request->hasFile('image')) {
            $model->image_path = $request->file('image')->store("masters/$folder", 'public');
        } elseif ($request->boolean('remove_image')) {
            $model->image_path = null;
        }

        if ($old && $old !== $model->image_path) {
            Storage::disk('public')->delete($old);
        }
    }

    /** IDs from a multi-select, de-duplicated. */
    protected function ids(Request $request, string $field): array
    {
        return array_values(array_unique(array_map('intval', $request->input($field, []))));
    }

    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

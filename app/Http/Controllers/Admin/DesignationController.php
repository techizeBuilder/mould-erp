<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DesignationController extends Controller
{
    public function index()
    {
        return view('admin.designations.index', [
            'designations' => Designation::withCount('users')->orderBy('name')->get(),
            'editing' => null,
        ]);
    }

    public function store(Request $request)
    {
        $designation = Designation::create($this->validated($request));

        return redirect()->route('admin.designations.index')->with('success', "Designation \"{$designation->name}\" created.");
    }

    public function edit(Designation $designation)
    {
        return view('admin.designations.index', [
            'designations' => Designation::withCount('users')->orderBy('name')->get(),
            'editing' => $designation,
        ]);
    }

    public function update(Request $request, Designation $designation)
    {
        $designation->update($this->validated($request, $designation));

        return redirect()->route('admin.designations.index')->with('success', "Designation \"{$designation->name}\" updated.");
    }

    public function destroy(Designation $designation)
    {
        if ($designation->users()->exists()) {
            return back()->with('error', "\"{$designation->name}\" is assigned to sub-admins. Re-assign them before deleting it.");
        }

        $designation->delete();

        return redirect()->route('admin.designations.index')->with('success', "Designation \"{$designation->name}\" deleted.");
    }

    protected function validated(Request $request, ?Designation $designation = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('designations')->ignore($designation?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

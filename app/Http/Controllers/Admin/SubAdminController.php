<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SubAdminController extends Controller
{
    public function index(Request $request)
    {
        $users = User::subAdmins()
            ->with('designation')
            ->withCount(['permissions as modules_count' => fn ($q) => $q->where('can_show', true)])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('designation'), fn ($q) => $q->where('designation_id', $request->designation))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.create', [
            'user' => new User(['is_active' => true]),
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Created through the fillable list only, so is_super_admin always stays false.
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);

            // New sub-admins start with every module switched on (everything except Delete);
            // the super admin turns off what they shouldn't have.
            foreach (array_keys(config('modules')) as $module) {
                $user->permissions()->create(['module' => $module] + UserPermission::DEFAULT_ACCESS);
            }

            return $user;
        });

        return redirect()->route('admin.users.permissions.edit', $user)
            ->with('success', "Sub-admin \"{$user->name}\" created with access to all modules (Delete is off). Turn off any module they should not use.");
    }

    public function edit(User $user)
    {
        $this->guardSuperAdmin($user);

        return view('admin.users.edit', [
            'user' => $user,
            'designations' => Designation::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->guardSuperAdmin($user);

        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', "Sub-admin \"{$user->name}\" updated successfully.");
    }

    public function toggleStatus(User $user)
    {
        $this->guardSuperAdmin($user);

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', "\"{$user->name}\" is now ".($user->is_active ? 'active' : 'inactive').'.');
    }

    public function destroy(User $user)
    {
        $this->guardSuperAdmin($user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "Sub-admin \"{$user->name}\" deleted.");
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation_id' => ['required', 'exists:designations,id'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'designation_id.required' => 'Please select a designation.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** The super admin account is never managed from the sub-admin screens. */
    protected function guardSuperAdmin(User $user): void
    {
        abort_if($user->isSuperAdmin(), 403, 'The Super Admin account cannot be managed here.');
    }
}

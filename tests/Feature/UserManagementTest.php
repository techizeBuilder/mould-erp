<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        $user = User::factory()->create(['email' => 'boss@erp.test']);
        $user->forceFill(['is_super_admin' => true])->save();

        return $user;
    }

    protected function subAdmin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['designation_id' => Designation::create(['name' => 'Accountant'])->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_login_and_logout(): void
    {
        $this->superAdmin();

        $this->post('/login', ['email' => 'boss@erp.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'boss@erp.test', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->get('/dashboard')->assertOk()->assertSee('User Management')->assertSee('Total Sub-Admins');

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_sub_admin_cannot_login(): void
    {
        $this->subAdmin(['email' => 'off@erp.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@erp.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_super_admin_creates_sub_admin_with_custom_designation(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post('/admin/designations', ['name' => 'Sales Head'])->assertRedirect('/admin/designations');
        $designation = Designation::where('name', 'Sales Head')->firstOrFail();

        $response = $this->post('/admin/users', [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@erp.test',
            'designation_id' => $designation->id,
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'is_active' => '1',
            'is_super_admin' => '1', // must be ignored
        ]);

        $ravi = User::where('email', 'ravi@erp.test')->firstOrFail();
        $response->assertRedirect("/admin/users/{$ravi->id}/permissions");
        $this->assertFalse($ravi->is_super_admin);
        $this->assertSame('Sales Head', $ravi->roleLabel());

        // Every module starts switched on with everything except Delete.
        foreach (array_keys(config('modules')) as $module) {
            foreach (['show', 'view', 'create', 'edit'] as $action) {
                $this->assertTrue($ravi->canModule($module, $action), "$module.$action should default on");
            }
            $this->assertFalse($ravi->canModule($module, 'delete'), "$module.delete should default off");
        }

        // The super admin can then switch modules off one by one.
        $kept = collect(config('modules'))->except('reports')
            ->map(fn () => ['show' => 1, 'view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 1])->all();
        $this->put("/admin/users/{$ravi->id}/permissions", ['permissions' => $kept])->assertRedirect();
        $ravi = $ravi->fresh();
        $this->assertFalse($ravi->canModule('reports', 'show'));
        $this->assertTrue($ravi->canModule('sales_leads', 'delete'));

        $this->get('/admin/users')->assertOk()->assertSee('Ravi Kumar');
        $this->get("/admin/users/{$ravi->id}/permissions")->assertOk()->assertSee('Module access');
    }

    public function test_sub_admin_cannot_reach_user_management(): void
    {
        $this->actingAs($this->subAdmin());

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/designations')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertDontSee('User Management')->assertSee('No modules assigned yet');
    }

    public function test_permissions_control_module_visibility_and_crud(): void
    {
        $admin = $this->superAdmin();
        $sub = $this->subAdmin();

        $this->actingAs($admin)->put("/admin/users/{$sub->id}/permissions", [
            'permissions' => [
                'reports' => ['show' => 1, 'edit' => 1],        // edit implies view
                'sales_leads' => ['create' => 1],                       // implies show + view
                'purchase' => ['view' => 1, 'delete' => 1],       // no show: still granted because view implies show
                'inventory' => ['show' => 1],                     // visible, no CRUD
            ],
        ])->assertRedirect();

        $sub->refresh();
        $this->assertTrue($sub->canModule('reports', 'view'));
        $this->assertTrue($sub->canModule('reports', 'edit'));
        $this->assertFalse($sub->canModule('reports', 'delete'));
        $this->assertTrue($sub->canModule('sales_leads', 'show'));
        $this->assertTrue($sub->canModule('sales_leads', 'create'));
        $this->assertTrue($sub->canModule('inventory', 'show'));
        $this->assertFalse($sub->canModule('inventory', 'view'));
        $this->assertFalse($sub->canModule('hr_payroll', 'show'));

        $this->actingAs($sub)->get('/dashboard')->assertOk()->assertSee('Reports')->assertDontSee('HR &amp; Payroll', false);
        $this->get('/modules/reports')->assertOk();
        $this->get('/hr/payroll')->assertForbidden();
        $this->get('/modules/nope')->assertNotFound();

        // Hiding a module revokes every right on it.
        $this->actingAs($admin)->put("/admin/users/{$sub->id}/permissions", [
            'permissions' => ['sales_leads' => ['create' => 1]],
        ]);
        $sub = $sub->fresh();
        $this->assertFalse($sub->canModule('reports', 'edit'));
        $this->assertTrue($sub->canModule('sales_leads', 'create'));
    }

    public function test_super_admin_account_is_protected(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $this->get("/admin/users/{$admin->id}/edit")->assertForbidden();
        $this->delete("/admin/users/{$admin->id}")->assertForbidden();
        $this->get("/admin/users/{$admin->id}/permissions")->assertForbidden();
        $this->assertTrue($admin->canModule('anything', 'delete'));
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $sub = $this->subAdmin();
        $this->actingAs($sub)->get('/dashboard')->assertOk();

        $sub->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_every_admin_page_renders(): void
    {
        $sub = $this->subAdmin();
        $this->actingAs($this->superAdmin());

        $this->get('/')->assertRedirect('/dashboard');
        foreach ([
            '/dashboard', '/profile', '/admin/users', '/admin/users/create', "/admin/users/{$sub->id}/edit",
            "/admin/users/{$sub->id}/permissions", "/admin/users/{$sub->id}/permissions?copy_from={$sub->id}",
            '/admin/designations', "/admin/designations/{$sub->designation_id}/edit", '/modules/quality',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_designation_in_use_cannot_be_deleted(): void
    {
        $sub = $this->subAdmin();
        $this->actingAs($this->superAdmin());

        $this->delete("/admin/designations/{$sub->designation_id}")->assertSessionHas('error');
        $this->assertDatabaseHas('designations', ['id' => $sub->designation_id]);
    }
}

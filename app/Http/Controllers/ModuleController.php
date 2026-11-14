<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Landing page for each ERP module. Real module screens will replace this
 * one by one; access is already enforced here and by the "module" middleware.
 */
class ModuleController extends Controller
{
    public function show(Request $request, string $module)
    {
        $config = config("modules.$module");
        abort_unless($config, 404);
        abort_unless($request->user()->canModule($module, 'show'), 403, 'This module is not available for your account.');

        if (isset($config['route'])) {
            return redirect()->route($config['route']);
        }

        return view('modules.show', ['key' => $module, 'module' => $config]);
    }
}

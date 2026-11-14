<?php

namespace App\Support;

/**
 * Helpers around config/modules.php.
 */
class Modules
{
    /** Sidebar / permission-matrix headings for grouped modules. */
    public const GROUPS = [
        'masters' => ['label' => 'Masters', 'icon' => 'database'],
        'sales' => ['label' => 'Sales & CRM', 'icon' => 'cart'],
        'orders' => ['label' => 'Order Management', 'icon' => 'document'],
        'accounts' => ['label' => 'Accounts', 'icon' => 'calculator'],
        'hr' => ['label' => 'HR & Payroll', 'icon' => 'users'],
    ];

    public static function url(string $key): string
    {
        $route = config("modules.$key.route");

        return $route ? route($route) : route('modules.show', $key);
    }

    /** The module the current request belongs to, for highlighting the sidebar. */
    public static function isActive(string $key): bool
    {
        $route = config("modules.$key.route");

        return $route
            ? request()->routeIs(preg_replace('/\.index$/', '.*', $route))
            : request()->is('modules/'.$key);
    }

    /**
     * Split modules into [groupKey => modules] with ungrouped ones under '',
     * keeping config order.
     */
    public static function grouped(array $modules): array
    {
        $grouped = [];
        foreach ($modules as $key => $module) {
            $grouped[$module['group'] ?? ''][$key] = $module;
        }

        return $grouped;
    }
}

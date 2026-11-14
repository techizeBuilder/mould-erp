<?php

/*
|--------------------------------------------------------------------------
| ERP Modules
|--------------------------------------------------------------------------
|
| Every module listed here shows up in the sub-admin permission matrix and
| the sidebar. Add a new module by adding a key here; access is then
| controlled per user with the "module:<key>,<action>" route middleware
| or the @module('<key>', '<action>') Blade directive.
|
| Actions: show, view, create, edit, delete
|
| Optional keys:
|   group — modules sharing a group are shown together under an expandable
|           sidebar heading (group labels live in App\Support\Modules).
|   route — named route the module opens; defaults to the placeholder page.
|
*/

return [
    'neck_master' => ['label' => 'Neck Master', 'icon' => 'tag', 'group' => 'masters', 'route' => 'masters.necks.index', 'description' => 'Neck finishes with size, type, height and diameters, each with its own serial number.'],
    'mould_master' => ['label' => 'Mould Master', 'icon' => 'cube', 'group' => 'masters', 'route' => 'masters.moulds.index', 'description' => 'Preform and blow moulds with capacity, neck size and compatible machines.'],
    'machine_master' => ['label' => 'Machine Master', 'icon' => 'cog', 'group' => 'masters', 'route' => 'masters.machines.index', 'description' => 'Preform and blow moulding machines with tonnage, size range and moulds.'],
    'product_master' => ['label' => 'Product Master', 'icon' => 'box', 'group' => 'masters', 'route' => 'masters.products.index', 'description' => 'Bottles, jars and preforms — manufactured or traded — each with its mould.'],

    // Sales & CRM — every screen is about the product a buyer wants, from enquiry to repeat order.
    'sales_leads' => ['label' => 'Leads', 'icon' => 'user-plus', 'group' => 'sales', 'route' => 'sales.leads.index', 'description' => 'Product enquiries with their source, specs, quantity and target price.'],
    'sales_qualification' => ['label' => 'Buyer Qualification', 'icon' => 'shield', 'group' => 'sales', 'route' => 'sales.qualification.index', 'description' => 'Score buyers on volume, price, mould fit, timeline and payment.'],
    'sales_quotations' => ['label' => 'Quotations', 'icon' => 'document', 'group' => 'sales', 'route' => 'sales.quotations.index', 'description' => 'Product quotations with revisions, samples, validity and win / loss tracking.'],
    'sales_followups' => ['label' => 'Follow-ups', 'icon' => 'clock', 'group' => 'sales', 'route' => 'sales.followups.index', 'description' => 'Scheduled calls, visits and reminders, with overdue tracking.'],
    'sales_retention' => ['label' => 'Customer Retention', 'icon' => 'refresh', 'group' => 'sales', 'route' => 'sales.retention.index', 'description' => 'Customers, repeat orders by product and reorder reminders.'],

    // Order Management — customer master, then sales orders, then their approval.
    'orders_customers' => ['label' => 'Customer Master', 'icon' => 'users', 'group' => 'orders', 'route' => 'orders.customers.index', 'description' => 'Customer details, addresses, GST, credit limit and payment terms.'],
    'orders_sales' => ['label' => 'Sales Orders', 'icon' => 'document', 'group' => 'orders', 'route' => 'orders.sales.index', 'description' => "Customers' orders for Product Master products, from entry to submission."],
    'orders_approval' => ['label' => 'Order Approval', 'icon' => 'shield', 'group' => 'orders', 'route' => 'orders.approval.index', 'description' => 'Review submitted orders with credit and product checks; approve or reject.'],

    // Accounts — double-entry books: every type of ledger, vouchers and the trial balance.
    'accounts_ledgers' => ['label' => 'Ledgers', 'icon' => 'document', 'group' => 'accounts', 'route' => 'accounts.ledgers.index', 'description' => 'All types of ledgers with opening balances and statements.'],
    'accounts_vouchers' => ['label' => 'Vouchers', 'icon' => 'banknotes', 'group' => 'accounts', 'route' => 'accounts.vouchers.index', 'description' => 'Receipts, payments, contra, sales, purchases and journals.'],
    'accounts_trial_balance' => ['label' => 'Trial Balance', 'icon' => 'chart', 'group' => 'accounts', 'route' => 'accounts.trial-balance.index', 'description' => 'Debit and credit balances of every ledger on any date.'],

    // HR & Payroll — employees are records only; they never get a login.
    'hr_employees' => ['label' => 'Employees', 'icon' => 'users', 'group' => 'hr', 'route' => 'hr.employees.index', 'description' => 'Employee records, salary structure, statutory and bank details.'],
    'hr_shifts' => ['label' => 'Shifts & Rotation', 'icon' => 'clock', 'group' => 'hr', 'route' => 'hr.shifts.index', 'description' => 'Shift timings, weekly roster, shift assignment and rotation.'],
    'hr_attendance' => ['label' => 'Attendance', 'icon' => 'calendar', 'group' => 'hr', 'route' => 'hr.attendance.index', 'description' => 'Daily attendance marking and the monthly muster roll.'],
    'hr_payroll' => ['label' => 'Payroll & Salary Slips', 'icon' => 'banknotes', 'group' => 'hr', 'route' => 'hr.payroll.index', 'description' => 'Monthly payroll from attendance, PF / ESI / PT deductions and salary slips.'],

    'purchase' => ['label' => 'Purchase', 'icon' => 'truck', 'description' => 'Purchase requests, orders and vendor management.'],
    'inventory' => ['label' => 'Inventory', 'icon' => 'box', 'description' => 'Stock, warehouses, inward and outward movements.'],
    'production' => ['label' => 'Production', 'icon' => 'cog', 'description' => 'Job cards, work orders and production planning.'],
    'quality' => ['label' => 'Quality Control', 'icon' => 'shield', 'description' => 'Inspections, checklists and QC approvals.'],
    // Not needed for now — uncomment to bring R&D back (existing rights rows are ignored while it is off).
    // 'rnd' => ['label' => 'R&D / Design', 'icon' => 'beaker', 'description' => 'Product master, mould design, BOM and prototypes.'],
    'reports' => ['label' => 'Reports', 'icon' => 'chart', 'description' => 'Business reports and analytics.'],
];

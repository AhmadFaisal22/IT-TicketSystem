<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $departments = [
            ['name' => 'IT',          'name_zh' => '信息技术部', 'description' => 'Information Technology'],
            ['name' => 'HR',          'name_zh' => '人力资源部', 'description' => 'Human Resources'],
            ['name' => 'Finance',     'name_zh' => '财务部',     'description' => 'Finance & Accounting'],
            ['name' => 'Operations',  'name_zh' => '运营部',     'description' => 'Operations'],
            ['name' => 'Sales',       'name_zh' => '销售部',     'description' => 'Sales & Marketing'],
            ['name' => 'Management',  'name_zh' => '管理层',     'description' => 'Senior Management'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['name' => $dept['name']], $dept);
        }

        $itDept = Department::where('name', 'IT')->first();

        // Global SLA policies (department_id = null = default for all)
        $slaPolicies = [
            ['department_id' => null, 'priority' => 'critical', 'response_hours' => 1,  'resolution_hours' => 4],
            ['department_id' => null, 'priority' => 'high',     'response_hours' => 4,  'resolution_hours' => 8],
            ['department_id' => null, 'priority' => 'medium',   'response_hours' => 8,  'resolution_hours' => 24],
            ['department_id' => null, 'priority' => 'low',      'response_hours' => 24, 'resolution_hours' => 72],
        ];

        foreach ($slaPolicies as $policy) {
            SlaPolicy::firstOrCreate(
                ['department_id' => $policy['department_id'], 'priority' => $policy['priority']],
                $policy
            );
        }

        // Admin user (password login for bootstrapping — OAuth will be used in production)
        User::firstOrCreate(
            ['email' => 'admin@helpdesk.local'],
            [
                'name'          => 'Admin User',
                'password'      => Hash::make('password'),
                'role'          => 'admin',
                'department_id' => $itDept?->id,
                'locale'        => 'en',
                'active'        => true,
            ]
        );

        // IT Staff user
        User::firstOrCreate(
            ['email' => 'staff@helpdesk.local'],
            [
                'name'          => 'IT Staff',
                'password'      => Hash::make('password'),
                'role'          => 'it_staff',
                'department_id' => $itDept?->id,
                'locale'        => 'en',
                'active'        => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Permissao;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Catálogo de permissões (AGENTS.md 6.2) — seguro para qualquer ambiente. */
class PermissoesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissao::cases() as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

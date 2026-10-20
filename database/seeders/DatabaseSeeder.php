<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissoesSeeder::class);

        // AGENTS.md seção 5.0 — dados de desenvolvimento só em local/testing
        if (app()->environment(['local', 'testing'])) {
            $this->call(DesenvolvimentoSeeder::class);
        }
    }
}

<?php

use Illuminate\Support\Facades\Schedule;

/*
| Rotinas agendadas — AGENTS.md seção 3.6.
| Executadas pelo "schedule:work" mantido pelo Supervisor no container app.
*/

Schedule::command('competencias:gerar-mensal')
    ->monthlyOn(1, '00:05')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('cobranca:documentacao-diaria')
    ->dailyAt('08:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onOneServer();

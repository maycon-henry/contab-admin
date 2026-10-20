<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** regras.md RN-004 — pedido de demonstração (plataforma, fora de qualquer escritório). */
class ContatoComercial extends Model
{
    protected $table = 'contatos_comerciais';

    protected $guarded = ['id'];
}

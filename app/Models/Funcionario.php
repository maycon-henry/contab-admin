<?php

namespace App\Models;

use App\Domain\DocumentoFiscal;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Funcionario extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'funcionarios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            // RN-513 / AGENTS.md seção 7 — dados bancários criptografados
            'banco' => 'encrypted',
            'agencia' => 'encrypted',
            'conta' => 'encrypted',
            'chave_pix' => 'encrypted',
            'pessoa_com_deficiencia' => 'boolean',
            'estrangeiro' => 'boolean',
        ];
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(Dependente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class)->latest('id');
    }

    public function contratoAtual(): HasOne
    {
        return $this->hasOne(Contrato::class)->latestOfMany();
    }

    public function getCpfFormatadoAttribute(): string
    {
        return DocumentoFiscal::formatarCpf($this->cpf);
    }
}

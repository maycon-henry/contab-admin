<?php

namespace App\Models;

use App\Domain\Competencia as Mes;
use App\Domain\DocumentoFiscal;
use App\Enums\RegimeTributario;
use App\Enums\SituacaoEmpresa;
use App\Enums\TipoUsuario;
use App\Models\Concerns\PertenceAoEscritorio;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Empresa extends Model implements HasName
{
    use PertenceAoEscritorio;

    protected $table = 'empresas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'regime_tributario' => RegimeTributario::class,
            'situacao' => SituacaoEmpresa::class,
            'data_inicio_atendimento' => 'date',
            'data_abertura' => 'date',
            'capital_social' => 'decimal:2',
            'indicativo_cooperativa' => 'boolean',
            'indicativo_construtora' => 'boolean',
            'indicativo_desoneracao' => 'boolean',
            'cobranca_suspensa_ate' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // RN-240 — usuário da empresa só enxerga as próprias empresas
        static::addGlobalScope('empresas_do_usuario', function (Builder $query) {
            if (Auth::hasUser() && Auth::user()->tipo === TipoUsuario::UsuarioEmpresa) {
                $query->whereIn($query->qualifyColumn('id'), Auth::user()->empresasIds());
            }
        });

        // RN-403 — histórico de regime tributário
        static::saved(function (Empresa $empresa) {
            if ($empresa->wasRecentlyCreated || $empresa->wasChanged('regime_tributario')) {
                $hoje = now()->toDateString();
                $empresa->regimes()->whereNull('vigencia_fim')->update(['vigencia_fim' => $hoje]);
                $empresa->regimes()->create([
                    'regime_tributario' => $empresa->regime_tributario,
                    'vigencia_inicio' => $empresa->wasRecentlyCreated ? $empresa->data_inicio_atendimento : $hoje,
                ]);
            }
        });
    }

    public function getFilamentName(): string
    {
        return $this->nome_fantasia ?: $this->razao_social;
    }

    public function getCnpjFormatadoAttribute(): string
    {
        return DocumentoFiscal::formatarCnpj($this->cnpj);
    }

    /** RN-1311 — data de corte própria ou a padrão do escritório */
    public function diaCorteCobranca(): int
    {
        return $this->dia_corte_cobranca ?? $this->escritorio->dia_corte_cobranca;
    }

    public function competenciaInicial(): Mes
    {
        return Mes::daData($this->data_inicio_atendimento);
    }

    public function regimes(): HasMany
    {
        return $this->hasMany(EmpresaRegime::class)->orderByDesc('vigencia_inicio');
    }

    public function socios(): HasMany
    {
        return $this->hasMany(Socio::class);
    }

    public function estabelecimentos(): HasMany
    {
        return $this->hasMany(Estabelecimento::class)->orderByDesc('matriz');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_usuario')->withTimestamps();
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class);
    }

    public function itensCompetencia(): HasMany
    {
        return $this->hasMany(ItemCompetencia::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    public function lotesImportacao(): HasMany
    {
        return $this->hasMany(LoteImportacaoXml::class);
    }

    public function ajustesRotina(): HasMany
    {
        return $this->hasMany(EmpresaRotinaAjuste::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(CobrancaDocumentacao::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}

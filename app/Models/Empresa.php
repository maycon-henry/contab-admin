<?php

namespace App\Models;

use App\Domain\Competencia as Mes;
use App\Domain\DocumentoFiscal;
use App\Enums\RegimeTributario;
use App\Enums\SituacaoEmpresa;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
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
            'honorario_valor' => 'decimal:2',
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

        // RN-1200/RN-1203 — honorário maior que zero, com dia de vencimento válido em todos os meses
        static::saving(function (Empresa $empresa) {
            if ($empresa->honorario_valor === null) {
                return;
            }

            RegraDeNegocioException::se((float) $empresa->honorario_valor <= 0, 'O valor do honorário deve ser maior que zero.');
            $dia = (int) $empresa->honorario_dia_vencimento;
            RegraDeNegocioException::se($dia < 1 || $dia > 28, 'Informe o dia de vencimento do honorário (1 a 28).');
        });

        // RN-1201 — histórico do honorário com vigência; o reajuste nunca sobrescreve o valor anterior
        static::saved(function (Empresa $empresa) {
            if (! ($empresa->wasRecentlyCreated || $empresa->wasChanged(['honorario_valor', 'honorario_dia_vencimento']))) {
                return;
            }

            $hoje = now()->toDateString();
            $vigente = $empresa->honorarios()->whereNull('vigencia_fim')->first();
            $novo = $empresa->honorario_valor === null ? null : [
                'valor' => $empresa->honorario_valor,
                'dia_vencimento' => $empresa->honorario_dia_vencimento,
                'registrado_por_id' => Auth::id(),
            ];

            // alterado de novo no mesmo dia: corrige a vigência de hoje em vez de abrir outra
            if ($vigente && $vigente->vigencia_inicio->toDateString() === $hoje) {
                $novo ? $vigente->update($novo) : $vigente->delete();

                return;
            }

            $vigente?->update(['vigencia_fim' => now()->subDay()->toDateString()]);

            if ($novo) {
                $empresa->honorarios()->create($novo + [
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

    public function honorarios(): HasMany
    {
        return $this->hasMany(EmpresaHonorario::class)->orderByDesc('vigencia_inicio');
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

<?php

namespace App\Models;

use App\Enums\GrupoDocumentoFuncionario;
use App\Enums\TipoUsuario;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Documento (arquivo) — regras.md seção 12 e RN-904. */
class Documento extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    public const DISCO = 'local';

    protected $table = 'documentos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visivel_empresa' => 'boolean',
            'substituido' => 'boolean',
            'validade' => 'date',
            'avaliado_em' => 'datetime',
            'tamanho' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // RN-568 — todo documento nasce ligado ao funcionário (quando é de um contrato) e com os dados do arquivo
        static::creating(function (Documento $documento) {
            if ($documento->funcionario_id === null && $documento->documentavel_type === (new Contrato)->getMorphClass()) {
                $documento->funcionario_id = Contrato::withoutGlobalScopes()->whereKey($documento->documentavel_id)->value('funcionario_id');
            }

            $disco = Storage::disk(self::DISCO);
            if ($documento->hash === null && filled($documento->arquivo) && $disco->exists($documento->arquivo)) {
                $documento->hash = hash('sha256', (string) $disco->get($documento->arquivo));
                $documento->mime = $disco->mimeType($documento->arquivo) ?: null;
                $documento->tamanho = $disco->size($documento->arquivo);
            }
        });

        // RN-904 — documentos internos nunca aparecem para a empresa
        static::addGlobalScope('visibilidade', function (Builder $query) {
            if (Auth::hasUser() && Auth::user()->tipo === TipoUsuario::UsuarioEmpresa) {
                $query->where($query->qualifyColumn('visivel_empresa'), true);
            }
        });
    }

    public function documentavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    /** RN-569 — Pessoais, Dependentes, Admissão, Desligamento. */
    public function grupo(): GrupoDocumentoFuncionario
    {
        return GrupoDocumentoFuncionario::doTipo($this->tipo);
    }

    public function rotulo(): string
    {
        return GrupoDocumentoFuncionario::rotuloDoTipo($this->tipo);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    /** RN-593 — quem do escritório aceitou ou recusou o documento. */
    public function avaliadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliado_por_id');
    }

    public function urlTemporaria(): string
    {
        return Storage::disk(self::DISCO)->temporaryUrl($this->arquivo, now()->addMinutes(10));
    }
}

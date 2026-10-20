<?php

namespace App\Models;

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
        ];
    }

    protected static function booted(): void
    {
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

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    public function urlTemporaria(): string
    {
        return Storage::disk(self::DISCO)->temporaryUrl($this->arquivo, now()->addMinutes(10));
    }
}

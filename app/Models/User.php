<?php

namespace App\Models;

use App\Enums\Permissao;
use App\Enums\SituacaoEmpresa;
use App\Enums\SituacaoEscritorio;
use App\Enums\TipoUsuario;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'escritorio_id', 'name', 'email', 'password', 'tipo', 'titular', 'ativo',
        'convite_token', 'convite_expira_em', 'convite_aceito_em', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'convite_token'];

    /** @var array<int>|null */
    private ?array $empresasIdsCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'convite_expira_em' => 'datetime',
            'convite_aceito_em' => 'datetime',
            'password' => 'hashed',
            'tipo' => TipoUsuario::class,
            'titular' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function escritorio(): BelongsTo
    {
        return $this->belongsTo(Escritorio::class);
    }

    /** RN-240 — empresas vinculadas ao usuário da empresa */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario')->withTimestamps();
    }

    /** @return array<int> */
    public function empresasIds(): array
    {
        return $this->empresasIdsCache ??= DB::table('empresa_usuario')
            ->where('user_id', $this->id)
            ->pluck('empresa_id')
            ->all();
    }

    public function scopeDaEquipe(Builder $query): Builder
    {
        return $query->whereIn('tipo', [TipoUsuario::Administrador, TipoUsuario::Colaborador]);
    }

    public function ehAdministrador(): bool
    {
        return $this->tipo === TipoUsuario::Administrador;
    }

    public function ehDaEquipe(): bool
    {
        return $this->tipo?->ehEquipe() ?? false;
    }

    public function conviteAceito(): bool
    {
        return $this->convite_aceito_em !== null && $this->password !== null;
    }

    /** regras.md RN-303 — quem pode entrar no sistema (vale para qualquer painel e para a API). */
    public function podeEntrar(): bool
    {
        if (! $this->ativo || ! $this->conviteAceito()) {
            return false;
        }

        if ($this->escritorio?->situacao !== SituacaoEscritorio::Ativo) {
            return false;
        }

        if ($this->tipo === TipoUsuario::UsuarioEmpresa) {
            return $this->empresas()->where('situacao', SituacaoEmpresa::Ativa)->exists();
        }

        return true;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->podeEntrar()) {
            return false;
        }

        return match ($panel->getId()) {
            'escritorio', 'empresa' => $this->ehDaEquipe(),
            'portal' => $this->tipo === TipoUsuario::UsuarioEmpresa,
            default => false,
        };
    }

    /** Portal da empresa: o tenant é a empresa (RN-241). */
    /**
     * Portal: empresas ativas vinculadas ao usuário da empresa (RN-241).
     * Contexto da empresa (equipe): todas as empresas do escritório.
     */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->ehDaEquipe()) {
            return Empresa::withoutGlobalScopes()->where('escritorio_id', $this->escritorio_id)->orderBy('razao_social')->get();
        }

        return $this->empresas()->where('situacao', SituacaoEmpresa::Ativa)->orderBy('razao_social')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->ehDaEquipe()) {
            return $tenant->escritorio_id === $this->escritorio_id; // RN-100
        }

        return in_array($tenant->getKey(), $this->empresasIds(), true);
    }

    /** regras.md RN-1602 e RN-1607 — equipe com permissão ou usuário vinculado à própria empresa. */
    public function podeGerenciarDespesasDa(Empresa $empresa): bool
    {
        if ($this->tipo === TipoUsuario::UsuarioEmpresa) {
            return in_array($empresa->getKey(), $this->empresasIds(), true);
        }

        return $empresa->escritorio_id === $this->escritorio_id && $this->can(Permissao::DespesasGerenciar->value);
    }

    /** URL inicial conforme o tipo de usuário (RN-304). */
    public function urlInicial(): string
    {
        return $this->tipo === TipoUsuario::UsuarioEmpresa ? url('/portal') : url('/admin');
    }
}

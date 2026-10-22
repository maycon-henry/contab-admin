<?php

namespace App\Actions\Admissao;

use App\Contracts\LeitorDadosDeDocumento;
use App\Domain\Admissao\PreenchimentoAutomatico;
use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * regras.md RN-579 — lê os dados pessoais de um documento anexado na admissão e devolve o
 * que pode ser pré-preenchido. Só campos vazios são devolvidos: o que o usuário já
 * preencheu nunca muda. Nada é gravado aqui; o usuário confere e salva o formulário.
 */
class PreencherComDocumento
{
    public function __construct(
        private readonly LeitorDadosDeDocumento $leitor,
        private readonly PreenchimentoAutomatico $preenchimento,
    ) {}

    public function disponivel(): bool
    {
        return $this->leitor->disponivel();
    }

    /**
     * @param  array<string, mixed>  $dadosAtuais  dados do funcionário como estão no formulário
     * @return array<string, string> campo => valor a preencher
     */
    public function executar(User $user, ?Empresa $empresa, DocumentoAdmissao $tipo, string $conteudo, string $mimeType, array $dadosAtuais): array
    {
        $this->autorizar($user, $empresa);

        $campos = $this->preenchimento->camposDoDocumento($tipo);
        if ($campos === [] || ! $this->leitor->disponivel()) {
            return [];
        }

        try {
            $lidos = $this->leitor->ler($conteudo, $mimeType, $tipo->getLabel(), $campos);
        } catch (Throwable $e) {
            // sem dados pessoais no log (AGENTS.md seção 7)
            Log::warning('Leitura automática de documento falhou.', ['tipo' => $tipo->value, 'erro' => $e::class.': '.$e->getMessage()]);

            throw new RegraDeNegocioException('Não foi possível ler o documento automaticamente. Preencha os dados manualmente.');
        }

        return $this->preenchimento->camposAPreencher($dadosAtuais, $this->preenchimento->normalizar(array_intersect_key($lidos, $campos)));
    }

    /** Quem pode preencher a admissão (SalvarAdmissao) pode usar a leitura automática. */
    private function autorizar(User $user, ?Empresa $empresa): void
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se($empresa === null || ! in_array($empresa->id, $user->empresasIds(), true), 'Empresa não vinculada ao usuário.');

            return;
        }

        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão para editar admissões.');
        RegraDeNegocioException::se($empresa !== null && $empresa->escritorio_id !== $user->escritorio_id, 'Empresa de outro escritório.');
    }
}

<?php

namespace App\Actions\Funcionarios;

use App\Enums\GrupoDocumentoFuncionario;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * regras.md RN-573 — dossiê do funcionário: um .zip com todos os documentos, organizados em
 * pastas por grupo (RN-569) e, na admissão e no desligamento, por contrato. As versões
 * anteriores (RN-562) vão numa pasta própria. A empresa recebe só o que pode ver (RN-904).
 */
class GerarDossieFuncionario
{
    /** @return array{caminho: string, nome: string} arquivo .zip temporário e o nome para download */
    public function executar(Funcionario $funcionario, User $user): array
    {
        $this->autorizar($funcionario, $user);

        $documentos = Documento::withoutGlobalScopes()
            ->where('funcionario_id', $funcionario->id)
            ->when($user->tipo === TipoUsuario::UsuarioEmpresa, fn ($q) => $q->where('visivel_empresa', true))
            ->orderBy('tipo')->orderBy('id')
            ->get();

        RegraDeNegocioException::se($documentos->isEmpty(), 'Este funcionário ainda não tem documentos.');

        $contratos = Contrato::withoutGlobalScopes()->where('funcionario_id', $funcionario->id)->get()->keyBy('id');
        $pastaRaiz = Str::slug($funcionario->nome, ' ') ?: 'funcionario';

        $caminho = storage_path('app/private/tmp/dossie-'.$funcionario->id.'-'.now()->format('YmdHis').'.zip');
        @mkdir(dirname($caminho), 0775, true);

        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $usados = [];
        $disco = Storage::disk(Documento::DISCO);

        foreach ($documentos as $documento) {
            if (! $disco->exists($documento->arquivo)) {
                continue;
            }

            $pasta = $this->pasta($documento, $contratos->get($documento->documentavel_id));
            if ($documento->substituido) {
                $pasta = 'Versões anteriores/'.$pasta;
            }

            $extensao = pathinfo($documento->arquivo, PATHINFO_EXTENSION) ?: pathinfo($documento->nome_original, PATHINFO_EXTENSION);
            $nome = $documento->rotulo().($documento->substituido ? ' - enviado em '.$documento->created_at->format('d-m-Y') : '');
            $arquivo = $this->semRepetir("{$pastaRaiz}/{$pasta}/{$nome}", $extensao, $usados);

            $zip->addFromString($arquivo, (string) $disco->get($documento->arquivo));
        }

        $zip->close();

        return ['caminho' => $caminho, 'nome' => 'dossie-'.Str::slug($funcionario->nome).'.zip'];
    }

    private function autorizar(Funcionario $funcionario, User $user): void
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se(! in_array($funcionario->empresa_id, $user->empresasIds(), true), 'Empresa não vinculada ao usuário.');

            return;
        }

        RegraDeNegocioException::se($funcionario->escritorio_id !== $user->escritorio_id || ! $user->can(Permissao::FuncionariosVisualizar->value),
            'Você não tem permissão para baixar os documentos deste funcionário.');
    }

    /** "01 Pessoais", "03 Admissão 01-10-2026" (data de admissão do contrato, para recontratações — RN-501). */
    private function pasta(Documento $documento, ?Contrato $contrato): string
    {
        $grupo = $documento->grupo();
        $pasta = sprintf('%02d %s', $grupo->ordem(), $grupo->getLabel());

        if ($contrato && in_array($grupo, [GrupoDocumentoFuncionario::Admissao, GrupoDocumentoFuncionario::Desligamento], true)) {
            $data = $grupo === GrupoDocumentoFuncionario::Desligamento ? $contrato->data_desligamento : ($contrato->data_admissao ?? $contrato->data_admissao_prevista);
            $pasta .= $data ? ' '.$data->format('d-m-Y') : '';
        }

        return $pasta;
    }

    /** @param array<string, true> $usados */
    private function semRepetir(string $base, string $extensao, array &$usados): string
    {
        $sufixo = $extensao !== '' ? '.'.$extensao : '';
        $nome = $base.$sufixo;
        for ($i = 2; isset($usados[$nome]); $i++) {
            $nome = "{$base} ({$i}){$sufixo}";
        }
        $usados[$nome] = true;

        return $nome;
    }
}

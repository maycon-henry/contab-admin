<?php

namespace App\Actions\NotasFiscais;

use App\Exceptions\RegraDeNegocioException;
use App\Jobs\ProcessarArquivoXmlJob;
use App\Models\Empresa;
use App\Models\LoteImportacaoXml;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Recebe os arquivos enviados, cria o lote e despacha um job por XML — regras.md seção 10.1, AGENTS.md seção 5.3.
 * O upload só armazena; a leitura é assíncrona.
 */
class ImportarLoteXml
{
    public const DISCO = 'local';

    /** Limites de segurança do .zip (decisão D26 / RN-701 ainda pendente). */
    public const MAX_ARQUIVOS = 500;

    public const MAX_BYTES_DESCOMPACTADOS = 200 * 1024 * 1024;

    /**
     * @param  array<string, string>  $arquivos  caminho temporário no disco => nome original
     */
    public function executar(Empresa $empresa, User $user, array $arquivos, bool $sincrono = false): LoteImportacaoXml
    {
        RegraDeNegocioException::se($arquivos === [], 'Envie pelo menos um arquivo XML ou ZIP.');

        $lote = LoteImportacaoXml::withoutGlobalScopes()->create([
            'escritorio_id' => $empresa->escritorio_id,
            'empresa_id' => $empresa->id,
            'enviado_por_id' => $user->id,
        ]);

        $pasta = "escritorios/{$empresa->escritorio_id}/empresas/{$empresa->id}/importacoes/{$lote->id}";

        foreach ($arquivos as $caminho => $nomeOriginal) {
            $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

            if ($extensao === 'zip') {
                $this->extrairZip($lote, $pasta, Storage::disk(self::DISCO)->path($caminho), $nomeOriginal);
            } else {
                $destino = $pasta.'/'.Str::random(8).'-'.basename($nomeOriginal);
                Storage::disk(self::DISCO)->move($caminho, $destino);
                $lote->arquivos()->create(['nome_original' => $nomeOriginal, 'arquivo' => $destino]);
            }
        }

        $lote->update(['total_arquivos' => $lote->arquivos()->count()]);

        foreach ($lote->arquivos as $arquivo) {
            $sincrono ? ProcessarArquivoXmlJob::dispatchSync($arquivo->id) : ProcessarArquivoXmlJob::dispatch($arquivo->id);
        }

        return $lote->fresh();
    }

    private function extrairZip(LoteImportacaoXml $lote, string $pasta, string $caminhoZip, string $nomeZip): void
    {
        $zip = new ZipArchive;

        if ($zip->open($caminhoZip) !== true) {
            $lote->arquivos()->create(['nome_original' => $nomeZip, 'arquivo' => '', 'resultado' => 'rejeitada', 'motivo' => 'Arquivo ZIP inválido.']);

            return;
        }

        $totalBytes = 0;
        $quantidade = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $info = $zip->statIndex($i);
            $nome = $info['name'];

            // ignora diretórios, caminhos com ".." e arquivos que não sejam .xml
            if (str_ends_with($nome, '/') || str_contains($nome, '..') || strtolower(pathinfo($nome, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $totalBytes += $info['size'];
            if (++$quantidade > self::MAX_ARQUIVOS || $totalBytes > self::MAX_BYTES_DESCOMPACTADOS) {
                $lote->arquivos()->create(['nome_original' => $nomeZip, 'arquivo' => '', 'resultado' => 'rejeitada',
                    'motivo' => 'ZIP excede o limite de '.self::MAX_ARQUIVOS.' arquivos ou 200 MB descompactados.']);
                break;
            }

            $destino = $pasta.'/'.Str::random(8).'-'.basename($nome);
            Storage::disk(self::DISCO)->put($destino, $zip->getFromIndex($i));
            $lote->arquivos()->create(['nome_original' => basename($nome), 'arquivo' => $destino]);
        }

        $zip->close();
        @unlink($caminhoZip);
    }
}

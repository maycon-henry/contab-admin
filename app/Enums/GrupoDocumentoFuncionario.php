<?php

namespace App\Enums;

use App\Actions\Desligamento\ConcluirDesligamento;
use Filament\Support\Contracts\HasLabel;

/**
 * Como os documentos do funcionário são organizados na consulta e no dossiê — regras.md RN-569.
 * O grupo vem do tipo do documento (checklist da admissão — RN-526 — ou do desligamento — RN-546).
 */
enum GrupoDocumentoFuncionario: string implements HasLabel
{
    case Pessoais = 'pessoais';
    case Dependentes = 'dependentes';
    case Admissao = 'admissao';
    case Desligamento = 'desligamento';
    case Outros = 'outros';

    public const PREFIXO_DESLIGAMENTO = 'desligamento_';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pessoais => 'Pessoais',
            self::Dependentes => 'Dependentes',
            self::Admissao => 'Admissão',
            self::Desligamento => 'Desligamento',
            self::Outros => 'Outros',
        };
    }

    public static function doTipo(?string $tipo): self
    {
        if (str_starts_with((string) $tipo, self::PREFIXO_DESLIGAMENTO)) {
            return self::Desligamento;
        }

        return match (DocumentoAdmissao::tryFrom((string) $tipo)) {
            DocumentoAdmissao::DocumentoComFoto, DocumentoAdmissao::ComprovanteResidencia, DocumentoAdmissao::CertidaoCasamento,
            DocumentoAdmissao::CertificadoReservista, DocumentoAdmissao::ComprovanteEscolaridade => self::Pessoais,
            DocumentoAdmissao::DocumentosDependentes => self::Dependentes,
            DocumentoAdmissao::AsoAdmissional, DocumentoAdmissao::ContratoAssinado => self::Admissao,
            null => self::Outros,
        };
    }

    /** Nome do tipo para a tela e para o nome do arquivo no dossiê. */
    public static function rotuloDoTipo(?string $tipo): string
    {
        if (str_starts_with((string) $tipo, self::PREFIXO_DESLIGAMENTO)) {
            $chave = substr((string) $tipo, strlen(self::PREFIXO_DESLIGAMENTO));

            return ConcluirDesligamento::DOCUMENTOS_OBRIGATORIOS[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
        }

        return DocumentoAdmissao::tryFrom((string) $tipo)?->getLabel() ?? ucfirst(str_replace('_', ' ', (string) $tipo));
    }

    /** Pastas do dossiê em ordem: "01 Pessoais", "02 Dependentes"... */
    public function ordem(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * Para filtrar no banco pelos grupos de tipo fixo.
     *
     * @return list<string>
     */
    public function tipos(): array
    {
        return collect(DocumentoAdmissao::cases())
            ->filter(fn (DocumentoAdmissao $t) => self::doTipo($t->value) === $this)
            ->map(fn (DocumentoAdmissao $t) => $t->value)
            ->values()->all();
    }
}

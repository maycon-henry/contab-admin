<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipos de documento anexados a uma despesa — regras.md RN-1606.
 * Formatos aceitos e tamanho máximo de cada tipo são configurados pelo escritório (RN-1610);
 * os valores abaixo valem enquanto o escritório não configurar.
 */
enum TipoDocumentoDespesa: string implements HasLabel
{
    case Boleto = 'boleto';
    case Recibo = 'recibo';
    case NotaFiscal = 'nota_fiscal';
    case Cheque = 'cheque';

    public const TAMANHO_PADRAO_MB = 10;

    public function getLabel(): string
    {
        return match ($this) {
            self::Boleto => 'Boleto',
            self::Recibo => 'Recibo',
            self::NotaFiscal => 'Nota Fiscal',
            self::Cheque => 'Cheque',
        };
    }

    /** @return array<string> chaves de App\Domain\Opcoes::FORMATOS */
    public function formatosPadrao(): array
    {
        return match ($this) {
            self::NotaFiscal => ['pdf', 'imagem', 'xml'],
            default => ['pdf', 'imagem'],
        };
    }
}

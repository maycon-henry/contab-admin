<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Checklist de documentos admissionais — regras.md RN-526.
 * Nesta fase a lista é fixa (personalização por escritório: decisão D30 / RN-526).
 */
enum DocumentoAdmissao: string implements HasLabel
{
    case DocumentoComFoto = 'documento_com_foto';
    case ComprovanteResidencia = 'comprovante_residencia';
    case AsoAdmissional = 'aso_admissional';
    case ContratoAssinado = 'contrato_assinado';
    case CertidaoCasamento = 'certidao_casamento';
    case DocumentosDependentes = 'documentos_dependentes';
    case CertificadoReservista = 'certificado_reservista';
    case ComprovanteEscolaridade = 'comprovante_escolaridade';

    public function getLabel(): string
    {
        return match ($this) {
            self::DocumentoComFoto => 'Documento de identificação com foto',
            self::ComprovanteResidencia => 'Comprovante de residência',
            self::AsoAdmissional => 'ASO admissional',
            self::ContratoAssinado => 'Contrato de trabalho assinado',
            self::CertidaoCasamento => 'Certidão de casamento',
            self::DocumentosDependentes => 'Certidão de nascimento e CPF dos dependentes',
            self::CertificadoReservista => 'Certificado de reservista',
            self::ComprovanteEscolaridade => 'Comprovante de escolaridade',
        };
    }

    public function obrigatorio(): bool
    {
        return in_array($this, self::sempreObrigatorios(), true);
    }

    /** @return array<self> */
    public static function sempreObrigatorios(): array
    {
        return [self::DocumentoComFoto, self::ComprovanteResidencia, self::AsoAdmissional, self::ContratoAssinado];
    }
}

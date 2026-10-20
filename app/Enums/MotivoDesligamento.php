<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** regras.md RN-541 — códigos da tabela 19 do eSocial */
enum MotivoDesligamento: string implements HasLabel
{
    case JustaCausa = '01';
    case SemJustaCausa = '02';
    case AntecipadaPeloEmpregador = '03';
    case AntecipadaPeloEmpregado = '04';
    case CulpaReciproca = '05';
    case TerminoContratoATermo = '06';
    case PedidoDeDemissao = '07';
    case Falecimento = '10';
    case EncerramentoEmpresa = '14';
    case AprendizDesempenho = '15';
    case RescisaoIndireta = '17';
    case ForcaMaior = '27';
    case Acordo = '33';

    public function getLabel(): string
    {
        return $this->value.' — '.match ($this) {
            self::JustaCausa => 'Justa causa, por iniciativa do empregador',
            self::SemJustaCausa => 'Sem justa causa, por iniciativa do empregador',
            self::AntecipadaPeloEmpregador => 'Rescisão antecipada do contrato a termo, pelo empregador',
            self::AntecipadaPeloEmpregado => 'Rescisão antecipada do contrato a termo, pelo empregado',
            self::CulpaReciproca => 'Culpa recíproca',
            self::TerminoContratoATermo => 'Término do contrato a termo (inclui fim da experiência)',
            self::PedidoDeDemissao => 'Pedido de demissão',
            self::Falecimento => 'Falecimento do empregado',
            self::EncerramentoEmpresa => 'Encerramento da empresa, de estabelecimento ou de atividade',
            self::AprendizDesempenho => 'Aprendiz — desempenho insuficiente ou inadaptação',
            self::RescisaoIndireta => 'Rescisão indireta',
            self::ForcaMaior => 'Força maior',
            self::Acordo => 'Acordo entre as partes (art. 484-A da CLT)',
        };
    }
}

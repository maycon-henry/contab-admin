<?php

namespace App\Domain;

/** Listas de opções usadas em formulários (valores alinhados ao eSocial quando aplicável). */
final class Opcoes
{
    public const SEXO = ['M' => 'Masculino', 'F' => 'Feminino'];

    public const RACA_COR = [
        'branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela',
        'indigena' => 'Indígena', 'nao_informado' => 'Não informado',
    ];

    public const ESTADO_CIVIL = [
        'solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)',
        'separado' => 'Separado(a)', 'viuvo' => 'Viúvo(a)', 'uniao_estavel' => 'União estável',
    ];

    public const GRAU_INSTRUCAO = [
        'fundamental_incompleto' => 'Fundamental incompleto', 'fundamental_completo' => 'Fundamental completo',
        'medio_incompleto' => 'Médio incompleto', 'medio_completo' => 'Médio completo',
        'superior_incompleto' => 'Superior incompleto', 'superior_completo' => 'Superior completo',
        'pos_graduacao' => 'Pós-graduação', 'mestrado' => 'Mestrado', 'doutorado' => 'Doutorado',
    ];

    public const TIPO_DEPENDENTE = [
        'conjuge' => 'Cônjuge', 'companheiro' => 'Companheiro(a)', 'filho' => 'Filho(a)', 'enteado' => 'Enteado(a)',
        'pai_mae' => 'Pai/Mãe', 'outro' => 'Outro',
    ];

    public const UNIDADE_SALARIO = ['hora' => 'Por hora', 'dia' => 'Por dia', 'semana' => 'Por semana', 'quinzena' => 'Por quinzena', 'mes' => 'Por mês', 'tarefa' => 'Por tarefa'];

    // RN-520 — categorias aceitas nesta fase (decisão D31)
    public const CATEGORIA = ['101' => '101 — Empregado geral', '103' => '103 — Aprendiz'];

    public const NATUREZA_ATIVIDADE = ['urbana' => 'Urbana', 'rural' => 'Rural'];

    public const REGIME_JORNADA = [
        'controle_horario' => 'Submetido a controle de horário',
        'atividade_externa' => 'Atividade externa (art. 62, I)',
        'cargo_gestao' => 'Cargo de gestão (art. 62, II)',
        'teletrabalho' => 'Teletrabalho (art. 62, III)',
    ];

    public const PENSAO_ALIMENTICIA = ['nao_ha' => 'Não há', 'percentual' => 'Percentual', 'valor' => 'Valor', 'percentual_valor' => 'Percentual e valor'];

    public const CLASSIFICACAO_TRIBUTARIA = [
        '01' => '01 — Simples Nacional (tributação previdenciária substituída)',
        '02' => '02 — Simples Nacional (tributação previdenciária não substituída)',
        '03' => '03 — Simples Nacional (substituída e não substituída)',
        '04' => '04 — MEI',
        '99' => '99 — Demais pessoas jurídicas',
    ];

    public const PORTE = ['me' => 'ME', 'epp' => 'EPP', 'demais' => 'Demais'];

    public const FORMATOS = ['pdf' => 'PDF', 'imagem' => 'Imagem (JPG/PNG)', 'xml' => 'XML', 'planilha' => 'Planilha'];

    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /** @return array<string, string> */
    public static function ufs(): array
    {
        return array_combine(self::UFS, self::UFS);
    }

    /** @return array<string> MIME types aceitos por formato */
    public static function mimes(array $formatos): array
    {
        $mapa = [
            'pdf' => ['application/pdf'],
            'imagem' => ['image/jpeg', 'image/png'],
            'xml' => ['application/xml', 'text/xml'],
            'planilha' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
        ];

        return collect($formatos ?: array_keys($mapa))->flatMap(fn ($f) => $mapa[$f] ?? [])->values()->all();
    }
}

<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * Mês/ano de trabalho de uma empresa — AGENTS.md seção 3.5.
 * Formato na URL/API: "2026-09". Formato na tela: "09/2026". No banco: date com o primeiro dia do mês.
 */
final class Competencia implements Stringable
{
    private function __construct(public readonly int $ano, public readonly int $mes)
    {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException("Mês inválido: {$mes}");
        }
    }

    public static function de(int $ano, int $mes): self
    {
        return new self($ano, $mes);
    }

    public static function daData(DateTimeInterface|string $data): self
    {
        $data = CarbonImmutable::parse($data);

        return new self($data->year, $data->month);
    }

    /** Aceita "2026-09" (URL) ou "09/2026" (tela). */
    public static function parse(string $valor): self
    {
        if (preg_match('/^(\d{4})-(\d{2})(-\d{2})?$/', $valor, $m)) {
            return new self((int) $m[1], (int) $m[2]);
        }

        if (preg_match('/^(\d{2})\/(\d{4})$/', $valor, $m)) {
            return new self((int) $m[2], (int) $m[1]);
        }

        throw new InvalidArgumentException("Competência inválida: {$valor}");
    }

    public static function atual(): self
    {
        return self::daData(CarbonImmutable::now());
    }

    /**
     * Competência padrão ao abrir uma empresa — decisão D11 / RN-600 (sugestão: mês anterior).
     */
    public static function padrao(): self
    {
        return self::atual()->anterior();
    }

    public function anterior(): self
    {
        return self::daData($this->primeiroDia()->subMonth());
    }

    public function seguinte(): self
    {
        return self::daData($this->primeiroDia()->addMonth());
    }

    public function primeiroDia(): CarbonImmutable
    {
        return CarbonImmutable::create($this->ano, $this->mes, 1)->startOfDay();
    }

    public function ultimoDia(): CarbonImmutable
    {
        return $this->primeiroDia()->endOfMonth()->startOfDay();
    }

    public function ehFutura(): bool
    {
        return $this->primeiroDia()->greaterThan(self::atual()->primeiroDia());
    }

    /** Data do dia X do mês seguinte (prazo dos itens — RN-610). */
    public function diaDoMesSeguinte(int $dia): CarbonImmutable
    {
        $seguinte = $this->seguinte()->primeiroDia();

        return $seguinte->setDay(min($dia, $seguinte->daysInMonth));
    }

    public function igual(self $outra): bool
    {
        return $this->ano === $outra->ano && $this->mes === $outra->mes;
    }

    public function paraUrl(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }

    public function paraTela(): string
    {
        return sprintf('%02d/%04d', $this->mes, $this->ano);
    }

    public function paraBanco(): string
    {
        return $this->primeiroDia()->toDateString();
    }

    public function __toString(): string
    {
        return $this->paraTela();
    }
}

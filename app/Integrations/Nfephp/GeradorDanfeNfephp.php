<?php

namespace App\Integrations\Nfephp;

use App\Contracts\GeradorDanfe;
use NFePHP\DA\NFe\Danfce;
use NFePHP\DA\NFe\Danfe;

/** DANFE/DANFCE com a biblioteca nfephp-org/sped-da (leiaute oficial, sem acesso à SEFAZ). */
class GeradorDanfeNfephp implements GeradorDanfe
{
    public function gerar(string $xml, string $modelo, bool $cancelada = false): string
    {
        $documento = $modelo === '65' ? new Danfce($xml) : new Danfe($xml);
        $documento->debugMode(false);
        $documento->creditsIntegratorFooter('Contab Admin', false);
        $documento->setCancelFlag($cancelada);

        return $documento->render();
    }
}

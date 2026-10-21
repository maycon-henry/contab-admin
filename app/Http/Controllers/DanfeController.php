<?php

namespace App\Http\Controllers;

use App\Actions\NotasFiscais\GerarDanfe;
use App\Exceptions\RegraDeNegocioException;
use App\Models\NotaFiscal;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Abre o DANFE da nota no navegador — regras.md RN-743. */
class DanfeController extends Controller
{
    public function __invoke(Request $request, NotaFiscal $nota, GerarDanfe $gerarDanfe): Response
    {
        try {
            $pdf = $gerarDanfe->executar($nota, $request->user());
        } catch (RegraDeNegocioException $e) {
            abort(422, $e->getMessage());
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="danfe-'.$nota->chave_acesso.'.pdf"',
        ]);
    }
}

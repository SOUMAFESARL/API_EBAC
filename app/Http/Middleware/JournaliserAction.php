<?php

namespace App\Http\Middleware;

use App\Support\Audit\Journal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JournaliserAction
{
    public function handle(Request $request, Closure $next): Response
    {
        $regle = config('audit.routes', [])[$request->route()?->getName()] ?? null;
        if (! $regle) {
            return $next($request);
        }

        Journal::ouvrirCollecte();
        try {
            $reponse = $next($request);
        } finally {
            $changements = Journal::fermerCollecte();
        }

        if ($reponse->isSuccessful()) {
            Journal::consignerChangements($regle, $changements);
        }

        return $reponse;
    }
}

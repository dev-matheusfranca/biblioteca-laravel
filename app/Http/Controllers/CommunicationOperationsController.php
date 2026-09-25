<?php

namespace App\Http\Controllers;

use App\Actions\Communication\ReprocessOutboxEvent;
use App\Models\CommunicationOutbox;
use DomainException;
use Illuminate\Http\Request;

class CommunicationOperationsController extends Controller
{
    public function index(Request $request)
    {
        $eventos = CommunicationOutbox::query()
            ->with('portalNotice')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('operacao.comunicacoes.index', compact('eventos'));
    }

    public function reprocessar(Request $request, CommunicationOutbox $outbox, ReprocessOutboxEvent $reprocess)
    {
        try {
            $reprocess->execute($request->user(), $outbox);
        } catch (DomainException $exception) {
            return back()->withErrors(['reprocessamento' => $exception->getMessage()]);
        }

        return back()->with('success', 'Comunicação reenfileirada para nova tentativa.');
    }
}

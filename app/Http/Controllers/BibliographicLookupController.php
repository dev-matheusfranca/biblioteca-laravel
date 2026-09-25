<?php

namespace App\Http\Controllers;

use App\Services\Bibliography\Isbn;
use App\Services\Bibliography\OpenLibraryLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BibliographicLookupController extends Controller
{
    public function index()
    {
        return view('livros.isbn', ['result' => null]);
    }

    public function lookup(Request $request, OpenLibraryLookup $lookup)
    {
        $data = $request->validate(['isbn' => ['required', 'string', 'max:32']]);
        $isbn = Isbn::normalize($data['isbn']);
        if ($isbn === null) {
            throw ValidationException::withMessages(['isbn' => 'Informe um ISBN de 10 ou 13 dígitos com verificador válido.']);
        }
        $request->session()->forget('bibliographic_suggestion');
        $result = $lookup->lookup($isbn);
        if ($result['status'] === 'found') {
            $request->session()->put('bibliographic_suggestion', [
                'id' => (string) Str::uuid(), 'created_at' => now()->timestamp, 'result' => $result,
            ]);
        }

        return view('livros.isbn', compact('result', 'isbn'));
    }

    public function useSuggestion(Request $request)
    {
        $request->validate(['suggestion_id' => ['required', 'uuid']]);
        $suggestion = $request->session()->get('bibliographic_suggestion');
        if (! is_array($suggestion) || ! hash_equals((string) ($suggestion['id'] ?? ''), $request->string('suggestion_id')->toString())
            || ($suggestion['created_at'] ?? 0) < now()->subMinutes(15)->timestamp) {
            throw ValidationException::withMessages(['isbn' => 'A sugestão expirou ou não está disponível. Consulte o ISBN novamente.']);
        }
        $request->session()->forget('bibliographic_suggestion');
        $edition = $suggestion['result']['edition'];

        return redirect()->route('livros.create')->withInput([
            'titulo' => $edition['title'], 'ano_publicacao' => $edition['publication_year'], 'isbn' => $edition['isbn'],
        ])->with('success', 'Sugestão aplicada ao formulário. Confira os dados e selecione autor e categoria antes de salvar.');
    }
}

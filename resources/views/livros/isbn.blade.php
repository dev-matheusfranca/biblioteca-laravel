@extends('layouts.app')
@section('title', 'Consultar ISBN')
@section('content')
<a class="back-link" href="{{ route('livros.create') }}">← Cadastrar manualmente</a>
<header class="page-heading"><div><div class="eyebrow">Acervo</div><h1>Consultar ISBN</h1><p>Encontre uma edição na Open Library e confira os dados antes de cadastrar.</p></div></header>
<section class="panel form-panel">
    <form action="{{ route('isbn.lookup') }}" method="POST" data-isbn-lookup>
        @csrf
        <div class="field"><label for="isbn_lookup">ISBN do livro</label><input id="isbn_lookup" name="isbn" class="form-control" value="{{ old('isbn', $isbn ?? '') }}" maxlength="32" required autocomplete="off" placeholder="Ex.: 9780140328721" aria-describedby="isbn-hint"><small id="isbn-hint">Consulte um título por vez. ISBNs com espaços ou hífens são aceitos.</small>@error('isbn')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Consultar edição</button><a class="btn btn-ghost" href="{{ route('livros.create') }}">Preencher manualmente</a></div>
        <p role="status" data-isbn-progress hidden>Consultando a Open Library…</p>
    </form>
</section>
@if($result)
<section class="panel form-panel" aria-live="polite">
    @if($result['status'] === 'found')
        <h2>Sugestão encontrada</h2><h3>{{ $result['edition']['title'] }}</h3>
        <dl class="detail-list"><dt>ISBN da edição</dt><dd>{{ $result['edition']['isbn'] }}</dd><dt>Ano da edição</dt><dd>{{ $result['edition']['publication_year'] ?? 'Não informado' }}</dd><dt>Autores informados na obra</dt><dd>{{ implode(', ', $result['authors']) ?: 'Não informados' }}</dd></dl>
        <p>Confira o autor na edição física e selecione o cadastro correspondente no próximo formulário. Nenhum livro será salvo nesta etapa.</p>
        <p><a class="text-link" href="{{ $result['source_url'] }}" target="_blank" rel="noopener noreferrer">Conferir edição na Open Library →</a></p>
        <form action="{{ route('isbn.useSuggestion') }}" method="POST">@csrf<input type="hidden" name="suggestion_id" value="{{ session('bibliographic_suggestion.id') }}"><button class="btn btn-primary" type="submit">Usar sugestão no cadastro</button></form>
    @else
        <h2>{{ $result['status'] === 'not_found' ? 'Nenhuma edição confirmada' : 'Consulta indisponível' }}</h2>
        <p>{{ match($result['status']) { 'not_found' => 'Não encontramos uma edição única para este ISBN. Confira o número ou preencha os dados manualmente.', 'busy' => 'Há outra consulta em andamento. Aguarde alguns segundos e tente novamente.', 'invalid_response' => 'A fonte retornou dados que não puderam ser confirmados. Você pode continuar o cadastro manualmente.', default => 'Não foi possível consultar a Open Library agora. Tente novamente depois ou continue manualmente.' } }}</p>
        <a class="btn btn-secondary" href="{{ route('livros.create') }}">Continuar cadastro manual</a>
    @endif
</section>
@endif
<script src="{{ asset('js/isbn-lookup.js') }}" defer></script>
@endsection

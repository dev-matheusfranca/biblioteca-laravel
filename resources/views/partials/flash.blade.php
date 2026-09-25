@if(session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif
@if(session('success'))
    <div class="alert alert-success" role="status">@include('partials.icon', ['name' => 'check'])<span>{{ session('success') }}</span></div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-validation-summary>
        <strong>Confira os campos antes de continuar.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

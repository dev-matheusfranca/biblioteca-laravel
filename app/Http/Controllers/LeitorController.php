<?php

namespace App\Http\Controllers;

use App\Actions\Identity\RevokeUserAccess;
use App\Enums\UserRole;
use App\Http\Requests\Leitor\LeitorIndexRequest;
use App\Http\Requests\Leitor\LeitorRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeitorController extends Controller
{
    public function index(LeitorIndexRequest $request)
    {
        $leitores = User::query()
            ->where('role', UserRole::Reader->value)
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%'.$request->string('q')->trim().'%';

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', $search)->orWhere('email', 'like', $search);
                });
            })
            ->when($request->input('status') === 'ativos', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inativos', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $totalLeitores = User::query()->where('role', UserRole::Reader->value)->count();

        return view('leitores.index', compact('leitores', 'totalLeitores'));
    }

    public function create()
    {
        return view('leitores.create');
    }

    public function store(LeitorRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, &$leitor) {
            $leitor = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => ($data['password'] ?? null) ?: Str::password(40),
            ]);
            $leitor->forceFill([
                'role' => UserRole::Reader,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ])->save();

            AuditLog::create([
                'action' => 'reader.created',
                'actor_id' => $request->user()->getKey(),
                'subject_id' => $leitor->getKey(),
                'metadata' => [
                    'role' => $leitor->role->value,
                    'is_active' => $leitor->isActive(),
                ],
            ]);
        });

        return redirect()->route('leitores.index')->with('success', 'Leitor cadastrado. Oriente a pessoa a recuperar a senha para definir o primeiro acesso.');
    }

    public function edit(User $leitor)
    {
        $this->ensureReader($leitor);

        return view('leitores.edit', compact('leitor'));
    }

    public function update(LeitorRequest $request, User $leitor, RevokeUserAccess $revokeAccess)
    {
        $this->ensureReader($leitor);
        $data = $request->validated();

        DB::transaction(function () use ($request, $leitor, $data, $revokeAccess) {
            /** @var User $lockedLeitor */
            $lockedLeitor = User::query()->lockForUpdate()->findOrFail($leitor->getKey());
            $this->ensureReader($lockedLeitor);
            $wasActive = $lockedLeitor->isActive();
            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $request->boolean('is_active'),
            ];

            if ($data['password'] ?? null) {
                $attributes['password'] = $data['password'];
            }

            $lockedLeitor->forceFill($attributes)->save();
            if (! $lockedLeitor->isActive() || ($data['password'] ?? null)) {
                $revokeAccess->execute($lockedLeitor);
            }

            if ($wasActive !== $lockedLeitor->isActive()) {
                AuditLog::create([
                    'action' => 'reader.status_changed',
                    'actor_id' => $request->user()->getKey(),
                    'subject_id' => $lockedLeitor->getKey(),
                    'metadata' => [
                        'before' => ['is_active' => $wasActive],
                        'after' => ['is_active' => $lockedLeitor->isActive()],
                    ],
                ]);
            }
        });

        return redirect()->route('leitores.index')->with('success', 'Leitor atualizado.');
    }

    private function ensureReader(User $leitor): void
    {
        abort_unless($leitor->role === UserRole::Reader, 404);
    }
}

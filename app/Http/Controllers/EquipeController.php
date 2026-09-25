<?php

namespace App\Http\Controllers;

use App\Actions\Identity\UpdateTeamMember;
use App\Enums\UserRole;
use App\Http\Requests\Equipe\UpdateTeamMemberRequest;
use App\Models\User;
use DomainException;

class EquipeController extends Controller
{
    public function index()
    {
        $usuarios = User::query()->orderBy('name')->orderBy('id')->paginate(20);
        $roles = UserRole::cases();

        return view('equipe.index', compact('usuarios', 'roles'));
    }

    public function update(UpdateTeamMemberRequest $request, User $user, UpdateTeamMember $updateTeamMember)
    {
        try {
            $updateTeamMember->execute(
                $request->user(),
                $user,
                UserRole::from($request->string('role')->toString()),
                $request->boolean('is_active'),
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['role' => $exception->getMessage()]);
        }

        return back()->with('success', 'Papel e acesso atualizados.');
    }
}

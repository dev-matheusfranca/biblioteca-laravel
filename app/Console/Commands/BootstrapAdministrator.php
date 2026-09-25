<?php

namespace App\Console\Commands;

use App\Actions\Identity\UpdateTeamMember;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class BootstrapAdministrator extends Command
{
    protected $signature = 'biblioteca:bootstrap-admin {user? : ID ou e-mail de uma conta existente}';

    protected $description = 'Promove uma conta existente a administrador com registro de auditoria.';

    public function handle(UpdateTeamMember $updateTeamMember): int
    {
        $identifier = $this->argument('user') ?: $this->ask('Informe o ID ou e-mail da conta existente');

        if (! $identifier) {
            $this->error('Informe uma conta existente.');

            return self::FAILURE;
        }

        $user = ctype_digit((string) $identifier)
            ? User::query()->find($identifier)
            : User::query()->where('email', $identifier)->first();

        if (! $user) {
            $this->error('Conta não encontrada.');

            return self::FAILURE;
        }

        $updateTeamMember->execute(null, $user, UserRole::Admin, true, 'administrator.bootstrapped');
        $this->info('Administrador provisionado.');

        return self::SUCCESS;
    }
}

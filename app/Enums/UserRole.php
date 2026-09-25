<?php

namespace App\Enums;

enum UserRole: string
{
    case Reader = 'leitor';
    case Librarian = 'bibliotecario';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Reader => 'Leitor',
            self::Librarian => 'Bibliotecário',
            self::Admin => 'Administrador',
        };
    }
}

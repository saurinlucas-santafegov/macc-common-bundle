<?php

namespace Macc\CommonBundle\Security;

interface UsuarioInterface
{
    public function hasFuncion(string $grupo, string $funcion): bool;
}

<?php

namespace Macc\CommonBundle\Security;

use Macc\CommonBundle\Workflow\WorkflowActionInterface;

interface RolActionInterface extends WorkflowActionInterface
{
    public function getRolGrupo(): RolGrupoInterface;
    public function getRolFuncion(): RolFuncionInterface;
}

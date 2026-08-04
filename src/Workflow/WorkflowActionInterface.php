<?php

namespace Macc\CommonBundle\Workflow;

interface WorkflowActionInterface extends \BackedEnum
{
    /**
     * Devuelve un label legible de la acción.
     */
    public function label(): string;
}

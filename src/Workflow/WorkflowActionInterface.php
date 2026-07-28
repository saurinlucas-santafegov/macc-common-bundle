<?php

namespace Macc\CommonBundle\Workflow;

interface WorkflowActionInterface extends \UnitEnum
{
    /**
     * Devuelve un label legible de la acción.
     */
    public function label(): string;

    /**
     * Crea una instancia del enum desde el nombre.
     */
    public static function from(string $name): static;
}

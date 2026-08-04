<?php

namespace Macc\CommonBundle\Workflow;

interface WorkflowStateInterface extends \BackedEnum
{
    /**
     * Devuelve un label legible del estado (opcional).
     */
    public function label(): string;

    /**
     * Devuelve el estado inicial, dependiendo de la acción inicial (si aplica).
     */
    public static function getStartingState(): self|array;

    /**
     * Devuelve la lista de estados finales válidos.
     *
     * @return array<class-string<WorkflowStateInterface>>
     */
    public static function getEndStates(): self|array;
}

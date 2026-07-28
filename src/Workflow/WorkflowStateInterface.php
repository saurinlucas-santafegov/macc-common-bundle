<?php

namespace Macc\CommonBundle\Workflow;

interface WorkflowStateInterface extends \UnitEnum
{
    /**
     * Devuelve un label legible del estado (opcional).
     */
    public function label(): string;

    /**
     * Crea una instancia del enum desde el nombre.
     */
    public static function from(string $name): static;

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

<?php

namespace Macc\CommonBundle\Workflow;

trait WorkflowActionTrait
{
    /**
     * Por defecto, ninguna acción es un wrapper.
     * @return WorkflowActionInterface[]
     */
    public function getSubActions(): array
    {
        return [];
    }

    /**
     * Por defecto, devuelve false.
     */
    public function isWrapper(): bool
    {
        return !empty($this->getSubActions());
    }

    /**
     * Implementación por defecto del label.
     */
    public function label(): string
    {
        return $this->name;
    }
}

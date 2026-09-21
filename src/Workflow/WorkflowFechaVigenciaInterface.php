<?php

namespace Macc\CommonBundle\Workflow;

/**
 * Si se quiere setear una fecha de vigencia a los historicos, se implementa esta interfaz en el initiator y se toma la fecha de `getFechaVigencia()`.
 *
 * Si no, se toma por defecto `date` del historico como `fechaVigencia`
 */
interface WorkflowFechaVigenciaInterface
{
    /**
     * Es la fecha que va a tomar `fechaVigencia` en el history cuando se haga la transición
     */
    public function getFechaVigencia(): ?\DateTimeInterface;
}

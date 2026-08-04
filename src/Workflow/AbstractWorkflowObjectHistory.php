<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
abstract class AbstractWorkflowObjectHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $state = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $action = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $usuario = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected ?array $data = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $initiatorType = null;

    #[ORM\Column(type: Types::INTEGER, length: 255, nullable: true)]
    protected ?int $initiatorId = null;

    abstract public function getWorkflowObject(): ?AbstractWorkflowObject;

    abstract public function setWorkflowObject(?AbstractWorkflowObject $object): static;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): WorkflowStateInterface|null
    {
        if ($this->state === null) {
            return null;
        }

        $enumClass = $this->getWorkflowObject()->getStateClass();

        if (!is_subclass_of($enumClass, WorkflowStateInterface::class)) {
            throw new \LogicException("La clase $enumClass no implementa WorkflowStateInterface");
        }

        return $enumClass::from($this->state);
    }

    public function getAction(): WorkflowActionInterface|null
    {
        if ($this->action === null) {
            return null;
        }

        $enumClass = $this->getWorkflowObject()->getActionsClass();

        if (!is_subclass_of($enumClass, WorkflowActionInterface::class)) {
            throw new \LogicException("La clase $enumClass no implementa WorkflowActionInterface");
        }

        return $enumClass::from($this->action);
    }

    public function setState(WorkflowStateInterface $state): static
    {
        // Guarda el nombre del enum (string) en la columna de la DB
        $this->state = $state->name;
        return $this;
    }

    public function setAction(WorkflowActionInterface $action): static
    {
        // Guarda el nombre del enum (string) en la columna de la DB
        $this->action = $action->name;
        return $this;
    }


    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getUsuario(): ?string
    {
        return $this->usuario;
    }

    public function setUsuario(?string $usuario): static
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Guarda una snapshot del objeto.
     */
    public function saveData(AbstractWorkflowObject $object): static
    {
        $this->data = [
            'state' => $object->getState()?->label(),
        ];

        return $this;
    }

    public function setContext(object ...$contextObjects): static
    {
        foreach ($contextObjects as $object) {
            $this->addContext($object);
        }

        return $this;
    }

    public function addContext(object $contextObject): static
    {
        if ($contextObject->getId() === null) {
            throw new \RuntimeException("El objeto que se quiere agregar como contexto no tiene Id. Puede que se quiera agregar un objeto sin persistir.");
        }

        if (!array_key_exists('context', $this->data)) {
            $this->data['context'] = [];
        }

        $this->data['context'] = [
            'id' => $contextObject->getId(),
            'class' => $contextObject::class,
        ];

        return $this;
    }

    public function getContext(): ?array
    {
        return array_key_exists('context', $this->data)
            ? $this->data['context']
            : null;
    }

    public function getInitiatorType(): ?string
    {
        return $this->initiatorType;
    }

    public function getInitiatorId(): ?int
    {
        return $this->initiatorId;
    }

    public function setInitiator(?object $initiator = null): static
    {
        if (!$initiator) {
            $this->initiatorType = null;
            $this->initiatorId = null;

            return $this;
        }

        if (!method_exists($initiator, 'getId') && $initiator->getId() === null) {
            throw new \RuntimeException("El objeto que se quiere agregar como contexto no tiene Id. Puede que se quiera agregar un objeto sin persistir.");
        }

        $this->initiatorType = $initiator::class;
        $this->initiatorId = $initiator->getId();

        if (method_exists($initiator, 'setHistory')) {
            $initiator->setHistory($this);
        }

        return $this;
    }
}

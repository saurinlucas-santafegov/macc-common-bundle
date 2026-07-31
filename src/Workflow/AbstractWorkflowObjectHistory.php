<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

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

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    protected ?UserInterface $user = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected ?array $data = [];

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

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;
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
    public function saveData(AbstractWorkflowObject $object): void
    {
        $this->data = [
            'state' => $object->getState()?->label(),
            'class' => get_class($object),
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }
}

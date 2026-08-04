<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\MappedSuperclass]
abstract class AbstractWorkflowObject
{
    abstract public function getStateClass(): string;

    abstract public function getActionsClass(): string;

    abstract public function getHistoriesClass(): string;

    abstract public function getState(): WorkflowStateInterface|string|null;

    abstract public function getHistories(): Collection;

    abstract public function addHistory(AbstractWorkflowObjectHistory $history): static;

    abstract public function removeHistory(AbstractWorkflowObjectHistory $history): static;

    public function transition(
        UserInterface|string $user,
        WorkflowActionInterface $action,
        WorkflowStateInterface $newState,
        ?object $initiator = null,
        object ...$contextObjects,
    ): static
    {
        $historiesclass = $this->getHistoriesClass();
        $newHistory = (new  $historiesclass)
            ->setWorkflowObject($this)
            ->setUsuario($user instanceof UserInterface ? $user->getUserIdentifier() : $user)
            ->setState($newState)
            ->setAction($action)
            ->setDate(new \DateTime('now', new \DateTimeZone('America/Argentina/Buenos_Aires')))
            ->setInitiator($initiator)
            ->saveData($this)
            ->setContext(...$contextObjects);

        $this->addHistory($newHistory);

        return $this;
    }
}

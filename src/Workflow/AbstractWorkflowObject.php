<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\Common\Collections\ArrayCollection;
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
        UserInterface $user,
        WorkflowActionInterface $action,
        WorkflowStateInterface $newState
    ): static
    {
        $historiesclass = $this->getHistoriesClass();
        $newHistory = (new  $historiesclass)
            ->setWorkflowObject($this)
            ->setUser($user)
            ->setState($newState)
            ->setAction($action)
            ->setDate(new \DateTime('now', new \DateTimeZone('America/Argentina/Buenos_Aires')));

        $newHistory->saveData($this);
        $this->addHistory($newHistory);

        return $this;
    }
}

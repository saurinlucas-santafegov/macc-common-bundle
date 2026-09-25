<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Macc\CommonBundle\Security\RolActionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Base abstract class for defining workflows via transition maps.
 */
abstract class AbstractWorkflow
{
    /**
     * @var array<class-string<WorkflowStateInterface>, array<<WorkflowActionInterface>,<WorkflowStateInterface>>>
     */
    protected array $transitions = [];

    public function __construct(
        protected AuthorizationCheckerInterface $authChecker,
        protected EntityManagerInterface $em,
        protected ?array $data = [], // Stored data for reuse in pre and post triggers
        protected bool $security = true,
    ) {}

    /**
     * Returns the next state for a given object and action.
     */
    public function getStateForAction(
        AbstractWorkflowObject $object,
        WorkflowActionInterface $action
    ): ?WorkflowStateInterface {
        $currentState = $object->getState();
        if (!$currentState) {
            return null;
        }

        $nextStateClass = $this->transitions[$currentState::class][$action::class] ?? null;
        return $nextStateClass ? new $nextStateClass() : null;
    }

    /**
     * Returns allowed actions for a given state.
     *
     * @return array<class-string<WorkflowActionInterface>>
     */
    public function actionsFor(WorkflowStateInterface $currentState): array
    {
        return array_keys($this->transitions[$currentState::class] ?? []);
    }

    /**
     * Executes a workflow transition if authorized and valid.
     */
    public function do(
        AbstractWorkflowObject $object,
        UserInterface|string $user,     // Usuario o un identificador directamente
        WorkflowActionInterface $action,
        ?object $initiator = null,
        object ...$contextObjects,
    ): AbstractWorkflowObject {
        $currentState = $object->getState();

        // 1 - Convert to string for lookup
        $stateKey = $currentState instanceof WorkflowStateInterface
            ? $currentState->value
            : (string) $currentState;

        $actionKey = $action instanceof WorkflowActionInterface
            ? $action->value
            : (string) $action;

        // 2 - Auth
        if ($this->security) {
            $granted = $action instanceof RolActionInterface
                ? $this->authChecker->isGranted($action->getRolFuncion()->value, $action->getRolGrupo())
                : $this->authChecker->isGranted($actionKey, $object);   // Se necesita la action como RolFuncion en BD

            if (!$granted) throw new AccessDeniedException(sprintf(
                "User '%s' is not authorized to perform action '%s'.",
                $user instanceof UserInterface ? $user->getUserIdentifier() : $user,
                $action->label()
            ));
        }

        // 3 - Validate transition
        if (
            !isset($this->transitions[$stateKey]) ||
            !isset($this->transitions[$stateKey][$actionKey])
        ) {
            throw new \LogicException(sprintf(
                "You can't perform '%s' from state '%s'.",
                $action->label(),
                $currentState->label() ?? 'NONE'
            ));
        }

        // 4 - Get new state
        $newStateValue = $this->transitions[$stateKey][$actionKey];

        if ($newStateValue === null) { // si no hay proximo estado, no cambiar
            return $object;
        }

        // 5 - Obtain an enum back
        $stateClass = $object->getStateClass();
        /** @var WorkflowStateInterface $newState */
        $newState = $stateClass::from($newStateValue);

        // 6 - Trigger pre transition hooks
        if (!$this->triggerPreTransitionHooks($object, $action, $newState, $user, $initiator, ...$contextObjects)) {
            return $object;
        }

        // 7 - Apply changes
        $object = $object->transition($user, $action, $newState, $initiator, ...$contextObjects);

        // 8 - Save data
        $this->em->persist($object);
        $this->em->flush();

        // 9 - Trigger post transition hooks
        $this->triggerPostTransitionHooks($object, $action, $newState, $user, $currentState, $initiator, ...$contextObjects);

        return $object;
    }

    /**
     * Returns if an action is valid to do in a given context.
     */
    public function canDo(AbstractWorkflowObject $object, WorkflowActionInterface $action): bool
    {
        $state = $object->getState()?->value ?? null;

        if($action->isWrapper()) {
            foreach ($action->getSubActions() as $subAction) {
                if ($this->canDo($object, $subAction)) {
                    return true;
                }
            }
            return false;
        }

        return
            isset($this->transitions[$state]) &&
            array_key_exists($action->value, $this->transitions[$state]);
    }

    /**
     * Optional hook for pre-transition hooks.
     * Return false to abort transition.
     */
    protected function triggerPreTransitionHooks(
        AbstractWorkflowObject $object,
        WorkflowActionInterface $action,
        WorkflowStateInterface $nextState,
        UserInterface|string $user,
        ?object $initiator = null,
        object ...$contextObjects,
    ): bool {
        return true;
    }

    /**
     * Optional hook for post-transition hooks.
     */
    protected function triggerPostTransitionHooks(
        AbstractWorkflowObject $object,
        WorkflowActionInterface $action,
        WorkflowStateInterface $currentState,
        UserInterface|string $user,
        ?WorkflowStateInterface $oldState,
        ?object $initiator = null,
        object ...$contextObjects,
    ): ?self
    {
        return $this;
    }

    public function getData()
    {
        return $this->data;

    }

    public function setData(array $data = [])
    {
        $this->data = $data;
        return $this;
    }

    public function getTransitions()
    {
        return $this->transitions;
    }

    public function getCurrentStateInitiator(AbstractWorkflowObject $object, ?string $searchClass = null): ?object
    {
        $currentHistory = $object->getHistories()->last();

        if (!$currentHistory) return null;

        return $this->getInitiator($currentHistory, $searchClass);
    }

    public function getInitiator(AbstractWorkflowObjectHistory $history, ?string $searchClass = null): ?object
    {
        $class = $history->getInitiatorType();
        $id = $history->getInitiatorId();

        if (!$class || !$id) {
            return null;
        }

        $entity = $this->em->getRepository($class)->find($id);

        if (!$entity) {
            return null;
        }

        // Si se pide filtrar por clase, descartamos la que no coincida
        if ($searchClass !== null && $class !== $searchClass) {
            return null;
        }

        return $entity;
    }

    /**
     * @param AbstractWorkflowObject $workflowObject
     * @return array<object>
     */
    public function getInitiators(AbstractWorkflowObject $workflowObject): array
    {
        /** @var array<int, null> $initiators */
        $initiators = [];

        foreach ($workflowObject->getHistories() as $history) {
            $initiator = $this->getInitiator($history);
            if ($initiator) $initiators[] = $this->getInitiator($history);
        }

        return $initiators;
    }

    public function getCurrentStateContext(AbstractWorkflowObject $object, ?string $searchClass = null): ?array
    {
        $currentHistory = $object->getHistories()->last();

        if (!$currentHistory) return null;

        return $this->getContext($currentHistory, $searchClass);
    }

    public function getContext(AbstractWorkflowObjectHistory $history, ?string $searchClass = null): ?array
    {
        $grouped = [];

        foreach ($history->getContext() as $context) {
            $class = $context['class'];
            $id = $context['id'];

            $entity = $this->em->getRepository($class)->find($id);

            if (!$entity) {
                continue;
            }

            // Si se pide filtrar por clase, salteamos las que no coincidan
            if ($searchClass !== null && $class !== $searchClass) {
                continue;
            }

            // Inicializar el array si no existe
            if (!isset($grouped[$class])) {
                $grouped[$class] = [];
            }

            $grouped[$class][] = $entity;
        }

        if ($searchClass !== null) {
            return $grouped[$searchClass] ?? [];
        }

        return $grouped ?: null;
    }
}

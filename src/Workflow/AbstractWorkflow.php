<?php

namespace Macc\CommonBundle\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        UserInterface $user,
        WorkflowActionInterface $action
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
        if ($this->security === true) {
            if (!$this->authChecker->isGranted($action->label(), $object)) {
                throw new \LogicException(sprintf(
                    "User '%s' is not authorized to perform action '%s'.",
                    $user->getUserIdentifier(),
                    $action->label()
                ));
            }
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
        if (!$this->triggerPreTransitionHooks($object, $action, $newState, $user)) {
            return $object;
        }

        // 7 - Apply changes
        $object = $object->transition($user, $action, $newState);

        // 8 - Save data
        $this->em->persist($object);
        $this->em->flush();

        // 9 - Trigger post transition hooks
        $this->triggerPostTransitionHooks($object, $action, $newState, $user, $currentState);

        return $object;
    }

    /**
     * Returns if an action is valid to do in a given context.
     */
    public function canDo(AbstractWorkflowObject $object,WorkflowActionInterface $action):bool
    {
        $state = $object->getState();
        if ($state && array_key_exists($state->label(),$this->transitions)) {
            /** @var array $validActions */
            $validActions = $this->transitions[$state->value];
            return array_key_exists($action->label(),$validActions);
        }
        return false;
    }

    /**
     * Optional hook for pre-transition hooks.
     * Return false to abort transition.
     */
    protected function triggerPreTransitionHooks(
        AbstractWorkflowObject $object,
        WorkflowActionInterface $action,
        WorkflowStateInterface $nextState,
        UserInterface $user
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
        UserInterface $user,
        ?WorkflowStateInterface $oldState= null,
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
}

<?php
/** @noinspection PhpUnused */

declare(strict_types=1);
/** @noinspection PhpUnused */

namespace ocallit\Util\OcStateMachine;

/*
3. change guards to methods and their return type is bool and

see https://mermaid.js.org/config/theming.html
---
config:
  theme: 'base'
  themeVariables:
    primaryColor: '#BB2528'
    primaryTextColor: '#fff'
    primaryBorderColor: '#7C0000'
    lineColor: '#F8B229'
    secondaryColor: '#006100'
    tertiaryColor: '#fff'
---

 classDiagram
    STATE_A <|-- STATE_B
    STATE_B --> STATE_C
    STATE_C --> STATE_D
    STATE_D --> STATE_A
    Animal <|--|> Zebra
    bar ()-- foo

        class STATE_A {
        <<Letter A>>
        🛡 ENTER can_enter_A, valid_para_A
        🛡 LEAVE can_exist_A
        ⚡  LEAVE a_left()
    }


    class Legend {
        <<📖 LEGEND>>
        🛡 Guards return bool
        🛡 No side effects allowed
        ⚡ Events ignore return value
        ⚡ Side effects expected
    }

Inheritance / generalization:
A <|-- B → B inherits from A.

Association (single arrow):
A --> B

Aggregation (hollow diamond):
A o-- B

Composition (filled diamond):
A *-- B

Dependency:
A ..> B

Realization / interface implementation:
A ..|> B

 */


/**
 * A state machine class, states are represented by:
 * $states = [
 * "stateId" => [
 * StateMachine::GUARD_ENTER => [{callable}],
 * self:TRANSITION_TO => [toId => [StateMachine::GUARD_TRANSITION => [{callable}], StateMachine::ON_TRANSITION => [{callable}]],
 * StateMachine::GUARD_LEAVE => [{callable}],
 * StateMachine::ON_ENTER => [{callable}],
 * StateMachine::ON_LEAVE => [{callable}],
 * ],
 * ]
 *
 * What is runned on moveTo state B while being in state A. only those with defined keys not null
 * (all are called with parameters: currentState, toState, luggage, action (leaveGuard,..., onBefore..)
 * 1. Rule: NO SIDE EFFECTS, if they return false or falsy stop and return false
 * moveToGuard
 * A.leaveGuards
 * A.transitionGuards["B"]
 * B.enterGuards
 *
 * 2. trigger the following and move the state. Rule: SIDE EFFECTS expected
 * onBeforeTransition() // runs on any state transition
 * A->B.onBeforeTransition()
 * A.onLeave
 * A->B.onTransition (Here currentState is changed to B)
 * B.onEnter
 * A.B.onAfterTransition()
 * onAfterTransition() // runs on any state transition
 *
 * Callable
 * 'My\Namespace\function_name', 'function_name',
 * [$object, 'method_name'], [$object, 'method_name'],
 * ['My\Namespace\ClassName', 'static_method_name'], ['ClassName', 'static_method_name'],
 */


/**
 * A finite state machine implementation supporting guarded transitions, event hooks and luggage.
 *
 * This state machine allows defining states with transitions between them, including:
 * - Guard conditions (enter, transition, leave) that must return true to allow state changes, no side effects
 * - Event callbacks (enter, transition, leave) that execute during state transitions, side effects expected return value ignored
 *
 * - Global transition hooks and guards
 * - Optional luggage data passed through all guards and callbacks
 *
 * States are defined as an array structure with transition rules and callbacks.
 * The machine validates transitions through guard functions before executing them.
 *
 * @package StateMachine
 */
class StateMachine {
    public const string LABEL = "LABEL";
    public const string GUARD_ENTER = "GUARD_ENTER";
    public const string GUARD_TRANSITION = "GUARD_TRANSITION";
    public const string GUARD_LEAVE = "GUARD_LEAVE";
    public const string TRANSITION_TO = "TRANSITION_TO";
    public const string ON_LEAVE = "ON_LEAVE";
    public const string ON_TRANSITION = "ON_TRANSITION";
    public const string ON_ENTER = "ON_ENTER";

    /**
     * An array of state definitions
     * Note: GUARD_*:boolean true proceed, false disallow. no side effects, vs
     *       ON_* side effects expected return value ignored
     *
     * @var array<string, array{
     *      GUARD_ENTER?: array<int|string, callable>,
     *      TRANSITION_TO?: array<string, array{
     *          GUARD_TRANSITION?: array<int|string, callable>,
     *          ON_TRANSITION?: array<int|string, callable>
     *      }>,
     *      GUARD_LEAVE?: array<int|string, callable>,
     *      ON_ENTER?: array<int|string, callable>,
     *      ON_LEAVE?: array<int|string, callable>,
     *      LABEL?: string
     * }>
     */
    protected array $states;
    protected string $currentState;
    protected mixed $luggage;


    /**
     * @var array<int|string, callable>
     */
    protected array $moveToGuard;

    /**
     * @var array<int|string, callable>
     */
    protected array $onBeforeTransition;

    /**
     * @var array<int|string, callable>
     */
    protected array $onAfterTransition;

    protected string $lastRejectionReason = "";

    public function getLastRejectionReason(): string {
        return $this->lastRejectionReason;
    }

    /**
     * @param array<string, array{
     *       GUARD_ENTER?: array<int|string, callable>,
     *       TRANSITION_TO?: array<string, array{
     *           GUARD_TRANSITION?: array<int|string, callable>,
     *           ON_TRANSITION?: array<int|string, callable>
     *       }>,
     *       GUARD_LEAVE?: array<int|string, callable>,
     *       ON_ENTER?: array<int|string, callable>,
     *       ON_LEAVE?: array<int|string, callable>,
     *       LABEL?: string
     *  }> $states
     * @param string $currentState
     * @param mixed $luggage
     * @param array<int|string, callable> $moveToGuard
     * @param array<int|string, callable> $onBeforeTransition
     * @param array<int|string, callable> $onAfterTransition
     */
    public function __construct(array $states, string $currentState, mixed $luggage = NULL, array $moveToGuard = [],
                                array $onBeforeTransition = [], array $onAfterTransition = []) {
        $this->states = $states;
        if(empty($currentState))
            $currentState = array_key_first($states);
        if(!array_key_exists($currentState, $states))
            $currentState = array_key_first($states);
        $this->currentState = $currentState;
        $this->luggage = $luggage;
        $this->moveToGuard = $moveToGuard;
        $this->onBeforeTransition = $onBeforeTransition;
        $this->onAfterTransition = $onAfterTransition;
    }

    /**
     * Attempts to transition to the specified state if allowed by guard conditions.
     *
     * Validates all applicable guards (global, leave, transition, enter) before proceeding GUARD_*
     * If any guard returns false, the transition is aborted and returns false. Expects no side effects from guards
     * Runs all callbacks ON_* ingnores returned values, side effects expected
     *
     * @param string $toState The target state identifier to transition to
     * @return bool True if transition was successful, false if blocked by guards or invalid transition
     */
    public function moveTo(string $toState): bool {
        // check if moving to $toState is valid and guards allow it
        if(!$this->moveToAllowed($toState))
            return FALSE;
        // move & trigger the events
        $this->move($toState);
        return TRUE;
    }

    /**
     * Checks if transitioning to the target state is allowed by all guard conditions.
     *
     * Validates in order: global moveTo guards, current state leave guards,
     * transition guards, and target state enter guards.
     *
     * @param string $toState The target state to validate transition for
     * @return bool True if all guards allow the transition, false otherwise
     */
    protected function moveToAllowed(string $toState): bool {
        $this->lastRejectionReason = "";
        $current = $this->states[$this->currentState] ?? "";

        // Check target state exists BEFORE running any guards
        if(!array_key_exists($toState, $this->states)) {
            $this->lastRejectionReason = "invalid State: $toState";
            return FALSE;
        }
        // path from $this->currentState to $toState is defined
        if(!array_key_exists($toState, $current[StateMachine::TRANSITION_TO] ?? [])) {
            $this->lastRejectionReason = "invalidToState: $toState currentState $this->currentState";
            return FALSE;
        }

        // check if guards exist and allows the move
        foreach($this->moveToGuard as $key => $guard)
            if(!$guard($this->currentState, $toState, $this->luggage, "moveToGuard", $this)) {
                $this->lastRejectionReason = "moveToGuard: " . $this->displayCallable($key, $guard);
                return FALSE;
            }
        foreach($current[StateMachine::GUARD_LEAVE] ?? [] as $key => $guard)
            if(!$guard($this->currentState, $toState, $this->luggage, StateMachine::GUARD_LEAVE, $this)) {
                $this->lastRejectionReason = StateMachine::GUARD_LEAVE . ": " . $this->displayCallable($key, $guard);
                return FALSE;
            }
        foreach($current[StateMachine::TRANSITION_TO][$toState][StateMachine::GUARD_TRANSITION] ?? [] as $key => $guard)
            if(!$guard($this->currentState, $toState, $this->luggage, StateMachine::GUARD_TRANSITION, $this)) {
                $this->lastRejectionReason = StateMachine::TRANSITION_TO . ":  " . $this->displayCallable($key, $guard);
                return FALSE;
            }
        foreach($this->states[$toState][StateMachine::GUARD_ENTER] ?? [] as $key => $guard)
            if(!$guard($this->currentState, $toState, $this->luggage, StateMachine::GUARD_ENTER, $this)) {
                $this->lastRejectionReason = StateMachine::GUARD_ENTER . ":  " . $this->displayCallable($key, $guard);
                return FALSE;
            }
        return TRUE;
    }

    /**
     * Executes the state transition and all associated event callbacks.
     *
     * Callback execution order:
     * 1. Global before-transition callbacks
     * 2. Current state ON_LEAVE callbacks
     * 3. Transition ON_TRANSITION callbacks
     * 4. Updates current state
     * 5. New state ON_ENTER callbacks
     * 6. Global after-transition callbacks
     *
     * @param string $toState The target state to transition to
     */
    protected function move(string $toState): void {

        $currentState = $this->currentState;

        foreach($this->onBeforeTransition as $on)
            $on($currentState, $toState, $this->luggage, "ON_BEFORE_TRANSITION", $this);

        $current = $this->states[$this->currentState];
        foreach($current[StateMachine::ON_LEAVE] ?? [] as $on)
            $on($this->currentState, $toState, $this->luggage, StateMachine::ON_LEAVE, $this);

        foreach($current[StateMachine::TRANSITION_TO][$toState][StateMachine::ON_TRANSITION] ?? [] as $on)
            $on($this->currentState, $toState, $this->luggage, StateMachine::TRANSITION_TO, $this);

        $this->currentState = $toState;

        foreach($this->states[$toState][StateMachine::ON_ENTER] ?? [] as $on)
            $on($this->currentState, $toState, $this->luggage, StateMachine::ON_ENTER, $this);

        foreach($this->onAfterTransition as $on)
            $on($currentState, $toState, $this->luggage, "ON_AFTER_TRANSITION", $this);

    }

    /**
     * Returns all valid states that can be transitioned to from the current state.
     *
     * @return array<string> Array of state identifiers that are valid transition targets
     */
    public function nextStates(): array {
        return array_keys($this->states[$this->currentState][StateMachine::TRANSITION_TO] ?? []);
    }

    /**
     * Retrieves the complete state machine configuration.
     *
     * return array<string, array{
     *      GUARD_ENTER?: array<int|string, callable>,
     *      TRANSITION_TO?: array<string, array{
     *          GUARD_TRANSITION?: array<int|string, callable>,
     *          ON_TRANSITION?: array<int|string, callable>
     *      }>,
     *      GUARD_LEAVE?: array<int|string, callable>,
     *      ON_ENTER?: array<int|string, callable>,
     *      ON_LEAVE?: array<int|string, callable>,
     *      LABEL?: string
     * }>
     */
    public function getStates(): array {
        return $this->states;
    }

    public function getCurrentState(): string {
        return $this->currentState;
    }

    public function getMoveToGuard(): array {
        return $this->moveToGuard;
    }

    public function getOnBeforeTransition(): array {
        return $this->onBeforeTransition;
    }

    public function getOnAfterTransition(): array {
        return $this->onAfterTransition;
    }

    /**
     * Directly sets the current state without triggering transitions or guards.
     *
     * Warning: This bypasses all guard conditions and transition callbacks.
     * Use only for initialization or exceptional cases where normal transition flow
     * should be circumvented.
     *
     * @param string $currentState The state to set as current
     * @return self
     */
    public function setCurrentState(string $currentState): self {
        $this->currentState = $currentState;
        return $this;
    }

    public function getLuggage(): mixed {
        return $this->luggage;
    }

    public function setLuggage(mixed $luggage): self {
        $this->luggage = $luggage;
        return $this;
    }

    protected function displayCallable(int|string $key, callable $callable):string {
        return "($key): " . $this->callableToString($callable);
    }

    protected function callableToString(callable $callable): string
    {
        if ($callable instanceof \Closure) {
            // Closures don't have names; we can only identify them as such
            return 'closure';
        }

        if (is_array($callable)) {
            // Could be [object, 'method'] or ['Class', 'method']
            $class = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];
            return "$class::$callable[1]";
        }

        if (is_string($callable)) {
            // Could be 'function_name' or 'Class::method'
            return $callable;
        }

        if (is_object($callable) && method_exists($callable, '__invoke')) {
            // Invokable object
            return get_class($callable) . '::__invoke';
        }

        // Fallback (should rarely happen)
        return 'callable(' . gettype($callable) . ')';
    }

}

<?php
/**
 * StateMachine.php - Developer API Cheat Sheet + Example (6 states with rework loop)
 *
 * Namespace: ocallit\Util\OcStateMachine
 * Class:     StateMachine
 *
 * ---------------------------------------------------------------------------
 * 1) What StateMachine implements (high-level)
 * ---------------------------------------------------------------------------
 * - Finite state machine with:
 *   - Per-state guards (enter/leave) and per-transition guards (A -> B) that
 *     MUST return bool and MUST have no side effects.
 *   - Per-state events (enter/leave) and per-transition events (A -> B) that
 *     MAY have side effects; return value ignored.
 *   - Optional global guards ($moveToGuard) that run for every transition.
 *   - Optional global hooks ($onBeforeTransition, $onAfterTransition) that run
 *     for every transition.
 *   - A "luggage" payload stored in the machine and passed to all guards/events.
 *
 * ---------------------------------------------------------------------------
 * 2) Developer API Cheat Sheet
 * ---------------------------------------------------------------------------
 *
 * Class constants (array keys used in config arrays):
 * - StateMachine::LABEL
 * - StateMachine::GUARD_ENTER
 * - StateMachine::GUARD_TRANSITION
 * - StateMachine::GUARD_LEAVE
 * - StateMachine::TRANSITION_TO
 * - StateMachine::ON_LEAVE
 * - StateMachine::ON_TRANSITION
 * - StateMachine::ON_ENTER
 *
 * State definition schema:
 * $states = [
 *   "STATE_ID" => [
 *     StateMachine::LABEL => "Optional label",
 *
 *     StateMachine::GUARD_ENTER => [callable, ...],
 *     StateMachine::GUARD_LEAVE => [callable, ...],
 *
 *     StateMachine::ON_ENTER => [callable, ...],
 *     StateMachine::ON_LEAVE => [callable, ...],
 *
 *     StateMachine::TRANSITION_TO => [
 *       "TARGET_STATE_ID" => [
 *         StateMachine::GUARD_TRANSITION => [callable, ...],
 *         StateMachine::ON_TRANSITION => [callable, ...],
 *       ],
 *     ],
 *   ],
 * ];
 *
 * Callable signature for guards & events:
 * function (string $currentState, string $toState, mixed $luggage, string $action) { ... }
 *
 * Guard rules:
 * - Must return bool.
 * - Must have no side effects.
 *
 * Event rules:
 * - Can have side effects.
 * - Return value ignored.
 *
 * Validation order when moveTo($toState) is called:
 * 1) Target state exists
 * 2) Transition exists (current[TRANSITION_TO] contains toState)
 * 3) Global guards ($moveToGuard)
 * 4) Current state leave guards (current[GUARD_LEAVE])
 * 5) Transition guards (current[TRANSITION_TO][toState][GUARD_TRANSITION])
 * 6) Target state enter guards (target[GUARD_ENTER])
 *
 * Execution order when a transition is allowed:
 * 1) Global before hooks ($onBeforeTransition) with action "ON_BEFORE_TRANSITION"
 * 2) Current state leave events (current[ON_LEAVE])
 * 3) Transition events (current[TRANSITION_TO][toState][ON_TRANSITION])
 * 4) Update current state
 * 5) Target state enter events (target[ON_ENTER])
 * 6) Global after hooks ($onAfterTransition) with action "ON_AFTER_TRANSITION"
 *
 * Public methods:
 * - __construct(array $states, string $currentState, mixed $luggage = NULL,
 *              array $moveToGuard = [], array $onBeforeTransition = [], array $onAfterTransition = [])
 * - moveTo(string $toState): bool
 * - nextStates(): array
 * - getStates(): array
 * - getCurrentState(): string
 * - setCurrentState(string $currentState): self   (direct setter; no guards/events)
 * - getLuggage(): mixed
 * - setLuggage(mixed $luggage): self
 * - getLastRejectionReason(): string
 *
 * ---------------------------------------------------------------------------
 * 3) Example: 6 states with a rework loop STEP_3 -> STEP_2
 * ---------------------------------------------------------------------------
 * Practical note: If you want events/guards to mutate luggage and persist, use
 * an object (e.g., stdClass) rather than an array.
 */

require_once __DIR__ . '/StateMachine.php'; // Adjust if needed.

use ocallit\Util\OcStateMachine\StateMachine;

$luggage = (object)[
    'step2Approved' => false,
    'needsRework'   => false,
];

$log = function(string $from, string $to, $luggage, string $action, StateMachine $sm): void {
    echo "[{$action}] {$from} -> {$to}\n";
};

$guardApprovedForStep3 = function(string $from, string $to, $luggage, string $action, StateMachine $sm): bool {
    // Only allow STEP_2 -> STEP_3 if STEP_2 was approved.
    return !empty($luggage->step2Approved);
};

$guardReworkOnlyWhenFlagged = function(string $from, string $to, $luggage, string $action, StateMachine $sm): bool {
    // Only allow STEP_3 -> STEP_2 when rework is needed.
    return !empty($luggage->needsRework);
};

$states = [
    'STEP_1' => [
        StateMachine::LABEL => 'Draft',
        StateMachine::TRANSITION_TO => [
            'STEP_2' => [
                StateMachine::ON_TRANSITION => [$log],
            ],
        ],
        StateMachine::ON_ENTER => [$log],
        StateMachine::ON_LEAVE => [$log],
    ],

    'STEP_2' => [
        StateMachine::LABEL => 'Review',
        StateMachine::TRANSITION_TO => [
            'STEP_3' => [
                StateMachine::GUARD_TRANSITION => [$guardApprovedForStep3],
                StateMachine::ON_TRANSITION => [$log],
            ],
        ],
        StateMachine::ON_ENTER => [$log],
        StateMachine::ON_LEAVE => [$log],
    ],

    'STEP_3' => [
        StateMachine::LABEL => 'Validation',
        StateMachine::TRANSITION_TO => [
            // Normal forward path
            'STEP_4' => [
                StateMachine::ON_TRANSITION => [$log],
            ],

            // Rework loop back to STEP_2
            'STEP_2' => [
                StateMachine::GUARD_TRANSITION => [$guardReworkOnlyWhenFlagged],
                StateMachine::ON_TRANSITION => [$log],
            ],
        ],
        StateMachine::ON_ENTER => [$log],
        StateMachine::ON_LEAVE => [$log],
    ],

    'STEP_4' => [
        StateMachine::LABEL => 'Packaging',
        StateMachine::TRANSITION_TO => [
            'STEP_5' => [
                StateMachine::ON_TRANSITION => [$log],
            ],
        ],
        StateMachine::ON_ENTER => [$log],
        StateMachine::ON_LEAVE => [$log],
    ],

    'STEP_5' => [
        StateMachine::LABEL => 'Shipping',
        StateMachine::TRANSITION_TO => [
            'STEP_6' => [
                StateMachine::ON_TRANSITION => [$log],
            ],
        ],
        StateMachine::ON_ENTER => [$log],
        StateMachine::ON_LEAVE => [$log],
    ],

    'STEP_6' => [
        StateMachine::LABEL => 'Done',
        StateMachine::TRANSITION_TO => [
            // no transitions out
        ],
        StateMachine::ON_ENTER => [$log],
    ],
];

// Optional global guards/hooks
$moveToGuard = [
    function(string $from, string $to, $luggage, string $action, StateMachine $sm): bool {
        // Example: disallow self-transitions.
        return $from !== $to;
    }
];

$onBefore = [
    function(string $from, string $to, $luggage, string $action, StateMachine $sm): void {
        echo "[{$action}] preparing {$from} -> {$to}\n";
    }
];

$onAfter = [
    function(string $from, string $to, $luggage, string $action, StateMachine $sm): void {
        echo "[{$action}] completed {$from} -> {$to}\n";
    }
];

$sm = new StateMachine($states, 'STEP_1', $luggage, $moveToGuard, $onBefore, $onAfter);

// Drive the workflow
$sm->moveTo('STEP_2'); // OK

// Attempt STEP_2 -> STEP_3 without approval (will fail)
if (!$sm->moveTo('STEP_3')) {
    echo "Rejected: " . $sm->getLastRejectionReason() . "\n";
}

// Approve STEP_2, then proceed
$luggage->step2Approved = true;
$sm->moveTo('STEP_3'); // OK

// Decide rework is needed at STEP_3, go back to STEP_2
$luggage->needsRework = true;
$sm->moveTo('STEP_2'); // OK (rework loop)

// Re-do STEP_2, proceed again
$luggage->needsRework = false;
$sm->moveTo('STEP_3'); // OK
$sm->moveTo('STEP_4'); // OK
$sm->moveTo('STEP_5'); // OK
$sm->moveTo('STEP_6'); // OK

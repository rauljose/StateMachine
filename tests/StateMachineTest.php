<?php

declare(strict_types=1);


use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A helper class to track callback executions for testing purposes.
 * An instance of this class can be used as the "luggage" to log which
 * callbacks are fired and in what order.
 */
class StateMachineCallbackTracker {
    public array $log = [];
    public bool $guardReturnValue = TRUE;

    /**
     * A callable method to be used as a guard or an action.
     * It logs the details of its execution.
     * For guards, it returns the value of $this->guardReturnValue.
     */
    public function track(string $from, string $to, mixed $luggage, string $action): bool {
        $this->log[] = [
          'action' => $action,
          'from' => $from,
          'to' => $to,
        ];
        return $this->guardReturnValue;
    }
}


#[CoversClass(StateMachine::class)]
final class StateMachineTest extends TestCase {
    #[Test]
    public function constructorAndSimpleGettersWorkCorrectly(): void {
        $states = ['S1' => []];
        $luggage = new \stdClass();

        $sm = new StateMachine($states, 'S1', $luggage);

        $this->assertSame($states, $sm->getStates());
        $this->assertSame('S1', $sm->getCurrentState());
        $this->assertSame($luggage, $sm->getLuggage());
    }

    #[Test]
    public function settersModifyStateAndLuggage(): void {
        $sm = new StateMachine(['S1' => []], 'S1');
        $luggage = (object)['data' => 'new luggage'];

        $sm->setCurrentState('NEW_STATE');
        $this->assertSame('NEW_STATE', $sm->getCurrentState());

        $sm->setLuggage($luggage);
        $this->assertSame($luggage, $sm->getLuggage());
    }

    #[Test]
    public function nextStatesReturnsCorrectTransitions(): void {
        $states = [
          'S1' => [
            StateMachine::TRANSITION_TO => [
              'S2' => [],
              'S3' => [],
            ],
          ],
          'S2' => [],
          'S3' => [
            StateMachine::TRANSITION_TO => [], // Empty transitions
          ],
          'S4' => [], // No TRANSITION_TO key
        ];

        $sm = new StateMachine($states, 'S1');
        $this->assertSame(['S2', 'S3'], $sm->nextStates());

        $sm->setCurrentState('S3');
        $this->assertSame([], $sm->nextStates());

        $sm->setCurrentState('S4');
        $this->assertSame([], $sm->nextStates());
    }

    #[Test]
    public function moveToFailsIfTransitionIsNotDefined(): void {
        $states = [
          'S1' => [
            StateMachine::TRANSITION_TO => ['S2' => []],
          ],
          'S2' => [],
        ];

        $sm = new StateMachine($states, 'S1');

        $this->assertFalse($sm->moveTo('S3'), 'Should fail moving to an undefined transition');
        $this->assertSame('S1', $sm->getCurrentState(), 'State should not change on failed transition');
    }

    #[Test]
    public function moveToFailsIfTargetStateIsNotDefined(): void {
        // Note: 'S2' is a valid transition target from 'S1', but 'S2' itself is not a defined state.
        $states = [
          'S1' => [
            StateMachine::TRANSITION_TO => ['S2' => []],
          ],
        ];

        $sm = new StateMachine($states, 'S1');

        // This fails at the GUARD_ENTER step because the state doesn't exist to check for guards.
        $this->assertFalse($sm->moveTo('S2'), 'Should fail moving to a state that is not defined');
        $this->assertSame('S1', $sm->getCurrentState(), 'State should not change');
    }

    #[Test]
    public function moveToFailsOnGlobalMoveToGuard(): void {
        $tracker = new StateMachineCallbackTracker();
        $tracker->guardReturnValue = FALSE; // Make this guard fail

        $states = [
          'S1' => [StateMachine::TRANSITION_TO => ['S2' => []]],
          'S2' => [],
        ];

        $sm = new StateMachine($states, 'S1', $tracker, moveToGuard: [[$tracker, 'track']]);

        $this->assertFalse($sm->moveTo('S2'));
        $this->assertSame('S1', $sm->getCurrentState(), 'State should not have changed');
        $this->assertCount(1, $tracker->log, 'Only the failing guard should have been called');
        $this->assertSame('moveToGuard', $tracker->log[0]['action']);
    }

    #[Test]
    public function moveToFailsOnLeaveGuard(): void {
        $tracker = new StateMachineCallbackTracker();
        $failingGuard = new StateMachineCallbackTracker();
        $failingGuard->guardReturnValue = FALSE;

        $states = [
          'S1' => [
            StateMachine::GUARD_LEAVE => [[$failingGuard, 'track']],
            StateMachine::TRANSITION_TO => ['S2' => []],
          ],
          'S2' => [],
        ];

        $sm = new StateMachine($states, 'S1', $tracker);

        $this->assertFalse($sm->moveTo('S2'));
        $this->assertSame('S1', $sm->getCurrentState());
        $this->assertCount(1, $failingGuard->log, 'The failing guard should be called');
        $this->assertSame(StateMachine::GUARD_LEAVE, $failingGuard->log[0]['action']);
        $this->assertEmpty($tracker->log, 'No other callbacks should have been triggered');
    }

    #[Test]
    public function moveToFailsOnTransitionGuard(): void {
        $tracker = new StateMachineCallbackTracker();
        $failingGuard = new StateMachineCallbackTracker();
        $failingGuard->guardReturnValue = FALSE;

        $states = [
          'S1' => [
            StateMachine::TRANSITION_TO => [
              'S2' => [
                StateMachine::GUARD_TRANSITION => [[$failingGuard, 'track']],
              ],
            ],
          ],
          'S2' => [],
        ];

        $sm = new StateMachine($states, 'S1', $tracker);

        $this->assertFalse($sm->moveTo('S2'));
        $this->assertSame('S1', $sm->getCurrentState());
        $this->assertCount(1, $failingGuard->log);
        $this->assertSame(StateMachine::GUARD_TRANSITION, $failingGuard->log[0]['action']);
        $this->assertEmpty($tracker->log);
    }

    #[Test]
    public function moveToFailsOnEnterGuard(): void {
        $tracker = new StateMachineCallbackTracker();
        $failingGuard = new StateMachineCallbackTracker();
        $failingGuard->guardReturnValue = FALSE;

        $states = [
          'S1' => [
            StateMachine::TRANSITION_TO => ['S2' => []],
          ],
          'S2' => [
            StateMachine::GUARD_ENTER => [[$failingGuard, 'track']],
          ],
        ];

        $sm = new StateMachine($states, 'S1', $tracker);

        $this->assertFalse($sm->moveTo('S2'));
        $this->assertSame('S1', $sm->getCurrentState());
        $this->assertCount(1, $failingGuard->log);
        $this->assertSame(StateMachine::GUARD_ENTER, $failingGuard->log[0]['action']);
        $this->assertEmpty($tracker->log);
    }

    #[Test]
    public function moveToSuccessfulTransitionAndCallbackOrder(): void {
        $tracker = new StateMachineCallbackTracker();

        $states = [
          'S1' => [
            StateMachine::GUARD_LEAVE => [[$tracker, 'track']],
            StateMachine::ON_LEAVE => [[$tracker, 'track']],
            StateMachine::TRANSITION_TO => [
              'S2' => [
                StateMachine::GUARD_TRANSITION => [[$tracker, 'track']],
                StateMachine::ON_TRANSITION => [[$tracker, 'track']],
              ],
            ],
          ],
          'S2' => [
            StateMachine::GUARD_ENTER => [[$tracker, 'track']],
            StateMachine::ON_ENTER => [[$tracker, 'track']],
          ],
        ];

        $sm = new StateMachine(
          states: $states,
          currentState: 'S1',
          luggage: $tracker,
          moveToGuard: [[$tracker, 'track']],
          onBeforeTransition: [[$tracker, 'track']],
          onAfterTransition: [[$tracker, 'track']]
        );

        $this->assertTrue($sm->moveTo('S2'), 'Transition should be successful');
        $this->assertSame('S2', $sm->getCurrentState(), 'Current state should be updated to S2');

        $expectedLogOrder = [
            // 1. Guards (no side effects)
          ['action' => 'moveToGuard', 'from' => 'S1', 'to' => 'S2'],
          ['action' => StateMachine::GUARD_LEAVE, 'from' => 'S1', 'to' => 'S2'],
          ['action' => StateMachine::GUARD_TRANSITION, 'from' => 'S1', 'to' => 'S2'],
          ['action' => StateMachine::GUARD_ENTER, 'from' => 'S1', 'to' => 'S2'],
            // 2. Actions (side effects)
          ['action' => 'ON_BEFORE_TRANSITION', 'from' => 'S1', 'to' => 'S2'],
          ['action' => StateMachine::ON_LEAVE, 'from' => 'S1', 'to' => 'S2'],
          ['action' => StateMachine::TRANSITION_TO, 'from' => 'S1', 'to' => 'S2'],
            // STATE OFFICIALLY CHANGES HERE
          ['action' => StateMachine::ON_ENTER, 'from' => 'S2', 'to' => 'S2'], // from state is now S2
          ['action' => 'ON_AFTER_TRANSITION', 'from' => 'S1', 'to' => 'S2'], // from state is the original
        ];

        // A small correction for the ON_ENTER action: the `$currentState` parameter is the *new* state.
        $log = $tracker->log;
        $log[7]['from'] = $sm->getCurrentState(); // The `from` parameter in the onEnter callback is the new state

        $this->assertEquals($expectedLogOrder, $tracker->log);
    }
}
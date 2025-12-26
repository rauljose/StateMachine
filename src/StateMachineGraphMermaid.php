<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace ocallit\Util\OcStateMachine;

/**
 * Generates Mermaid class diagrams from StateMachine instances
 */
class StateMachineGraphMermaid {
    protected StateMachine $stateMachine;
    protected string $title;
    protected string $notes;

    public function __construct(StateMachine $stateMachine, string $title, string $notes = "") {
        $this->stateMachine = $stateMachine;
        $this->title = $title;
        $this->notes = $notes;
    }

    /**
     * Generates a mermaid class diagram string
     *
     * @return string The mermaid diagram markup
     */
    public function generate(): string {
        $states = $this->stateMachine->getStates();
        $currentState = $this->stateMachine->getCurrentState();

        $mermaid = "";

        // Add title using frontmatter if provided
        if(!empty($this->title)) {
            $mermaid .= "---\n";
            $mermaid .= "title: $this->title\n";
            $mermaid .= "---\n\n";
        }

        $mermaid .= "classDiagram\n";

        // Add subtitle with date/time
        $datetime = date('Y-m-d H:i:s');
        $mermaid .= "    note \"Generated: $datetime\"\n\n";

        // Add custom notes if provided
        if(!empty($this->notes)) {
            $escapedNotes = str_replace('"', '\\"', $this->notes);
            $mermaid .= "    note \"$escapedNotes\"\n\n";
        }

        // Add icon legend
        $mermaid .= "    note \"Legend:\\n🛡 Guard functions (return bool, no side effects)\\n⚡ Event handlers (return value ignored, side effects expected)\"\n\n";

        // Generate state relationships (transitions)
        $mermaid .= $this->generateTransitions($states);
        $mermaid .= "\n";

        // Generate state class definitions
        $mermaid .= $this->generateStateClasses($states, $currentState);

        // Generate global callbacks class if any exist
        $mermaid .= $this->generateGlobalCallbacksClass();

        // Highlight current state
        $mermaid .= $this->highlightCurrentState($currentState);

        return $mermaid;
    }

    /**
     * Generates the transition relationships between states
     */
    protected function generateTransitions(array $states): string {
        $transitions = "";

        foreach($states as $stateId => $stateConfig) {
            $transitionsTo = $stateConfig[StateMachine::TRANSITION_TO] ?? [];

            foreach($transitionsTo as $toStateId => $transitionConfig) {
                $transitions .= "    $stateId --> $toStateId\n";
            }
        }

        return $transitions;
    }

    /**
     * Generates class definitions for each state
     */
    protected function generateStateClasses(array $states, string $currentState): string {
        $classes = "";

        foreach($states as $stateId => $stateConfig) {
            $classes .= $this->generateStateClass($stateId, $stateConfig, $stateId === $currentState);
        }

        return $classes;
    }

    /**
     * Generates a single state class definition
     */
    protected function generateStateClass(string $stateId, array $stateConfig, bool $isCurrent = FALSE): string {
        $class = "    class $stateId {\n";

        // Add label if exists, with current state indicator
        $label = $stateConfig[StateMachine::LABEL] ?? $stateId;
        if($isCurrent) {
            $label = "🟢 CURRENT: $label";
        }
        $class .= "        <<$label>>\n";

        // Add guards and callbacks
        $class .= $this->generateCallbackSection('🛡 ENTER', $stateConfig[StateMachine::GUARD_ENTER] ?? []);
        $class .= $this->generateCallbackSection('🛡 LEAVE', $stateConfig[StateMachine::GUARD_LEAVE] ?? []);
        $class .= $this->generateCallbackSection('⚡ENTER', $stateConfig[StateMachine::ON_ENTER] ?? []);
        $class .= $this->generateCallbackSection('⚡LEAVE', $stateConfig[StateMachine::ON_LEAVE] ?? []);

        // Add transition-specific callbacks
        $transitionsTo = $stateConfig[StateMachine::TRANSITION_TO] ?? [];
        foreach($transitionsTo as $toStateId => $transitionConfig) {
            $guardTransitions = $transitionConfig[StateMachine::GUARD_TRANSITION] ?? [];
            $onTransitions = $transitionConfig[StateMachine::ON_TRANSITION] ?? [];

            if(!empty($guardTransitions)) {
                $class .= $this->generateCallbackSection("🛡 TO_$toStateId", $guardTransitions);
            }

            if(!empty($onTransitions)) {
                $class .= $this->generateCallbackSection("⚡ TO_$toStateId", $onTransitions);
            }
        }

        $class .= "    }\n\n";

        return $class;
    }

    /**
     * Generates a callback section (guards or event handlers)
     */
    protected function generateCallbackSection(string $sectionName, array $callbacks): string {
        if(empty($callbacks)) {
            return "";
        }

        $section = "        $sectionName";

        if(count($callbacks) === 1) {
            $callbackName = $this->getCallbackName(reset($callbacks));
            $section .= " $callbackName\n";
        } else {
            $section .= "\n";
            foreach($callbacks as $callback) {
                $callbackName = $this->getCallbackName($callback);
                $section .= "            $callbackName\n";
            }
        }

        return $section;
    }

    /**
     * Extracts a readable name from a callable
     */
    protected function getCallbackName(callable $callback): string {
        if(is_string($callback)) {
            // Function name or 'Class::method'
            return $callback;
        }

        if(is_array($callback) && count($callback) === 2) {
            [$object, $method] = $callback;

            if(is_object($object)) {
                $className = get_class($object);
                return "$className::$method";
            }

            if(is_string($object)) {
                // Static method call
                return "$object::$method";
            }
        }
        return "inline func";
    }

    /**
     * Generates global callbacks class if any global callbacks exist
     */
    protected function generateGlobalCallbacksClass(): string {
        $hasGlobalCallbacks = FALSE;
        $class = "    class GlobalCallbacks {\n";
        $class .= "        <<Always>>\n";

        // Check for global moveToGuard callbacks
        $moveToGuards = $this->stateMachine->getMoveToGuard();
        if(!empty($moveToGuards)) {
            $hasGlobalCallbacks = TRUE;
            $class .= $this->generateCallbackSection('🛡 MOVE_TO', $moveToGuards);
        }

        // Check for global onBeforeTransition callbacks
        $onBeforeTransition = $this->stateMachine->getOnBeforeTransition();
        if(!empty($onBeforeTransition)) {
            $hasGlobalCallbacks = TRUE;
            $class .= $this->generateCallbackSection('⚡BEFORE', $onBeforeTransition);
        }

        // Check for global onAfterTransition callbacks
        $onAfterTransition = $this->stateMachine->getOnAfterTransition();
        if(!empty($onAfterTransition)) {
            $hasGlobalCallbacks = TRUE;
            $class .= $this->generateCallbackSection('⚡AFTER', $onAfterTransition);
        }

        $class .= "    }\n\n";

        return $hasGlobalCallbacks ? $class : "";
    }

    /**
     * Highlights the current state with CSS styling
     */
    protected function highlightCurrentState(string $currentState): string {
        return "    class $currentState currentState\n" .
          "    classDef currentState fill:#90EE90,stroke:#006400,stroke-width:3px,color:#000000\n";
    }

}

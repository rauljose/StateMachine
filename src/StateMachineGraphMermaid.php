<?php

declare(strict_types=1);

namespace ocallit\Util\OcStateMachine;

class StateMachineMermaid {
    protected StateMachine $sm;
    protected array $idMap = [];
    protected string $idCounter = 'A';

    public function __construct(StateMachine $sm) {
        $this->sm = $sm;
    }

    /**
     * Generates Mermaid code using HTML labels for better formatting.
     * *
     * @param bool $nextStepsOnly
     * @param array $stateNotes External history logs: ['STATE_KEY' => 'Log info...']
     * @return string
     */
    public function generate(bool $nextStepsOnly = FALSE, array $stateNotes = []): string {
        $this->idMap = [];
        $this->idCounter = 'A';

        $states = $this->sm->getStates();
        $current = $this->sm->getCurrentState();

        // 1. Determine which states to render
        $renderStates = $states;
        $edgesFrom = array_keys($states);

        if($nextStepsOnly) {
            $targets = array_keys($states[$current][StateMachine::TRANSITION_TO] ?? []);
            $renderStates = [$current => $states[$current]];
            foreach($targets as $t) {
                if(isset($states[$t])) $renderStates[$t] = $states[$t];
            }
            $edgesFrom = [$current];
        }

        $out = "stateDiagram-v2\n    direction LR\n";

        // 2. Generate Nodes with HTML Labels
        foreach($renderStates as $sid => $cfg) {
            $short = $this->getId($sid);

            // --- LABEL LOGIC ---
            // If LABEL is set, use it. Otherwise, use the Array Key (SID).
            $mainLabel = $cfg[StateMachine::LABEL] ?? $sid;

            // Start building HTML string
            // We use specific styles for the Header vs Details
            $html = "<b>$mainLabel</b>";

            // Add Guards (Only show for current/next steps to reduce clutter, or all if preferred)
            if((!$nextStepsOnly || $sid === $current) && !empty($cfg[StateMachine::GUARD_ENTER])) {
                $guards = $this->fmtList($cfg[StateMachine::GUARD_ENTER]);
                $html .= "<br/>🛡 <i>$guards</i>";
            }

            // Add External Notes (Logs)
            if(isset($stateNotes[$sid])) {
                $note = nl2br(htmlspecialchars($stateNotes[$sid])); // Convert PHP newlines to HTML <br>
                $html .= "<hr/>📝 <span style='font-size:0.9em'>$note</span>";
            }

            // Clean up: Remove double quotes from the final HTML string to avoid syntax errors
            $safeHtml = str_replace('"', "'", $html);

            // Output state with Description
            $out .= sprintf('    state "%s" as %s' . "\n", $safeHtml, $short);
        }

        // 3. Generate Edges
        foreach($edgesFrom as $from) {
            foreach($states[$from][StateMachine::TRANSITION_TO] ?? [] as $to => $edge) {
                if(!isset($renderStates[$to])) continue;

                $fromId = $this->getId($from);
                $toId = $this->getId($to);
                $text = "";

                // Add Transition Guards/Logic to the arrow
                if(!empty($edge[StateMachine::GUARD_TRANSITION])) {
                    $guardList = $this->fmtList($edge[StateMachine::GUARD_TRANSITION]);
                    $text = ": " . str_replace('"', "'", $guardList);
                } elseif(!empty($edge[StateMachine::LABEL])) {
                    // If the transition itself has a label (e.g., "Request Changes")
                    $text = ": " . str_replace('"', "'", $edge[StateMachine::LABEL]);
                }

                $out .= "    $fromId --> $toId $text\n";
            }
        }

        // 4. Highlight Current State
        $curId = $this->getId($current);
        // Using a CSS class style for better visuals
        $out .= "    style $curId fill:#ffdfba,stroke:#ff8c00,stroke-width:2px,color:#000\n";

        return $out;
    }

    protected function getId(string $id): string {
        return $this->idMap[$id] ??= 's_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $id);
    }

    protected function fmtList(array $items): string {
        $out = [];
        foreach($items as $k => $v) {
            // If the key is a string (e.g., 'guardName' => closure), use the key.
            // If the value is a string (e.g., 'functionName'), use the value.
            $out[] = is_string($k) ? $k : (is_string($v) ? $v : 'Function');
        }
        return implode(", ", $out);
    }
}

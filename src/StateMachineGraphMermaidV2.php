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
     * Generates Mermaid code.
     * @param bool $nextStepsOnly
     * @param array $stateNotes Associative array ['STATE_ID' => 'Log message or note']
     * Mermaid strictly limits formatting here. Use \n for newlines.
     * HTML tags are generally NOT supported inside state labels.
     */
    public function generate(bool $nextStepsOnly = FALSE, array $stateNotes = []): string {
        $this->idMap = [];
        $this->idCounter = 'A';

        $states = $this->sm->getStates();
        $current = $this->sm->getCurrentState();

        // Filter logic
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

        // Generate Nodes
        foreach($renderStates as $sid => $cfg) {
            $short = $this->getId($sid);
            $label = $cfg[StateMachine::LABEL] ?? $sid;

            // 1. Add Details (Guards)
            if((!$nextStepsOnly || $sid === $current) && !empty($cfg[StateMachine::GUARD_ENTER])) {
                $label .= "\n🛡 " . $this->fmtList($cfg[StateMachine::GUARD_ENTER]);
            }

            // 2. Add Custom Notes (Log Entries)
            if(isset($stateNotes[$sid])) {
                // Mermaid creates new lines with \n
                $note = $stateNotes[$sid];
                // Clean up note to prevent breaking syntax
                $safeNote = str_replace(["\r", "\n", '"'], ["", "\\n", "'"], $note);
                $label .= "\n📝 " . $safeNote;
            }

            // Remove double quotes for Mermaid alias syntax
            $safeLabel = str_replace('"', '', $label);
            $out .= sprintf('    state "%s" as %s' . "\n", $safeLabel, $short);
        }

        // Generate Edges
        foreach($edgesFrom as $from) {
            foreach($states[$from][StateMachine::TRANSITION_TO] ?? [] as $to => $edge) {
                if(!isset($renderStates[$to])) continue;

                $fromId = $this->getId($from);
                $toId = $this->getId($to);
                $text = "";
                if(!empty($edge[StateMachine::GUARD_TRANSITION])) {
                    $text = ": " . $this->fmtList($edge[StateMachine::GUARD_TRANSITION]);
                }
                $out .= "    $fromId --> $toId $text\n";
            }
        }

        $curId = $this->getId($current);
        $out .= "    style $curId fill:#f96,stroke:#333,stroke-width:2px\n";

        return $out;
    }

    protected function getId(string $id): string {
        return $this->idMap[$id] ??= $this->idCounter++;
    }

    protected function fmtList(array $items): string {
        $out = [];
        foreach($items as $k => $v) $out[] = is_string($k) ? $k : (is_string($v) ? $v : 'Func');
        return implode(", ", $out);
    }
}

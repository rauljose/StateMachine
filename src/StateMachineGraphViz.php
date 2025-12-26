<?php

declare(strict_types=1);

namespace ocallit\Util\OcStateMachine;

class StateMachineViz {
    protected StateMachine $sm;

    public function __construct(StateMachine $sm) {
        $this->sm = $sm;
    }

    /**
     * Generates GraphViz DOT code using HTML-Like Labels.
     * * @param bool $nextStepsOnly
     * @param array $stateNotes Associative array ['STATE_ID' => 'HTML Content']
     * * CAPABILITIES:
     * - Anchors: <href>Click Me</href> (Note: GraphViz uses 'href', not 'a')
     * - Images: <img src="path/to/icon.png"/>
     * - Formatting: <b>Bold</b>, <i>Italic</i>, <font color="red">Color</font>
     * - Tables: You can pass full <table> structures in the notes.
     */
    public function generate(bool $nextStepsOnly = FALSE, array $stateNotes = []): string {
        $states = $this->sm->getStates();
        $current = $this->sm->getCurrentState();

        // Filter Logic
        $renderStates = $states;
        $renderEdgesFrom = array_keys($states);

        if($nextStepsOnly) {
            $targets = array_keys($states[$current][StateMachine::TRANSITION_TO] ?? []);
            $renderStates = [$current => $states[$current]];
            foreach($targets as $t) {
                if(isset($states[$t])) $renderStates[$t] = $states[$t];
            }
            $renderEdgesFrom = [$current];
        }

        $out = "digraph G {\n";
        $out .= "  graph [rankdir=LR, nodesep=0.5, ranksep=0.7];\n";
        // Note: shape=plain is required for HTML tables to look right
        $out .= "  node [shape=plain, fontname=\"Segoe UI\", fontsize=12];\n";
        $out .= "  edge [fontname=\"Segoe UI\", fontsize=10];\n";

        foreach($renderStates as $sid => $cfg) {
            $label = $cfg[StateMachine::LABEL] ?? $sid;

            // Start HTML Table
            // CELLBORDER=1 simulates the lines of the 'record' shape
            $html = '<table border="0" cellborder="1" cellspacing="0" cellpadding="4">';

            // 1. Title Row
            // Current state gets a colored background
            $bgColor = ($sid === $current) ? ' bgcolor="#FFF3B0"' : '';
            $html .= sprintf('<tr><td%s><b>%s</b></td></tr>', $bgColor, htmlspecialchars($label));

            // 2. Details (Guards/Actions)
            if(!$nextStepsOnly || $sid === $current) {
                $guards = array_merge($cfg[StateMachine::GUARD_ENTER] ?? [], $cfg[StateMachine::GUARD_LEAVE] ?? []);
                if($guards) {
                    $html .= sprintf('<tr><td align="left">🛡 %s</td></tr>', $this->fmtList($guards));
                }

                $actions = array_merge($cfg[StateMachine::ON_ENTER] ?? [], $cfg[StateMachine::ON_LEAVE] ?? []);
                if($actions) {
                    $html .= sprintf('<tr><td align="left">⚡ %s</td></tr>', $this->fmtList($actions));
                }
            }

            // 3. Custom Notes (Rich HTML Supported Here)
            if(isset($stateNotes[$sid])) {
                // We do NOT escape this, trusting the user provided valid GraphViz HTML
                // Example: 'See <href="http://google.com">Docs</href>'
                $html .= sprintf('<tr><td align="left">%s</td></tr>', $stateNotes[$sid]);
            }

            $html .= '</table>';

            // Wrap in < ... > for GraphViz HTML mode
            $out .= sprintf('  "%s" [label=<%s>];' . "\n", $sid, $html);
        }

        // Edges (Same as before)
        foreach($renderEdgesFrom as $from) {
            foreach($states[$from][StateMachine::TRANSITION_TO] ?? [] as $to => $edge) {
                if(!isset($renderStates[$to])) continue;

                $text = "";
                if(!empty($edge[StateMachine::GUARD_TRANSITION])) {
                    $text = "🛡 " . $this->fmtList($edge[StateMachine::GUARD_TRANSITION]);
                }
                // Escaping is critical for edge labels as they are still strings
                $out .= sprintf('  "%s" -> "%s" [label="%s"];' . "\n", $from, $to, $text);
            }
        }

        $out .= "}\n";
        return $out;
    }

    protected function fmtList(array $items): string {
        $out = [];
        foreach($items as $k => $v) $out[] = is_string($k) ? $k : (is_string($v) ? $v : 'Func');
        // HTML safe
        return htmlspecialchars(implode(", ", $out));
    }
}

<?php

declare(strict_types=1);

namespace ocallit\Util\OcStateMachine;

// ---------------------------------------------------------
// 1. INCLUDES
// ---------------------------------------------------------
require_once 'StateMachine.php';
require_once 'StateMachineGraphViz.php';

// ---------------------------------------------------------
// 2. CONFIGURATION
// ---------------------------------------------------------

$guardHighQuality = fn($c, $t, $l, $stateMachine) => ($l['score'] ?? 0) > 80;
$guardIsAdmin     = fn($c, $t, $l, $stateMachine) => ($l['role'] ?? '') === 'admin';

$states = [
    'DRAFT' => [
        StateMachine::LABEL => 'Draft 📝',
        StateMachine::TRANSITION_TO => [
            'REVIEW' => []
        ]
    ],
    'REVIEW' => [
        StateMachine::LABEL => 'Under Review 🧐',
        StateMachine::GUARD_ENTER => ['checkPlagiarism'],
        StateMachine::TRANSITION_TO => [
            'DRAFT'    => [StateMachine::LABEL => 'Request Changes'],
            'APPROVAL' => [
                StateMachine::GUARD_TRANSITION => ['highQualityOnly' => $guardHighQuality],
                StateMachine::LABEL => 'Promote'
            ]
        ]
    ],
    'APPROVAL' => [
        StateMachine::LABEL => 'Board Approval ⚖️',
        StateMachine::TRANSITION_TO => [
            'PUBLISHED' => [
                StateMachine::GUARD_TRANSITION => ['adminOnly' => $guardIsAdmin]
            ],
            'ARCHIVED'  => []
        ]
    ],
    'PUBLISHED' => [
        StateMachine::LABEL => 'Live 🚀',
        StateMachine::ON_ENTER => ['sendNotifications'],
        StateMachine::TRANSITION_TO => [
            'ARCHIVED' => []
        ]
    ],
    'ARCHIVED' => [
        StateMachine::LABEL => 'Archived 🗄️',
        StateMachine::TRANSITION_TO => []
    ]
];

// ---------------------------------------------------------
// 3. GENERATION
// ---------------------------------------------------------

$context = ['score' => 85, 'role' => 'editor'];
$sm = new StateMachine($states, 'REVIEW', $context);

// Log Notes for Visualization
$stateNotes = [
    'DRAFT' => '<i>Author:</i> John Doe<br/><i>Created:</i> 2023-10-01',
    'REVIEW' => '<font color="red"><b>Current Step</b></font><br/>Waiting on Board',
    'PUBLISHED' => 'Target: 2023-11-01'
];

$viz = new StateMachineViz($sm);
$dotCode = $viz->generate(false, $stateNotes);

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>StateMachine (Viz.js)</title>
    <style>
        body { font: 14px/1.4 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px; background: #f4f4f9; height: 100vh; box-sizing: border-box; display: flex; flex-direction: column; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        h1 { margin: 0; color: #333; font-size: 1.5rem; }

        /* Controls */
        .controls { display: flex; gap: 10px; background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        button { cursor: pointer; padding: 6px 12px; background: #e0e0e0; border: none; border-radius: 4px; font-weight: 600; transition: background 0.2s; }
        button:hover { background: #d0d0d0; }
        button.active { background: #BB2528; color: white; }

        /* The Graph Container */
        #stage {
            flex: 1;
            border: 1px solid #ccc;
            border-radius: 8px;
            background: white;
            overflow: hidden; /* SVG Pan Zoom handles scrolling */
            position: relative;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        #stage svg { width: 100%; height: 100%; }

        /* Debug Textarea */
        details { margin-top: 10px; }
        textarea { width: 100%; height: 150px; font-family: monospace; font-size: 12px; border: 1px solid #ccc; border-radius: 4px; padding: 10px; box-sizing: border-box;}
    </style>

    <script src="https://cdn.jsdelivr.net/npm/@viz-js/viz@3.7.0/lib/viz-standalone.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/svg-pan-zoom@3.6.1/dist/svg-pan-zoom.min.js"></script>
</head>
<body>

<div class="header">
    <h1>Document Workflow: <span><?php echo $sm->getCurrentState(); ?></span></h1>

    <div class="controls">
        <button id="btnOrient">Rotate (TB/LR)</button>
        <button id="btnPZ" class="active">Pan/Zoom: ON</button>
        <button id="btnReset">Reset View</button>
    </div>
</div>

<div id="stage"></div>

<details>
    <summary>🛠️ Debug DOT Source</summary>
    <textarea id="dot" spellcheck="false"><?php echo htmlspecialchars($dotCode); ?></textarea>
</details>

<script>
    let viz = null;
    let pz = null;
    let vertical = false;
    let pzEnabled = true;

    // 1. Helper: Modifies DOT string for orientation
    function applyFilters(dot) {
        if (vertical) {
            return dot.replace(/rankdir\s*=\s*LR/i, 'rankdir=TB');
        } else {
            // Ensure LR is set if not present or replace TB
            if(dot.match(/rankdir\s*=\s*TB/i)) {
                return dot.replace(/rankdir\s*=\s*TB/i, 'rankdir=LR');
            }
            return dot;
        }
    }

    // 2. Main Render Function
    async function render() {
        if (!viz) viz = await Viz.instance();

        const src = document.getElementById('dot').value;
        const dot = applyFilters(src);
        const stage = document.getElementById('stage');

        try {
            // Render to SVG Element
            const svgEl = await viz.renderSVGElement(dot);

            // Clear stage and append new SVG
            stage.replaceChildren(svgEl);

            // Make responsive (remove fixed dimensions)
            svgEl.removeAttribute('width');
            svgEl.removeAttribute('height');
            svgEl.style.width = "100%";
            svgEl.style.height = "100%";

            // Re-apply PanZoom if enabled
            if (pzEnabled) enablePanZoom();

        } catch (e) {
            stage.innerHTML = `<div style="color:red; padding:20px;">Render Error: ${e}</div>`;
            console.error(e);
        }
    }

    function enablePanZoom() {
        const svg = document.querySelector('#stage svg');
        if (!svg) return;

        // Destroy existing instance if any
        if (pz) pz.destroy();

        pz = svgPanZoom(svg, {
            zoomEnabled: true,
            controlIconsEnabled: true,
            fit: true,
            center: true,
            minZoom: 0.1,
            maxZoom: 10
        });
    }

    // 3. Event Listeners
    document.getElementById('btnOrient').onclick  = () => { vertical = !vertical; render(); };

    document.getElementById('btnReset').onclick = () => {
        if(pz) pz.reset();
    };

    document.getElementById('btnPZ').onclick = function() {
        pzEnabled = !pzEnabled;
        this.innerText = pzEnabled ? "Pan/Zoom: ON" : "Pan/Zoom: OFF";
        this.classList.toggle('active');

        if (pzEnabled) {
            enablePanZoom();
        } else {
            if (pz) { pz.destroy(); pz = null; }
        }
    };

    // Initial Render
    render();
</script>

</body>
</html>
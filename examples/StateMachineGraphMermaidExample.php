<?php
declare(strict_types=1);

namespace ocallit\Util\OcStateMachine;

// ---------------------------------------------------------
// 1. INCLUDES
// ---------------------------------------------------------
require_once 'StateMachine.php';
require_once 'StateMachineGraphMermaid.php';

// ---------------------------------------------------------
// 2. CONFIGURATION & SETUP
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
// 3. SIMULATION
// ---------------------------------------------------------

$context = ['score' => 85, 'role' => 'editor'];
$sm = new StateMachine($states, 'REVIEW', $context);

// Fake History Logs
$stateNotes = [
  'DRAFT' => "Created: 2023-10-01\nAuthor: John Doe",
  'REVIEW' => "Current Step\nWaiting on: Board",
  'PUBLISHED' => "Target Date:\n2023-11-01"
];

// Generate
$generator = new StateMachineMermaid($sm);
$mermaidCode = $generator->generate(false, $stateNotes);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>State Machine Visualizer</title>
    <style>
        /* FULL SCREEN OVERRIDE */
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f9;
        }

        .header-bar {
            background: #333;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container {
            /* USE THE HARDWARE! */
            width: 98vw;
            margin: 1vh auto;
            background: white;
            padding: 20px;
            box-sizing: border-box;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Make the diagram scrollable if it gets absolutely massive */
        .mermaid {
            width: 100%;
            overflow: auto;
            text-align: center;
            min-height: 400px;
        }

        /* DEBUG SECTION STYLES */
        details {
            margin-top: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #fafafa;
        }
        summary {
            padding: 10px;
            cursor: pointer;
            font-weight: bold;
            background: #e0e0e0;
        }
        pre {
            margin: 0;
            padding: 15px;
            white-space: pre-wrap;
            background: #2d2d2d;
            color: #76e05e; /* Matrix green for code */
            font-family: 'Consolas', 'Monaco', monospace;
            text-align: left;
        }
    </style>
</head>
<body>

<div class="header-bar">
    <span><strong>State Machine Visualizer</strong></span>
    <span style="font-size: 0.9em">Current State: <strong><?php echo $sm->getCurrentState(); ?></strong></span>
</div>

<div class="container">
    <div class="mermaid">
        <?php echo $mermaidCode; ?>
    </div>

    <details open>
        <summary>🛠️ Debug Mermaid Code (Click to toggle)</summary>
        <pre><?php echo htmlspecialchars($mermaidCode); ?></pre>
    </details>
</div>

<script type="module"> import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.12.2/+esm'

    mermaid.initialize({
        startOnLoad: true,
        securityLevel: 'loose', // Allows HTML labels if needed
        theme: 'base',
        themeVariables: {
            primaryColor: '#BB2528',
            primaryTextColor: '#fff',
            lineColor: '#F8B229',
            mainBkg: '#fff',
            nodeBorder: '#7C0000'
        }
    });
</script>

</body>
</html>
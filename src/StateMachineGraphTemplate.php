<!doctype html>
<meta charset="utf-8"/>
<title>StateMachine (DOT + Viz.js)</title>
<style>
    body {
        font: 14px/1.4 system-ui, sans-serif;
        margin: 12px;
    }

    textarea {
        width: 100%;
        height: 140px;
    }

    .row {
        margin: 8px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    #stage {
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 8px;
        height: 70vh;
        overflow: auto;
    }

    #stage svg {
        width: 100%;
        height: auto;
    }

    /* responsive */
</style>

<!-- Viz.js v3 standalone -->
<script src="https://cdn.jsdelivr.net/npm/@viz-js/viz@3.7.0/lib/viz-standalone.js"></script>
<!-- Optional: pan/zoom for better UX on large graphs -->
<script src="https://cdn.jsdelivr.net/npm/svg-pan-zoom@3.6.1/dist/svg-pan-zoom.min.js"></script>

<textarea id="dot">
digraph G {
  graph [rankdir=LR, labelloc=t, label="Documents – Workflow\n2025-09-27 12:00", fontsize=12, nodesep=0.3, ranksep=0.5];
  node  [shape=record, fontname="Segoe UI", fontsize=12];
  edge  [fontname="Segoe UI", fontsize=11];

  DRAFT [label="{Draft|{<guards> GUARD_ENTER: [] | GUARD_LEAVE: [stampEditedAt]}|{<trig> ON_ENTER(): [] | ON_LEAVE(): [stampEditedAt] | ▶ ON_TRANSITION→REVIEW(): [logSubmit, notifyReviewer]}}"];
  REVIEW [style=filled, fillcolor="#FFF3B0",
          label="{In Review «CURRENT ●»|{<guards> GUARD_ENTER:[reviewerAssigned,docReadable] | GUARD_LEAVE: []}|{<trig> ON_ENTER():[startTimer] | ON_LEAVE():[stopTimer] | ▶ ON_TRANSITION→APPROVED():[logApprove] | ▶ ON_TRANSITION→REJECTED():[logReject,notifyAuthor] | ▶ ON_TRANSITION→REWORK():[requestChanges]}}"];
  APPROVED [label="{Approved|{<guards> GUARD_ENTER:[] | GUARD_LEAVE:[]}|{<trig> ON_ENTER():[publishDoc] | ON_LEAVE():[]}}"];
  REJECTED [label="{Rejected|{<guards> }|{<trig> ON_ENTER():[archiveDraft] | ON_LEAVE():[]}}"];
  REWORK   [label="{Rework|{<guards> }|{<trig> ON_ENTER():[openTasks] | ON_LEAVE():[closeTasks] | ▶ ON_TRANSITION→REVIEW():[logResubmission]}}"];

  DRAFT   -> REVIEW   [label="⚙️ submit for review\nGUARD_TRANSITION: [hasItems, totalValid]"];
  REVIEW  -> APPROVED [label="approve"];
  REVIEW  -> REJECTED [label="reject"];
  REVIEW  -> REWORK   [label="rework (request changes)\nGUARD_TRANSITION: [hasReviewerFeedback]"];
  REWORK  -> REVIEW   [label="resubmit"];
  REJECTED-> DRAFT    [label="restart"];
}
</textarea>

<div class="row">
    <button id="btnRender">Render</button>
    <button id="btnFields">Hide/Show fields</button>
    <button id="btnMethods">Hide/Show methods</button>
    <button id="btnOrient">Toggle vertical</button>
    <button id="btnFit">Fit to width</button>
    <button id="btnPZ">Pan/Zoom on/off</button>
</div>

<div id="stage"></div>

<script>
    let viz, pz = null, hideF = false, hideM = false, vertical = false;

    // Remove every {<portName> ...balanced... } group in record labels
    function stripSectionBalanced(src, portName) {
        let out = '', i = 0;
        while(i < src.length) {
            const start = src.indexOf('{<' + portName + '>', i);
            if(start === -1) {
                out += src.slice(i);
                break;
            }
            // copy text up to the start of the section
            out += src.slice(i, start);
            // now walk to matching closing '}' accounting for nested {...}
            let depth = 0, j = start;
            // we enter at '{', so set depth = 0 and increment when encountering '{'
            while(j < src.length) {
                const ch = src[j++];
                if(ch === '{') depth++;
                else if(ch === '}') {
                    depth--;
                    if(depth === 0) break; // matched closing brace of the section
                }
            }
            // Skip trailing '|' that often follows a removed record field, if present
            if(src[j] === '|') j++;
            i = j;
        }
        return out;
    }

    function applyFilters(dot) {
        let d = dot;
        if(hideF) d = stripSectionBalanced(d, 'guards');
        if(hideM) d = stripSectionBalanced(d, 'trig');
        if(vertical) d = d.replace(/rankdir\s*=\s*LR/i, 'rankdir=TB'); else d = d.replace(/rankdir\s*=\s*TB/i, 'rankdir=LR');
        return d;
    }

    async function ensureViz() {
        if(!viz) viz = await Viz.instance();
    }

    function makeResponsive(svgEl) {
        // Ensure responsive SVG: remove fixed size and keep viewBox
        svgEl.removeAttribute('width');
        svgEl.removeAttribute('height');
        if(!svgEl.getAttribute('viewBox')) {
            const bb = svgEl.getBBox();
            svgEl.setAttribute('viewBox', `${bb.x} ${bb.y} ${bb.width} ${bb.height}`);
        }
        svgEl.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    }

    async function render() {
        await ensureViz();
        const src = document.getElementById('dot').value;
        const dot = applyFilters(src);
        const stage = document.getElementById('stage');
        try {
            const svgEl = await viz.renderSVGElement(dot);
            stage.replaceChildren(svgEl);
            makeResponsive(svgEl);
            if(pz) {
                pz.destroy();
                pz = null;
            } // reset pan/zoom after re-render
        } catch(e) {
            stage.textContent = 'Render error:\n' + e;
        }
    }

    // Buttons
    document.getElementById('btnRender').onclick = render;
    document.getElementById('btnFields').onclick = () => {
        hideF = !hideF;
        render();
    };
    document.getElementById('btnMethods').onclick = () => {
        hideM = !hideM;
        render();
    };
    document.getElementById('btnOrient').onclick = () => {
        vertical = !vertical;
        render();
    };
    document.getElementById('btnFit').onclick = () => {
        const st = document.getElementById('stage');
        st.scrollTop = 0;
        st.scrollLeft = 0;
    };
    document.getElementById('btnPZ').onclick = () => {
        const svg = document.querySelector('#stage svg');
        if(!svg) return;
        if(pz) {
            pz.destroy();
            pz = null;
        } else {
            pz = svgPanZoom(svg, {zoomEnabled: true, controlIconsEnabled: true, fit: true, center: true});
        }
    };

    render();
</script>

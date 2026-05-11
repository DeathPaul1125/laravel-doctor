<?php

declare(strict_types=1);

namespace LaravelDoctor\Console;

use LaravelDoctor\Finding;

/**
 * Generates a single self-contained HTML report with embedded CSS + JS.
 * No external dependencies — open the file in any browser.
 */
final class HtmlReporter
{
    /**
     * @param array{findings: Finding[], score: int, grade: string, byCategory: array<string,int>, baselineCount?: int} $result
     */
    public function render(array $result, string $projectRoot, string $outputPath): string
    {
        $html = $this->buildHtml($result, $projectRoot);
        file_put_contents($outputPath, $html);
        return $outputPath;
    }

    /**
     * @param array{findings: Finding[], score: int, grade: string, byCategory: array<string,int>, baselineCount?: int} $result
     */
    public function buildHtml(array $result, string $projectRoot): string
    {
        $score = $result['score'];
        $grade = $result['grade'];
        $findings = array_map(fn (Finding $f) => $f->toArray(), $result['findings']);
        $byCategory = $result['byCategory'];
        $baselineCount = $result['baselineCount'] ?? 0;
        $generated = date('Y-m-d H:i:s');

        $gradeColor = match ($grade) {
            'great' => '#10b981',
            'needs-work' => '#f59e0b',
            'critical' => '#ef4444',
            default => '#6b7280',
        };
        $gradeLabel = match ($grade) {
            'great' => 'EXCELENTE',
            'needs-work' => 'NECESITA TRABAJO',
            'critical' => 'CRÍTICO',
            default => '',
        };

        $findingsJson = json_encode($findings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $byCategoryJson = json_encode($byCategory);
        $project = htmlspecialchars($projectRoot, ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laravel Doctor — {$project}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #0f172a; --bg-2: #1e293b; --bg-3: #334155;
    --fg: #e2e8f0; --fg-muted: #94a3b8;
    --critical: #ef4444; --high: #f97316; --medium: #f59e0b; --low: #06b6d4;
    --green: #10b981; --border: #334155;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: var(--bg); color: var(--fg); min-height: 100vh; padding: 24px;
    line-height: 1.5;
}
.container { max-width: 1280px; margin: 0 auto; }
header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.brand { display: flex; align-items: center; gap: 12px; }
.brand h1 { font-size: 22px; font-weight: 700; }
.brand .sub { color: var(--fg-muted); font-size: 13px; font-family: monospace; }
.meta { color: var(--fg-muted); font-size: 13px; }
.cards { display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 24px; }
@media (max-width: 800px) { .cards { grid-template-columns: 1fr; } }
.card { background: var(--bg-2); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.score { text-align: center; }
.score .num { font-size: 72px; font-weight: 800; color: {$gradeColor}; line-height: 1; }
.score .grade { font-size: 14px; font-weight: 600; color: {$gradeColor}; margin-top: 8px; letter-spacing: 1px; }
.score .label { font-size: 12px; color: var(--fg-muted); margin-top: 4px; }
.category-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
.cat-box { background: var(--bg-3); border-radius: 8px; padding: 16px; }
.cat-box .num { font-size: 32px; font-weight: 700; }
.cat-box .label { font-size: 11px; text-transform: uppercase; color: var(--fg-muted); letter-spacing: 1px; }
.filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
.filter-btn {
    background: var(--bg-2); border: 1px solid var(--border); color: var(--fg);
    padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px;
    transition: all 0.15s;
}
.filter-btn:hover { background: var(--bg-3); }
.filter-btn.active { background: var(--fg); color: var(--bg); }
.filter-btn.sev-critical.active { background: var(--critical); color: white; border-color: var(--critical); }
.filter-btn.sev-high.active { background: var(--high); color: white; border-color: var(--high); }
.filter-btn.sev-medium.active { background: var(--medium); color: white; border-color: var(--medium); }
.filter-btn.sev-low.active { background: var(--low); color: white; border-color: var(--low); }
.search {
    flex: 1; min-width: 200px;
    background: var(--bg-2); border: 1px solid var(--border); color: var(--fg);
    padding: 6px 12px; border-radius: 6px; font-size: 13px;
}
.findings { display: flex; flex-direction: column; gap: 8px; }
.finding {
    background: var(--bg-2); border: 1px solid var(--border); border-left: 4px solid var(--fg-muted);
    border-radius: 8px; padding: 16px; transition: border-color 0.15s;
}
.finding.sev-critical { border-left-color: var(--critical); }
.finding.sev-high { border-left-color: var(--high); }
.finding.sev-medium { border-left-color: var(--medium); }
.finding.sev-low { border-left-color: var(--low); }
.finding-head { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.badge {
    font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.badge.sev-critical { background: var(--critical); color: white; }
.badge.sev-high { background: var(--high); color: white; }
.badge.sev-medium { background: var(--medium); color: white; }
.badge.sev-low { background: var(--low); color: white; }
.check-id { font-family: monospace; font-size: 12px; color: var(--fg-muted); }
.category-pill { font-size: 11px; padding: 2px 8px; background: var(--bg-3); border-radius: 4px; color: var(--fg-muted); }
.finding-msg { margin-top: 8px; font-size: 14px; }
.finding-loc { margin-top: 4px; font-family: monospace; font-size: 12px; color: var(--fg-muted); }
.finding-snippet { margin-top: 8px; padding: 8px 12px; background: var(--bg); border-radius: 6px; font-family: monospace; font-size: 12px; overflow-x: auto; color: var(--fg-muted); }
.finding-suggestion { margin-top: 8px; font-size: 13px; color: var(--green); }
.empty { text-align: center; padding: 48px 24px; color: var(--fg-muted); }
.empty .icon { font-size: 48px; margin-bottom: 8px; }
footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--border); color: var(--fg-muted); font-size: 12px; text-align: center; }
</style>
</head>
<body>
<div class="container">
    <header>
        <div class="brand">
            <div>
                <h1>Laravel Doctor</h1>
                <div class="sub">{$project}</div>
            </div>
        </div>
        <div class="meta">Generado el {$generated}</div>
    </header>

    <div class="cards">
        <div class="card score">
            <div class="num">{$score}<span style="font-size: 32px; color: var(--fg-muted);">/100</span></div>
            <div class="grade">{$gradeLabel}</div>
            <div class="label">PUNTUACIÓN DE SALUD</div>
            {$this->baselineNotice($baselineCount)}
        </div>
        <div class="card">
            <div class="category-grid" id="cats"></div>
        </div>
    </div>

    <div class="filters">
        <button class="filter-btn active" data-filter="cat" data-value="all">Todas</button>
        <button class="filter-btn" data-filter="cat" data-value="security">Seguridad</button>
        <button class="filter-btn" data-filter="cat" data-value="performance">Rendimiento</button>
        <button class="filter-btn" data-filter="cat" data-value="architecture">Arquitectura</button>
        <button class="filter-btn" data-filter="cat" data-value="quality">Calidad</button>
        <span style="width: 16px;"></span>
        <button class="filter-btn sev-critical" data-filter="sev" data-value="critical">Crítico</button>
        <button class="filter-btn sev-high" data-filter="sev" data-value="high">Alto</button>
        <button class="filter-btn sev-medium" data-filter="sev" data-value="medium">Medio</button>
        <button class="filter-btn sev-low" data-filter="sev" data-value="low">Bajo</button>
        <input type="text" class="search" id="search" placeholder="Filtrar por archivo, mensaje o check id…">
    </div>

    <div class="findings" id="findings"></div>

    <footer>
        Laravel Doctor · {$generated} · Inspirado en react-doctor
    </footer>
</div>

<script>
const findings = {$findingsJson};
const byCategory = {$byCategoryJson};

const catColors = { security: '#ef4444', performance: '#f59e0b', architecture: '#8b5cf6', quality: '#06b6d4' };
const catLabels = { security: 'seguridad', performance: 'rendimiento', architecture: 'arquitectura', quality: 'calidad' };
const sevLabels = { critical: 'crítico', high: 'alto', medium: 'medio', low: 'bajo' };
const catsEl = document.getElementById('cats');
for (const [cat, count] of Object.entries(byCategory)) {
    const box = document.createElement('div');
    box.className = 'cat-box';
    box.innerHTML = `<div class="num" style="color:\${catColors[cat]||'#fff'}">\${count}</div><div class="label">\${catLabels[cat]||cat}</div>`;
    catsEl.appendChild(box);
}

const state = { cat: 'all', sev: new Set(), q: '' };
function escapeHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }

function render() {
    const root = document.getElementById('findings');
    const filtered = findings.filter(f => {
        if (state.cat !== 'all' && f.category !== state.cat) return false;
        if (state.sev.size > 0 && !state.sev.has(f.severity)) return false;
        if (state.q) {
            const q = state.q.toLowerCase();
            const blob = (f.file + ' ' + f.message + ' ' + f.check + ' ' + (f.snippet||'')).toLowerCase();
            if (!blob.includes(q)) return false;
        }
        return true;
    });
    if (!filtered.length) {
        root.innerHTML = '<div class="empty"><div class="icon">🎉</div>Ningún hallazgo coincide con este filtro.</div>';
        return;
    }
    root.innerHTML = filtered.map(f => `
        <div class="finding sev-\${f.severity}">
            <div class="finding-head">
                <span class="badge sev-\${f.severity}">\${sevLabels[f.severity]||f.severity}</span>
                <span class="check-id">\${escapeHtml(f.check)}</span>
                <span class="category-pill">\${catLabels[f.category]||f.category}</span>
            </div>
            <div class="finding-msg">\${escapeHtml(f.message)}</div>
            <div class="finding-loc">→ \${escapeHtml(f.file)}\${f.line ? ':' + f.line : ''}</div>
            \${f.snippet ? `<div class="finding-snippet">\${escapeHtml(f.snippet)}</div>` : ''}
            \${f.suggestion ? `<div class="finding-suggestion">💡 \${escapeHtml(f.suggestion)}</div>` : ''}
        </div>
    `).join('');
}

document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const filter = btn.dataset.filter;
        const value = btn.dataset.value;
        if (filter === 'cat') {
            document.querySelectorAll('.filter-btn[data-filter=cat]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.cat = value;
        } else if (filter === 'sev') {
            btn.classList.toggle('active');
            if (btn.classList.contains('active')) state.sev.add(value); else state.sev.delete(value);
        }
        render();
    });
});
document.getElementById('search').addEventListener('input', e => { state.q = e.target.value; render(); });
render();
</script>
</body>
</html>
HTML;
    }

    private function baselineNotice(int $count): string
    {
        if ($count === 0) {
            return '';
        }
        return '<div class="label" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">' . $count . ' hallazgos conocidos ocultos por baseline</div>';
    }
}

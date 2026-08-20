<?php

namespace Phoenix1331\LaravelEnvAudit\Formatters;

use Phoenix1331\LaravelEnvAudit\Data\EnvAuditReport;

class HtmlFormatter
{
    public function __construct(private readonly string $title = 'Env Audit Report') {}

    public function format(EnvAuditReport $report): string
    {
        $score = $report->isolationScore();
        $scoreColor = $score >= 90 ? '#22c55e' : ($score >= 70 ? '#eab308' : '#ef4444');

        $directRows = $this->directUsageRows($report);
        $secretRows = $this->secretRows($report);
        $missingRows = $this->missingRows($report);
        $unusedRows = $this->unusedRows($report);
        $ignoreRows = $this->ignoreRows($report);
        $expiredRows = $this->expiredIgnoreRows($report);

        $generatedAt = date('Y-m-d H:i:s');
        $title = htmlspecialchars($this->title, ENT_QUOTES);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>{$title}</title>
          <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                   background: #0f172a; color: #e2e8f0; padding: 2rem; min-height: 100vh; }
            h1 { font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem; }
            .meta { font-size: 0.75rem; color: #64748b; margin-bottom: 2rem; }
            .score-card { background: #1e293b; border-radius: 0.75rem; padding: 1.5rem 2rem;
                          display: inline-flex; align-items: baseline; gap: 1rem; margin-bottom: 2rem;
                          border: 1px solid #334155; }
            .score-number { font-size: 3rem; font-weight: 800; color: {$scoreColor}; line-height: 1; }
            .score-label { font-size: 0.875rem; color: #94a3b8; }
            .score-detail { font-size: 0.75rem; color: #64748b; }
            section { margin-bottom: 1.5rem; }
            .section-header { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem;
                              border-radius: 0.5rem 0.5rem 0 0; font-weight: 600; font-size: 0.875rem; }
            .section-error   { background: #450a0a; color: #fca5a5; border: 1px solid #7f1d1d; }
            .section-warning { background: #422006; color: #fcd34d; border: 1px solid #78350f; }
            .section-info    { background: #0c1a2e; color: #93c5fd; border: 1px solid #1e3a5f; }
            .section-ok      { background: #052e16; color: #86efac; border: 1px solid #14532d; }
            .section-expired { background: #450a0a; color: #fca5a5; border: 1px solid #7f1d1d; }
            .section-bypass  { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
            table { width: 100%; border-collapse: collapse; background: #1e293b;
                    border: 1px solid #334155; border-top: none; border-radius: 0 0 0.5rem 0.5rem; overflow: hidden; }
            th { background: #0f172a; color: #64748b; font-size: 0.7rem; text-transform: uppercase;
                 letter-spacing: 0.05em; padding: 0.5rem 1rem; text-align: left; cursor: pointer;
                 user-select: none; }
            th:hover { color: #94a3b8; }
            td { padding: 0.5rem 1rem; font-size: 0.8rem; border-top: 1px solid #0f172a; vertical-align: top; }
            tr:hover td { background: #263045; }
            .badge-error   { color: #f87171; }
            .badge-warning { color: #fbbf24; }
            .badge-info    { color: #60a5fa; }
            .file-path { color: #94a3b8; }
            .line-num  { color: #64748b; }
            .key-name  { color: #e2e8f0; font-weight: 600; }
            .masked    { color: #fbbf24; font-family: monospace; }
            .reason-text { color: #94a3b8; font-size: 0.75rem; }
            .empty-state { padding: 0.75rem 1rem; font-size: 0.8rem; color: #86efac;
                           background: #1e293b; border: 1px solid #334155; border-top: none;
                           border-radius: 0 0 0.5rem 0.5rem; }
            footer { margin-top: 3rem; font-size: 0.7rem; color: #475569; text-align: center; }
          </style>
        </head>
        <body>
          <h1>{$title}</h1>
          <p class="meta">Generated at {$generatedAt}</p>

          <div class="score-card">
            <span class="score-number">{$score}%</span>
            <div>
              <div class="score-label">Isolation Score</div>
              <div class="score-detail">{$report->configEnvCalls} of {$report->totalEnvCalls} env() calls live inside config/</div>
            </div>
          </div>

          <section>
            <div class="section-header {$this->sectionClass('error', count($report->directUsageViolations))}">
              {$this->sectionIcon('error', count($report->directUsageViolations))}
              Direct env() Usage Outside config/ ({$this->count($report->directUsageViolations)})
            </div>
            {$directRows}
          </section>

          <section>
            <div class="section-header {$this->sectionClass('error', count($report->possibleSecrets))}">
              {$this->sectionIcon('error', count($report->possibleSecrets))}
              Possible Secrets in .env.example ({$this->count($report->possibleSecrets)})
            </div>
            {$secretRows}
          </section>

          <section>
            <div class="section-header {$this->sectionClass('warning', count($report->missingFromExample))}">
              {$this->sectionIcon('warning', count($report->missingFromExample))}
              Missing from .env.example ({$this->count($report->missingFromExample)})
            </div>
            {$missingRows}
          </section>

          <section>
            <div class="section-header {$this->sectionClass('info', count($report->unusedInExample))}">
              {$this->sectionIcon('info', count($report->unusedInExample))}
              Unused in .env.example ({$this->count($report->unusedInExample)})
            </div>
            {$unusedRows}
          </section>

          <section>
            <div class="section-header section-bypass">
              Bypasses — Active ({$this->count($report->ignores)})
            </div>
            {$ignoreRows}
          </section>

          {$this->expiredSection($report, $expiredRows)}

          <script>
            document.querySelectorAll('th[data-col]').forEach(th => {{
              th.addEventListener('click', () => {{
                const table = th.closest('table');
                const col = +th.dataset.col;
                const rows = [...table.querySelectorAll('tbody tr')];
                const asc = th.dataset.asc !== 'true';
                rows.sort((a, b) => {{
                  const av = a.cells[col]?.textContent.trim() ?? '';
                  const bv = b.cells[col]?.textContent.trim() ?? '';
                  return asc ? av.localeCompare(bv) : bv.localeCompare(av);
                }});
                th.dataset.asc = asc;
                rows.forEach(r => table.querySelector('tbody').appendChild(r));
              }});
            }});
          </script>

          <footer>phoenix1331/laravel-env-audit &mdash; secret values are never stored or rendered in full</footer>
        </body>
        </html>
        HTML;
    }

    private function directUsageRows(EnvAuditReport $report): string
    {
        if (count($report->directUsageViolations) === 0) {
            return '<div class="empty-state">No direct usage violations found.</div>';
        }

        $rows = '';
        foreach ($report->directUsageViolations as $call) {
            $file = htmlspecialchars($call->file, ENT_QUOTES);
            $key = htmlspecialchars($call->key ?? '(dynamic)', ENT_QUOTES);
            $rows .= <<<ROW
            <tr>
              <td class="file-path">{$file}</td>
              <td class="line-num">{$call->line}</td>
              <td class="key-name">{$key}</td>
            </tr>
            ROW;
        }

        return <<<TABLE
        <table>
          <thead><tr>
            <th data-col="0">File</th>
            <th data-col="1">Line</th>
            <th data-col="2">Key</th>
          </tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function secretRows(EnvAuditReport $report): string
    {
        if (count($report->possibleSecrets) === 0) {
            return '<div class="empty-state">No possible secrets detected.</div>';
        }

        $rows = '';
        foreach ($report->possibleSecrets as $secret) {
            $key = htmlspecialchars($secret->key, ENT_QUOTES);
            $masked = htmlspecialchars($secret->maskedValue, ENT_QUOTES);
            $detail = htmlspecialchars($secret->detail, ENT_QUOTES);
            $rows .= <<<ROW
            <tr>
              <td class="key-name">{$key}</td>
              <td class="masked">{$masked}</td>
              <td class="reason-text">{$detail}</td>
            </tr>
            ROW;
        }

        return <<<TABLE
        <table>
          <thead><tr>
            <th data-col="0">Key</th>
            <th data-col="1">Masked Value</th>
            <th data-col="2">Reason</th>
          </tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function missingRows(EnvAuditReport $report): string
    {
        if (count($report->missingFromExample) === 0) {
            return '<div class="empty-state">No missing keys detected.</div>';
        }

        $rows = '';
        foreach ($report->missingFromExample as $key) {
            $key = htmlspecialchars($key, ENT_QUOTES);
            $rows .= "<tr><td class=\"key-name\">{$key}</td></tr>";
        }

        return <<<TABLE
        <table>
          <thead><tr><th data-col="0">Key</th></tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function unusedRows(EnvAuditReport $report): string
    {
        if (count($report->unusedInExample) === 0) {
            return '<div class="empty-state">No unused keys detected.</div>';
        }

        $rows = '';
        foreach ($report->unusedInExample as $key) {
            $key = htmlspecialchars($key, ENT_QUOTES);
            $rows .= "<tr><td class=\"key-name\">{$key}</td></tr>";
        }

        return <<<TABLE
        <table>
          <thead><tr><th data-col="0">Key</th></tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function ignoreRows(EnvAuditReport $report): string
    {
        if (count($report->ignores) === 0) {
            return '<div class="empty-state">No active bypasses.</div>';
        }

        $rows = '';
        foreach ($report->ignores as $ignore) {
            $file = htmlspecialchars($ignore->file, ENT_QUOTES);
            $reason = htmlspecialchars($ignore->reason, ENT_QUOTES);
            $source = htmlspecialchars($ignore->source, ENT_QUOTES);
            $expires = $ignore->expires ? htmlspecialchars($ignore->expires, ENT_QUOTES) : '-';
            $rows .= <<<ROW
            <tr>
              <td class="file-path">{$file}</td>
              <td class="line-num">{$ignore->line}</td>
              <td class="reason-text">{$reason}</td>
              <td class="reason-text">{$expires}</td>
              <td class="reason-text">{$source}</td>
            </tr>
            ROW;
        }

        return <<<TABLE
        <table>
          <thead><tr>
            <th data-col="0">File</th>
            <th data-col="1">Line</th>
            <th data-col="2">Reason</th>
            <th data-col="3">Expires</th>
            <th data-col="4">Source</th>
          </tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function expiredIgnoreRows(EnvAuditReport $report): string
    {
        if (count($report->expiredIgnores) === 0) {
            return '';
        }

        $rows = '';
        foreach ($report->expiredIgnores as $ignore) {
            $file = htmlspecialchars($ignore->file, ENT_QUOTES);
            $reason = htmlspecialchars($ignore->reason, ENT_QUOTES);
            $expires = htmlspecialchars($ignore->expires ?? '', ENT_QUOTES);
            $rows .= <<<ROW
            <tr>
              <td class="file-path">{$file}</td>
              <td class="line-num">{$ignore->line}</td>
              <td class="reason-text">{$reason}</td>
              <td class="badge-error">{$expires}</td>
            </tr>
            ROW;
        }

        return <<<TABLE
        <table>
          <thead><tr>
            <th data-col="0">File</th>
            <th data-col="1">Line</th>
            <th data-col="2">Reason</th>
            <th data-col="3">Expired</th>
          </tr></thead>
          <tbody>{$rows}</tbody>
        </table>
        TABLE;
    }

    private function expiredSection(EnvAuditReport $report, string $rows): string
    {
        if (count($report->expiredIgnores) === 0) {
            return '';
        }

        $count = $this->count($report->expiredIgnores);

        return <<<SECTION
        <section>
          <div class="section-header section-expired">
            Expired Bypasses ({$count}) — must be resolved or renewed
          </div>
          {$rows}
        </section>
        SECTION;
    }

    private function sectionClass(string $severity, int $count): string
    {
        if ($count === 0) {
            return 'section-ok';
        }

        return match ($severity) {
            'error' => 'section-error',
            'warning' => 'section-warning',
            default => 'section-info',
        };
    }

    private function sectionIcon(string $severity, int $count): string
    {
        if ($count === 0) {
            return '&#10004;';
        }

        return match ($severity) {
            'error' => '&#10008;',
            'warning' => '&#9888;',
            default => '&#9432;',
        };
    }

    /** @param array<mixed> $items */
    private function count(array $items): string
    {
        return (string) count($items);
    }
}

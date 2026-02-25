<?php

namespace Edrard\Pingmonit\Report;

use Edrard\Pingmonit\Contracts\ReportGeneratorInterface;
use Edrard\Pingmonit\State\HostStateRepository;

class HtmlIndexReportGenerator implements ReportGeneratorInterface
{
    private $outputFile;
    private $refreshSeconds;

    public function __construct($outputFile, $refreshSeconds = 0)
    {
        $this->outputFile = $outputFile;
        $this->refreshSeconds = (int) $refreshSeconds;
    }

    public function generate(array $targets, HostStateRepository $state, array $upsTargets = [], HostStateRepository $upsState = null)
    {
        $rows = [];

        foreach ($targets as $t) {
            if (!is_array($t)) {
                continue;
            }

            $web = (bool) ($t['web'] ?? true);
            if ($web !== true) {
                continue;
            }

            $ip = (string) ($t['ip'] ?? '');
            if ($ip === '') {
                continue;
            }

            $name = (string) ($t['name'] ?? '');
            $entry = $state->get($ip) ?? [];
            $status = (string) ($entry['status'] ?? 'unknown');

            $label = '';

            $problemSince = null;
            if ($status === 'critical') {
                $problemSince = $entry['warning_start'] ?? null;
                if (!is_string($problemSince) || $problemSince === '') {
                    $problemSince = $entry['critical_since'] ?? null;
                }
                if (!is_string($problemSince) || $problemSince === '') {
                    $problemSince = null;
                }
            }

            $rows[] = [
                'label' => $label,
                'status' => $status,
                'problem_since' => $problemSince,
            ];
        }

        if ($upsState !== null) {
            foreach ($upsTargets as $t) {
                if (!is_array($t)) {
                    continue;
                }

                $web = (bool) ($t['web'] ?? true);
                if ($web !== true) {
                    continue;
                }

                $ip = (string) ($t['ip'] ?? '');
                if ($ip === '') {
                    continue;
                }

                $entry = $upsState->get($ip) ?? [];
                $status = (string) ($entry['status'] ?? 'unknown');

                $label = '';

                $problemSince = null;
                if ($status === 'critical') {
                    $problemSince = $entry['warning_start'] ?? null;
                    if (!is_string($problemSince) || $problemSince === '') {
                        $problemSince = $entry['critical_since'] ?? null;
                    }
                    if (!is_string($problemSince) || $problemSince === '') {
                        $problemSince = null;
                    }
                }

                $rows[] = [
                    'label' => $label,
                    'status' => $status,
                    'problem_since' => $problemSince,
                ];
            }
        }

        $html = $this->render($rows);

        $dir = dirname($this->outputFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->outputFile, $html);
    }

    private function render(array $rows)
    {
        $count = count($rows);

        $barsHtml = '';
        foreach ($rows as $row) {
            $status = $row['status'];
            $class = 'unknown';
            if ($status === 'good') {
                $class = 'good';
            } elseif ($status === 'warning') {
                $class = 'warning';
            } elseif ($status === 'critical') {
                $class = 'critical';
            }

            $since = $row['problem_since'] !== null
                ? htmlspecialchars((string) $row['problem_since'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : '';

            $meta = $since !== '' ? '<div class="since">' . $since . '</div>' : '';

            $barsHtml .= '<div class="bar ' . $class . '">'
                . '<div class="content">'
                . $meta
                . '</div>'
                . '</div>';
        }

        $title = 'PingMonit';
        $refresh = '';
        if ($this->refreshSeconds > 0) {
            $refresh = '<meta http-equiv="refresh" content="' . (int) $this->refreshSeconds . '">';
        }

        return '<!doctype html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">'
            . '<meta name="robots" content="noindex,nofollow">'
            . $refresh
            . '<title>' . $title . '</title>'
            . '<style>'
            . 'html,body{height:100%;margin:0;}'
            . 'body{overflow:hidden;background:#111;color:#fff;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;}'
            . '.wrap{height:100vh;display:flex;flex-direction:column;}'
            . '.bar{flex:1;display:flex;align-items:center;justify-content:center;border-bottom:2px solid rgba(255,255,255,.35);}'
            . '.bar:last-child{border-bottom:none;}'
            . '.bar.good{background:#16a34a;}'
            . '.bar.warning{background:#f59e0b;}'
            . '.bar.critical{background:#dc2626;}'
            . '.bar.unknown{background:#374151;}'
            . '.content{width:100%;padding:10px 12px;box-sizing:border-box;text-align:center;}'
            . '.since{margin-top:4px;font-size:clamp(12px,3.2vw,16px);font-weight:600;opacity:.95;}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<div class="wrap" data-count="' . (int) $count . '">'
            . $barsHtml
            . '</div>'
            . '</body>'
            . '</html>';
    }
}

<?php
// includes/version.php – Versionsnummer und Release Notes (#111)
// Einzige Quelle der Version ist die Datei VERSION im Wurzelverzeichnis; die Release Notes stehen in CHANGELOG.md
// (Format "Keep a Changelog"). Beide werden hier gelesen, nichts wird doppelt gepflegt.

const FUELSTAT_WURZEL = __DIR__ . '/..';

function fuelstatVersion(): string
{
    static $version = null;
    if ($version === null) {
        $version = trim((string)@file_get_contents(FUELSTAT_WURZEL . '/VERSION')) ?: '0.0.0';
    }
    return $version;
}

/**
 * CHANGELOG.md zerlegen.
 * @return array Liste von ['version' => '0.9.0', 'datum' => '2026-11-06', 'einleitung' => '…',
 *               'abschnitte' => ['Neu' => ['…', …], …]]; neueste zuerst, ohne "Unreleased"
 */
function fuelstatReleases(?string $text = null): array
{
    $text ??= (string)@file_get_contents(FUELSTAT_WURZEL . '/CHANGELOG.md');
    $releases = [];
    $aktuell = null;
    $abschnitt = null;
    foreach (preg_split('/\r?\n/', $text) as $zeile) {
        if (preg_match('/^## \[([^\]]+)\](?:\s*-\s*(\d{4}-\d{2}-\d{2}))?/', $zeile, $m)) {
            if ($aktuell !== null) {
                $releases[] = $aktuell;
            }
            $aktuell = strcasecmp($m[1], 'Unreleased') === 0 ? null
                : ['version' => $m[1], 'datum' => $m[2] ?? null, 'einleitung' => '', 'abschnitte' => []];
            $abschnitt = null;
            continue;
        }
        if ($aktuell === null) {
            continue;
        }
        if (preg_match('/^### (.+)$/', $zeile, $m)) {
            $abschnitt = trim($m[1]);
            $aktuell['abschnitte'][$abschnitt] = [];
        } elseif ($abschnitt !== null && preg_match('/^\s*[-*] (.+)$/', $zeile, $m)) {
            $aktuell['abschnitte'][$abschnitt][] = trim($m[1]);
        } elseif ($abschnitt === null && trim($zeile) !== '' && !str_starts_with($zeile, '[')) {
            $aktuell['einleitung'] = trim($aktuell['einleitung'] . ' ' . trim($zeile));
        }
    }
    if ($aktuell !== null) {
        $releases[] = $aktuell;
    }
    return $releases;
}

/** Inline-Markdown einer Zeile sicher als HTML: erst escapen, dann **fett**, `code` und [Text](https://…) */
function fuelstatMarkdownZeile(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    // Nur https-Links, Ziel bereits escaped
    $html = preg_replace('/\[([^\]]+)\]\((https:\/\/[^)\s"]+)\)/', '<a href="$2" rel="noopener" target="_blank">$1</a>', $html);
    return $html;
}

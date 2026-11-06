<?php
// Versionsnummer und Release Notes (#111): überall dieselbe Version, CHANGELOG lesbar
require __DIR__ . '/../includes/version.php';

$version = fuelstatVersion();
assert(preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $version) === 1, 'VERSION ist SemVer: ' . $version);

$paket = json_decode((string)file_get_contents(__DIR__ . '/../backend/package.json'), true);
assert($paket['version'] === $version, 'backend/package.json hat dieselbe Version wie VERSION (node backend/scripts/neue-version.js)');
$lock = json_decode((string)file_get_contents(__DIR__ . '/../backend/package-lock.json'), true);
assert($lock['version'] === $version && $lock['packages']['']['version'] === $version, 'package-lock.json hat dieselbe Version');

$releases = fuelstatReleases();
assert($releases !== [], 'CHANGELOG.md enthält Versionen');
assert($releases[0]['version'] === $version, 'Neueste Version im CHANGELOG ist ' . $version . ', nicht ' . $releases[0]['version']);
assert($releases[0]['datum'] !== null, 'Version hat ein Datum');
foreach ($releases as $r) {
    assert($r['abschnitte'] !== [], 'Version ' . $r['version'] . ' hat Abschnitte');
}

// Parser: Unreleased wird ausgelassen, Abschnitte und Einträge werden erkannt
$beispiel = "# Changelog\n\n## [Unreleased]\n### Neu\n- kommt noch\n\n## [1.1.0] - 2027-01-02\nKurz gesagt.\n### Neu\n- Eins\n- Zwei\n### Behoben\n- Drei\n\n## [1.0.0] - 2027-01-01\n### Neu\n- Start\n\n[1.1.0]: https://example.test\n";
$r = fuelstatReleases($beispiel);
assert(count($r) === 2 && $r[0]['version'] === '1.1.0' && $r[1]['version'] === '1.0.0');
assert($r[0]['einleitung'] === 'Kurz gesagt.');
assert($r[0]['abschnitte'] === ['Neu' => ['Eins', 'Zwei'], 'Behoben' => ['Drei']]);

// Markdown sicher: HTML wird escaped, nur https-Links
assert(fuelstatMarkdownZeile('**fett** und `code`') === '<strong>fett</strong> und <code>code</code>');
assert(fuelstatMarkdownZeile('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
assert(str_contains(fuelstatMarkdownZeile('[Doku](https://example.test/a)'), '<a href="https://example.test/a"'));
assert(!str_contains(fuelstatMarkdownZeile('[x](javascript:alert(1))'), '<a '), 'keine javascript:-Links');

echo "All tests passed\n";

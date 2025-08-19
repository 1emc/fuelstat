<?php
require __DIR__ . '/../includes/functions.php';

$entries = [
    ['datum' => '2025-05-08', 'menge' => 47.2, 'tachostand' => 60092, 'vollgetankt' => 1],
    ['datum' => '2025-05-26', 'menge' => 27.5, 'tachostand' => 60495, 'vollgetankt' => 1],
    ['datum' => '2025-06-14', 'menge' => 13.2, 'tachostand' => 60695, 'vollgetankt' => 1],
    ['datum' => '2025-06-26', 'menge' => 20.0, 'tachostand' => 60830, 'vollgetankt' => 0],
    ['datum' => '2025-07-05', 'menge' => 21.8, 'tachostand' => 61020, 'vollgetankt' => 1],
    ['datum' => '2025-07-24', 'menge' => 16.9, 'tachostand' => 61280, 'vollgetankt' => 1],
];

$result = berechneVerbrauchProMonatAusTankfuellungen($entries);

assert(abs($result['2025-05'] - 6.82) < 0.01, 'Mai 2025 expected 6.82');
assert(abs($result['2025-06'] - 6.60) < 0.01, 'Jun 2025 expected 6.60');
assert(abs($result['2025-07'] - 10.03) < 0.01, 'Jul 2025 expected ~10.03');

echo "All tests passed\n";

<?php
// Ladestationen in der Nähe über Open Charge Map (https://openchargemap.org), Gegenstück zur Tankerkönig-Suche.
// Datenmodell: https://github.com/openchargemap/ocm-system (OCM.API.Model, ChargePoint/AddressInfo/ConnectionInfo).

const OCM_API_URL = 'https://api.openchargemap.io/v3/poi/';
const OCM_CACHE_SEKUNDEN = 6 * 3600;

/** Steckertypen kurz und deutsch, z. B. "Type 2 (Socket Only)" → "Typ 2", "CCS (Type 2)" → "CCS" */
function ocmSteckerName(string $titel): string
{
    $t = strtolower($titel);
    return match (true) {
        str_contains($t, 'ccs') || str_contains($t, 'combo') => 'CCS',
        str_contains($t, 'chademo') => 'CHAdeMO',
        str_contains($t, 'tesla') => 'Tesla',
        str_contains($t, 'type 2') || str_contains($t, 'mennekes') => 'Typ 2',
        str_contains($t, 'type 1') || str_contains($t, 'j1772') => 'Typ 1',
        str_contains($t, 'schuko') || str_contains($t, 'cee 7/4') => 'Schuko',
        str_contains($t, 'europlug') || str_contains($t, 'cee 7/16') => 'Eurostecker',
        str_contains($t, 'cee') => 'CEE',
        default => trim($titel),
    };
}

/**
 * Open-Charge-Map-Treffer auf das reduzieren, was das Formular braucht.
 * @param array $pois Antwort von /v3/poi (Liste von ChargePoint-Objekten)
 * @return array Liste mit name, betreiber, adresse, ort, entfernung, punkte, max_kw, stecker, kosten, in_betrieb
 */
function ocmStationenAufbereiten(array $pois): array
{
    $stationen = [];
    foreach ($pois as $poi) {
        if (!is_array($poi)) {
            continue;
        }
        $adresse = is_array($poi['AddressInfo'] ?? null) ? $poi['AddressInfo'] : [];
        $verbindungen = is_array($poi['Connections'] ?? null) ? $poi['Connections'] : [];
        $leistungen = array_filter(array_map(fn($c) => (float)($c['PowerKW'] ?? 0), $verbindungen));
        $stecker = array_values(array_unique(array_filter(array_map(
            fn($c) => ocmSteckerName((string)($c['ConnectionType']['Title'] ?? '')),
            $verbindungen
        ))));
        $betreiber = trim((string)($poi['OperatorInfo']['Title'] ?? ''));
        if (stripos($betreiber, 'unknown') !== false) {
            $betreiber = '';
        }
        $punkte = (int)($poi['NumberOfPoints'] ?? 0)
            ?: array_sum(array_map(fn($c) => max(1, (int)($c['Quantity'] ?? 1)), $verbindungen));

        $stationen[] = [
            'name' => trim((string)($adresse['Title'] ?? '')) ?: ($betreiber ?: 'Ladestation'),
            'betreiber' => $betreiber,
            'adresse' => trim((string)($adresse['AddressLine1'] ?? '')),
            'ort' => trim((string)($adresse['Town'] ?? '')),
            'entfernung' => isset($adresse['Distance']) ? round((float)$adresse['Distance'], 1) : null,
            'punkte' => $punkte ?: null,
            'max_kw' => $leistungen ? round(max($leistungen), 1) : null,
            'stecker' => $stecker,
            'kosten' => trim((string)($poi['UsageCost'] ?? '')),
            'in_betrieb' => ($poi['StatusType']['IsOperational'] ?? true) !== false,
        ];
    }
    usort($stationen, fn($a, $b) => ($a['entfernung'] ?? INF) <=> ($b['entfernung'] ?? INF));
    return $stationen;
}

/**
 * Ladestationen um einen Punkt abfragen (mit Zwischenspeicher, um das Abfragelimit zu schonen).
 * @return array|null aufbereitete Liste oder null bei Fehler
 */
function ocmLadestationen(float $lat, float $lng, int $radiusKm, string $apiKey): ?array
{
    $cacheDatei = sys_get_temp_dir() . '/fuelstat-ocm/' . md5(sprintf('%.3f|%.3f|%d', $lat, $lng, $radiusKm)) . '.json';
    if (is_file($cacheDatei) && filemtime($cacheDatei) > time() - OCM_CACHE_SEKUNDEN) {
        $gespeichert = json_decode((string)file_get_contents($cacheDatei), true);
        if (is_array($gespeichert)) {
            return $gespeichert;
        }
    }

    $url = OCM_API_URL . '?' . http_build_query([
        'output' => 'json',
        'latitude' => $lat,
        'longitude' => $lng,
        'distance' => $radiusKm,
        'distanceunit' => 'KM',
        'maxresults' => 30,
        'verbose' => 'false',
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_USERAGENT => 'Fuelstat (https://tanken.1emc.de)',
    ]);
    $antwort = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($antwort === false || $status !== 200) {
        error_log('[Ladestationen] Open Charge Map antwortet mit Status ' . $status);
        return null;
    }
    $pois = json_decode($antwort, true);
    if (!is_array($pois)) {
        return null;
    }

    $stationen = ocmStationenAufbereiten($pois);
    if (!is_dir(dirname($cacheDatei))) {
        @mkdir(dirname($cacheDatei), 0700, true);
    }
    @file_put_contents($cacheDatei, json_encode($stationen));
    return $stationen;
}

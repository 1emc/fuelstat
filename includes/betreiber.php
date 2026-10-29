<?php
// Angaben zum Betreiber für Impressum und Datenschutzerklärung – an einer Stelle pflegen.
// Leere Pflichtangaben werden auf den Seiten deutlich als fehlend markiert.
return [
    'name' => 'Mario Caraggiu',
    'strasse' => '',
    'plz_ort' => '',
    'email' => '',
    'telefon' => '',              // optional

    // Hosting (Datenschutzerklärung)
    'hoster' => '',               // z. B. "Hetzner Online GmbH, Industriestr. 25, 91710 Gunzenhausen"
    'log_tage' => 14,             // nach wie vielen Tagen die Server-Logfiles gelöscht werden
    'backup_tage' => null,        // nach wie vielen Tagen Datensicherungen überschrieben werden (null = keine Angabe)

    // Zuständige Datenschutz-Aufsichtsbehörde (Sitz des Betreibers)
    'aufsicht' => 'Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Baden-Württemberg, Lautenschlagerstraße 20, 70173 Stuttgart, www.baden-wuerttemberg.datenschutz.de',

    'stand' => 'Oktober 2026',
];

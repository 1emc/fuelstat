/*
 * Menge × Preis pro Einheit = Gesamtpreis.
 * Aus den zwei zuletzt eingetragenen Feldern wird das dritte berechnet und direkt ins Feld geschrieben
 * (sind alle drei eingetragen, bevorzugt der Preis pro Einheit)
 * (also auch gespeichert). Ein Knopf neben jedem Feld zeigt, ob der Wert eingetragen oder berechnet ist;
 * Antippen zeigt die Rechnung.
 *
 * Verwendung:
 *   const rechner = mengenRechner({ einheit: () => 'l', eingetragen: ['kosten', 'menge', 'preis'] });
 *   rechner.setze('preis', 1.749);   // Wert von außen (z. B. Tankstellenpreis) wie eine Eingabe übernehmen
 *   rechner.aktualisiere();          // nach Wechsel der Einheit (Plug-in-Hybrid)
 */
(function () {
    'use strict';

    var FELDER = { menge: 'menge', preis: 'preis_pro_einheit', kosten: 'kosten' };
    var STELLEN = { menge: 2, preis: 3, kosten: 2 };

    function zahlAus(input) {
        var n = parseFloat(String(input.value).replace(',', '.'));
        return isFinite(n) ? n : null;
    }

    function format(n, stellen) {
        return Number(n).toLocaleString('de-DE', { minimumFractionDigits: stellen, maximumFractionDigits: stellen });
    }

    window.mengenRechner = function (optionen) {
        optionen = optionen || {};
        var einheit = optionen.einheit || function () { return 'l'; };
        // Zuletzt eingetragene Felder zuerst; beim Bearbeiten gelten alle vorhandenen Werte als eingetragen
        var reihenfolge = (optionen.eingetragen || []).slice();
        var zustand = {};
        var texte = {};
        var inputs = {};
        var knoepfe = {};

        Object.keys(FELDER).forEach(function (key) {
            inputs[key] = document.getElementById(FELDER[key]);
            knoepfe[key] = document.getElementById('info_' + FELDER[key]);
            zustand[key] = inputs[key] && inputs[key].value !== '' && reihenfolge.indexOf(key) !== -1 ? 'eingetragen' : 'leer';
        });
        reihenfolge = reihenfolge.filter(function (key) { return zustand[key] === 'eingetragen'; });

        function rechnung(key) {
            var e = einheit();
            var m = format(zahlAus(inputs.menge), 2) + ' ' + e;
            var p = format(zahlAus(inputs.preis), 3) + ' €/' + e;
            var k = format(zahlAus(inputs.kosten), 2) + ' €';
            if (key === 'kosten') return m + ' × ' + p + ' = ' + k;
            if (key === 'preis') return k + ' ÷ ' + m + ' = ' + p;
            return k + ' ÷ ' + p + ' = ' + m;
        }

        var NAMEN = { menge: 'Menge', preis: 'Preis pro Einheit', kosten: 'Gesamtpreis' };

        function zeige(key) {
            var knopf = knoepfe[key];
            var input = inputs[key];
            if (!knopf || !input) return;
            var z = zustand[key];
            var icon = knopf.querySelector('i');
            knopf.classList.toggle('btn-outline-info', z === 'berechnet');
            knopf.classList.toggle('btn-outline-secondary', z !== 'berechnet');
            input.classList.toggle('feld-berechnet', z === 'berechnet');
            if (icon) icon.className = 'fas ' + (z === 'berechnet' ? 'fa-calculator' : z === 'eingetragen' ? 'fa-pen' : 'fa-circle-info');
            texte[key] = z === 'berechnet'
                ? 'Berechnet: ' + rechnung(key) + '. Überschreiben ist jederzeit möglich.'
                : z === 'eingetragen'
                    ? 'Von dir eingetragen.'
                    : 'Wird berechnet, sobald zwei der drei Felder (Menge, Preis, Gesamtpreis) ausgefüllt sind.';
            var label = NAMEN[key] + ': ' + (z === 'berechnet' ? 'berechnet' : z === 'eingetragen' ? 'eingetragen' : 'noch leer');
            knopf.setAttribute('aria-label', label);
            knopf.setAttribute('title', label);
        }

        function berechne() {
            // Die zwei zuletzt eingetragenen Felder bestimmen das dritte. Sind alle drei eingetragen, wird der
            // Preis pro Einheit neu berechnet (Menge und Gesamtpreis stehen auf Zapfsäule bzw. Beleg; so rechnet
            // auch die API) – außer man hat gerade den Preis selbst geändert.
            if (reihenfolge.length === 3 && reihenfolge[0] !== 'preis') {
                reihenfolge = reihenfolge.filter(function (k) { return k !== 'preis'; }).concat(['preis']);
            }
            var basis = reihenfolge.slice(0, 2);
            Object.keys(FELDER).forEach(function (key) {
                if (basis.indexOf(key) === -1 && zustand[key] === 'eingetragen' && basis.length === 2) {
                    zustand[key] = 'berechnet';
                    reihenfolge = reihenfolge.filter(function (k) { return k !== key; });
                }
            });
            var ziel = Object.keys(FELDER).filter(function (key) { return basis.indexOf(key) === -1; })[0];
            if (basis.length === 2) {
                var m = zahlAus(inputs.menge), p = zahlAus(inputs.preis), k = zahlAus(inputs.kosten);
                var wert = null;
                if (ziel === 'kosten' && m !== null && p !== null) wert = m * p;
                if (ziel === 'preis' && k !== null && m) wert = k / m;
                if (ziel === 'menge' && k !== null && p) wert = k / p;
                if (wert !== null && isFinite(wert)) {
                    inputs[ziel].value = wert.toFixed(STELLEN[ziel]);
                    zustand[ziel] = 'berechnet';
                } else if (zustand[ziel] === 'berechnet') {
                    inputs[ziel].value = '';
                    zustand[ziel] = 'leer';
                }
            } else {
                // Zu wenig Angaben: früher berechnete Werte wieder leeren
                Object.keys(FELDER).forEach(function (key) {
                    if (zustand[key] === 'berechnet') {
                        inputs[key].value = '';
                        zustand[key] = 'leer';
                    }
                });
            }
            Object.keys(FELDER).forEach(zeige);
        }

        function eingabe(key) {
            reihenfolge = reihenfolge.filter(function (k) { return k !== key; });
            if (inputs[key].value !== '') {
                zustand[key] = 'eingetragen';
                reihenfolge.unshift(key);
            } else {
                zustand[key] = 'leer';
            }
            berechne();
        }

        Object.keys(FELDER).forEach(function (key) {
            if (!inputs[key]) return;
            inputs[key].addEventListener('input', function () { eingabe(key); });
            var knopf = knoepfe[key];
            if (!knopf || !window.bootstrap) return;
            // Antippen zeigt die Erklärung, nächstes Antippen irgendwo schließt sie (funktioniert auch auf dem Handy)
            var popover = new bootstrap.Popover(knopf, {
                trigger: 'manual',
                placement: 'top',
                content: function () { return texte[key]; }
            });
            knopf.addEventListener('click', function (ev) {
                ev.stopPropagation();
                Object.keys(knoepfe).forEach(function (k) {
                    if (k !== key && knoepfe[k]) bootstrap.Popover.getInstance(knoepfe[k]).hide();
                });
                popover.toggle();
            });
        });
        document.addEventListener('click', function () {
            Object.keys(knoepfe).forEach(function (k) {
                var p = knoepfe[k] && window.bootstrap ? bootstrap.Popover.getInstance(knoepfe[k]) : null;
                if (p) p.hide();
            });
        });

        Object.keys(FELDER).forEach(zeige);

        return {
            // Wert von außen setzen und wie eine Eingabe behandeln
            setze: function (key, wert) {
                inputs[key].value = wert;
                eingabe(key);
            },
            zustand: function (key) { return zustand[key]; },
            aktualisiere: function () { Object.keys(FELDER).forEach(zeige); }
        };
    };
})();

/*
 * Menge × Preis pro Einheit = Gesamtpreis.
 * Sind genau zwei Felder eingetragen, wird das dritte berechnet und ins Feld geschrieben (also auch gespeichert).
 * Eingetragene Werte – von Hand oder übernommen, z. B. der Preis der gewählten Tankstelle – werden nie
 * überschrieben (#94). Sind alle drei eingetragen und passen nicht zusammen, erscheint nur ein Hinweis mit
 * Knopf zum Angleichen des Preises. Ein berechneter Preis pro Einheit wird nur übernommen, wenn er plausibel
 * ist (nicht während man den Gesamtpreis noch tippt).
 * Ein Knopf neben jedem Feld zeigt, ob der Wert eingetragen oder berechnet ist; Antippen zeigt die Rechnung.
 *
 * Verwendung:
 *   const rechner = mengenRechner({ einheit: () => 'l', eingetragen: ['kosten', 'menge'], hinweis: 'calcHint' });
 *   rechner.setze('preis', 1.749);   // Wert von außen (z. B. Tankstellenpreis) wie eine Eingabe übernehmen
 *   rechner.aktualisiere();          // nach Wechsel der Einheit (Plug-in-Hybrid)
 */
(function () {
    'use strict';

    var FELDER = { menge: 'menge', preis: 'preis_pro_einheit', kosten: 'kosten' };
    var STELLEN = { menge: 2, preis: 3, kosten: 2 };
    // Plausibler Preis pro Einheit (Strom darf 0 sein: kostenloses Laden)
    var PREIS_SPANNE = { kWh: [0, 2], l: [0.3, 5], kg: [0.3, 5] };

    function plausiblerPreis(p, einheit) {
        var spanne = PREIS_SPANNE[einheit] || [0, 10];
        return p !== null && isFinite(p) && p >= spanne[0] && p <= spanne[1];
    }

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
        var hinweisFeld = optionen.hinweis ? document.getElementById(optionen.hinweis) : null;
        // Vom Nutzer gerade geleertes Feld: nicht sofort wieder füllen, sonst hängt die nächste Eingabe an den alten Wert an
        var geleert = null;
        // Zuletzt eingetragene Felder zuerst; beim Bearbeiten gelten alle vorhandenen Werte als eingetragen
        var reihenfolge = (optionen.eingetragen || []).slice();
        var zustand = {};
        var texte = {};
        var inputs = {};
        var knoepfe = {};

        Object.keys(FELDER).forEach(function (key) {
            inputs[key] = document.getElementById(FELDER[key]);
            knoepfe[key] = document.getElementById('info_' + FELDER[key]);
            // Vorhandene Werte: in "eingetragen" genannt = eingetragen, sonst berechnet (z. B. Preis beim Bearbeiten)
            zustand[key] = !inputs[key] || inputs[key].value === '' ? 'leer' : (reihenfolge.indexOf(key) !== -1 ? 'eingetragen' : 'berechnet');
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
                    : 'Wird berechnet, sobald die beiden anderen Felder (Menge, Preis, Gesamtpreis) ausgefüllt sind.';
            var label = NAMEN[key] + ': ' + (z === 'berechnet' ? 'berechnet' : z === 'eingetragen' ? 'eingetragen' : 'noch leer');
            knopf.setAttribute('aria-label', label);
            knopf.setAttribute('title', label);
        }

        // Alle drei eingetragen, aber Menge × Preis ≠ Gesamt: Hinweis statt stillem Überschreiben
        function abgleich() {
            if (!hinweisFeld) return;
            hinweisFeld.replaceChildren();
            var alle = Object.keys(FELDER).every(function (k) { return zustand[k] === 'eingetragen'; });
            var m = zahlAus(inputs.menge), p = zahlAus(inputs.preis), k = zahlAus(inputs.kosten);
            if (!alle || m === null || p === null || k === null || m <= 0) return;
            var differenz = Math.abs(m * p - k);
            if (differenz <= Math.max(0.02, k * 0.005)) return;
            var e = einheit();
            var neu = k / m;
            hinweisFeld.append(document.createTextNode(
                format(m, 2) + ' ' + e + ' × ' + format(p, 3) + ' €/' + e + ' = ' + format(m * p, 2) + ' €, nicht ' + format(k, 2) + ' €. '
                + 'Gespeichert wird der Preis aus Gesamt ÷ Menge: ' + format(neu, 3) + ' €/' + e + '. '));
            var knopf = document.createElement('button');
            knopf.type = 'button';
            knopf.className = 'btn btn-link btn-sm p-0 align-baseline';
            knopf.textContent = 'Preis angleichen';
            knopf.addEventListener('click', function () {
                inputs.preis.value = neu.toFixed(STELLEN.preis);
                zustand.preis = 'berechnet';
                reihenfolge = reihenfolge.filter(function (x) { return x !== 'preis'; });
                berechne();
            });
            hinweisFeld.append(knopf);
        }

        function berechne() {
            // Nur wenn genau zwei Felder eingetragen sind, wird das dritte berechnet; eingetragene Werte bleiben unangetastet
            var eingetragen = Object.keys(FELDER).filter(function (key) { return zustand[key] === 'eingetragen'; });
            if (eingetragen.length === 2) {
                var ziel = Object.keys(FELDER).filter(function (key) { return eingetragen.indexOf(key) === -1; })[0];
                var m = zahlAus(inputs.menge), p = zahlAus(inputs.preis), k = zahlAus(inputs.kosten);
                var wert = null;
                if (ziel === geleert) ziel = null;
                if (ziel === 'kosten' && m !== null && p !== null) wert = m * p;
                if (ziel === 'preis' && k !== null && m) wert = k / m;
                if (ziel === 'menge' && k !== null && p) wert = k / p;
                // Preis nur übernehmen, wenn er plausibel ist (z. B. nicht 0,175 €/l, während man „72,10“ noch tippt)
                if (ziel === 'preis' && !plausiblerPreis(wert, einheit())) wert = null;
                if (wert !== null && isFinite(wert)) {
                    inputs[ziel].value = wert.toFixed(STELLEN[ziel]);
                    zustand[ziel] = 'berechnet';
                } else if (ziel && zustand[ziel] === 'berechnet') {
                    inputs[ziel].value = '';
                    zustand[ziel] = 'leer';
                }
            } else if (eingetragen.length < 2) {
                // Zu wenig Angaben: früher berechnete Werte wieder leeren
                Object.keys(FELDER).forEach(function (key) {
                    if (zustand[key] === 'berechnet') {
                        inputs[key].value = '';
                        zustand[key] = 'leer';
                    }
                });
            }
            Object.keys(FELDER).forEach(zeige);
            abgleich();
        }

        function eingabe(key) {
            reihenfolge = reihenfolge.filter(function (k) { return k !== key; });
            geleert = inputs[key].value === '' ? key : null;
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
            aktualisiere: function () { berechne(); }
        };
    };
})();

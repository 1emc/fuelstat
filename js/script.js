/*
 * Seitenwechsel (#89)
 *
 * 1. Ladebalken: Beim Antippen eines Links oder Absenden eines Formulars erscheint oben sofort ein Balken.
 *    Ab 300 ms wird der Inhalt leicht abgeblendet, damit man sieht, dass etwas passiert.
 * 2. Zurück: Seiten dürfen in den Back-Forward-Cache des Browsers (kein "no-store" mehr, siehe
 *    includes/session.php). "Zurück" zeigt die vorige Seite dann sofort. Wurde inzwischen etwas
 *    gespeichert (POST-Formular) oder abgemeldet, oder ist die Seite älter als 10 Minuten, wird sie neu geladen.
 */
(function () {
    'use strict';

    var SCHLUESSEL = 'fuelstat-geaendert';
    var MAX_ALTER = 10 * 60 * 1000;
    var geladen = Date.now();
    var balken = null;
    var abblenden = null;

    function speichern(wert) {
        try { localStorage.setItem(SCHLUESSEL, String(wert)); } catch (e) { /* privat: dann eben ohne */ }
    }
    function letzteAenderung() {
        try { return Number(localStorage.getItem(SCHLUESSEL)) || 0; } catch (e) { return 0; }
    }

    function start() {
        if (!balken) {
            balken = document.createElement('div');
            balken.className = 'lade-balken';
            balken.setAttribute('role', 'progressbar');
            balken.setAttribute('aria-label', 'Seite wird geladen');
            document.body.appendChild(balken);
        }
        balken.classList.remove('fertig');
        // Erst sichtbar machen, dann im nächsten Frame losfahren (sonst springt die Animation)
        balken.classList.remove('laeuft');
        void balken.offsetWidth;
        balken.classList.add('laeuft');
        clearTimeout(abblenden);
        abblenden = setTimeout(function () { document.body.classList.add('seite-laedt'); }, 300);
        // Sicherheitsnetz: kommt nach 15 s keine neue Seite (z. B. Download, Abbruch), Balken wieder ausblenden
        clearTimeout(start.notaus);
        start.notaus = setTimeout(stopp, 15000);
    }

    function stopp() {
        clearTimeout(abblenden);
        document.body.classList.remove('seite-laedt');
        if (balken) {
            balken.classList.remove('laeuft');
            balken.classList.add('fertig');
        }
    }

    // Führt dieser Klick zu einem echten Seitenwechsel in der App?
    function istSeitenwechsel(e, link) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return false;
        if (!link.href || link.target && link.target !== '_self' || link.hasAttribute('download')) return false;
        if (link.dataset.bsToggle || link.getAttribute('role') === 'tab') return false;
        var ziel = new URL(link.href, location.href);
        if (ziel.origin !== location.origin || !/^https?:$/.test(ziel.protocol)) return false;
        // Nur Anker auf derselben Seite (z. B. #eintraege): kein Seitenwechsel
        if (ziel.pathname === location.pathname && ziel.search === location.search && ziel.hash) return false;
        return true;
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('a[href]');
        if (!link) return;
        // Abmelden: offene Seiten im Back-Forward-Cache beim Zurückgehen neu laden (führen dann zum Login)
        if (/\/logout\.php(\?|$)/.test(link.getAttribute('href') || '')) speichern(Date.now());
        // Erst nach anderen Klick-Handlern entscheiden (die rufen evtl. preventDefault auf)
        setTimeout(function () { if (istSeitenwechsel(e, link)) start(); }, 0);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        setTimeout(function () {
            if (e.defaultPrevented || form.target && form.target !== '_self') return;
            // Gespeichert wird per POST: Seiten im Back-Forward-Cache sind danach veraltet
            if ((form.getAttribute('method') || 'get').toLowerCase() === 'post') speichern(Date.now());
            // Downloads (z. B. CSV-Export) laden keine neue Seite
            if (form.hasAttribute('data-download')) return;
            start();
        }, 0);
    });

    window.addEventListener('pageshow', function (e) {
        stopp();
        if (!e.persisted) return;
        // Aus dem Back-Forward-Cache zurück: nur neu laden, wenn sich seither etwas geändert hat
        if (letzteAenderung() > geladen || Date.now() - geladen > MAX_ALTER) {
            start();
            location.reload();
        }
    });
    // Beim Verlassen der Seite nichts tun (kein "unload": der würde den Back-Forward-Cache verhindern)
    window.addEventListener('pagehide', function () { clearTimeout(abblenden); });
})();

/*
 * Fahrzeug-Reiter auf dem Handy per Wischen wechseln (#88).
 * Nach links wischen = nächster Reiter, nach rechts = vorheriger; am ersten bzw. letzten Reiter passiert nichts.
 *
 * Damit Scrollen und Bedienen ungestört bleiben:
 * - nur auf schmalen Bildschirmen (unter 992 px, wie die mobile Ansicht) und nur mit einem Finger
 * - die Listener sind passiv: das Wischen blockiert nie das vertikale Scrollen
 * - erst eindeutig waagerecht (Richtung wird nach den ersten Pixeln festgelegt), weit genug und zügig genug
 * - nicht in Eingabefeldern, Auswahllisten, Diagrammen (canvas), offenen Menüs, waagerecht scrollbaren
 *   Bereichen (z. B. breite Tabellen) und nicht am Bildschirmrand (Zurück-Geste von iOS/Android)
 * - ein Antippen ist nie ein Wischen: Knöpfe und Links funktionieren wie gewohnt
 *
 * Verwendung: reiterWischen({ reiter: <ul class="nav-tabs">, bereich: <Inhalt>, wechsel: function (link) {} })
 */
(function () {
    'use strict';

    var MIN_WEG = 60;          // px waagerecht
    var MIN_ANTEIL = 0.18;     // bzw. Anteil der Breite, das Größere gilt
    var RICHTUNG_AB = 12;      // px, ab denen die Richtung feststeht
    var MAX_DAUER = 800;       // ms
    var RAND = 24;             // px vom Bildschirmrand: System-Geste, nicht unsere
    var AUSNAHMEN = 'input, textarea, select, [contenteditable=""], [contenteditable="true"], canvas, .dropdown-menu, .no-swipe';

    // Liegt das Element in einem Bereich, der selbst waagerecht scrollen kann?
    function scrolltWaagerecht(el, grenze) {
        for (; el && el !== grenze; el = el.parentElement) {
            if (el.scrollWidth > el.clientWidth + 1) {
                var ox = getComputedStyle(el).overflowX;
                if (ox === 'auto' || ox === 'scroll') return true;
            }
        }
        return false;
    }

    window.reiterWischen = function (optionen) {
        var reiter = optionen.reiter;
        var bereich = optionen.bereich;
        var mobil = window.matchMedia('(max-width: 991.98px)');
        var start = null;

        function links() {
            return Array.prototype.slice.call(reiter.querySelectorAll('.nav-link')).filter(function (l) {
                return !l.classList.contains('disabled') && l.offsetParent !== null;
            });
        }

        bereich.addEventListener('touchstart', function (e) {
            start = null;
            if (!mobil.matches || e.touches.length !== 1) return;
            var t = e.touches[0];
            if (t.clientX < RAND || t.clientX > window.innerWidth - RAND) return;
            if (e.target.closest && e.target.closest(AUSNAHMEN)) return;
            if (scrolltWaagerecht(e.target, bereich)) return;
            start = { x: t.clientX, y: t.clientY, zeit: Date.now(), richtung: null };
        }, { passive: true });

        bereich.addEventListener('touchmove', function (e) {
            if (!start) return;
            if (e.touches.length !== 1) { start = null; return; }
            if (start.richtung) return;
            var dx = e.touches[0].clientX - start.x;
            var dy = e.touches[0].clientY - start.y;
            if (Math.abs(dx) < RICHTUNG_AB && Math.abs(dy) < RICHTUNG_AB) return;
            // Erst senkrecht bewegt: das ist Scrollen, kein Wischen
            start.richtung = Math.abs(dx) > Math.abs(dy) * 1.5 ? 'waagerecht' : 'senkrecht';
            if (start.richtung === 'senkrecht') start = null;
        }, { passive: true });

        bereich.addEventListener('touchcancel', function () { start = null; }, { passive: true });

        bereich.addEventListener('touchend', function (e) {
            var s = start;
            start = null;
            if (!s || s.richtung !== 'waagerecht') return;
            var t = e.changedTouches[0];
            var dx = t.clientX - s.x;
            var dy = t.clientY - s.y;
            var noetig = Math.max(MIN_WEG, window.innerWidth * MIN_ANTEIL);
            if (Math.abs(dx) < noetig || Math.abs(dx) < Math.abs(dy) * 1.5 || Date.now() - s.zeit > MAX_DAUER) return;
            // Markierter Text: der Nutzer wollte auswählen, nicht wechseln
            var auswahl = window.getSelection && window.getSelection();
            if (auswahl && !auswahl.isCollapsed) return;

            var liste = links();
            var aktiv = liste.findIndex(function (l) { return l.classList.contains('active'); });
            var ziel = aktiv + (dx < 0 ? 1 : -1);
            if (aktiv < 0 || ziel < 0 || ziel >= liste.length) return;
            bereich.dataset.wischRichtung = dx < 0 ? 'vor' : 'zurueck';
            optionen.wechsel(liste[ziel]);
        }, { passive: true });
    };
})();

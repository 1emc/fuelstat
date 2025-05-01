<?php
// Debug-Parameter
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// tabs/allgemein.php

// Fahrzeugdetails werden von fahrzeug_detail.php bereitgestellt (via $fahrzeug)
$initial_tachostand = $fahrzeug['tachostand']; // Initialer Tachostand aus dem Fahrzeugobjekt
$aktueller_tachostand = getAktuellenTachostand($fahrzeug_id);

// Hole die letzten Einträge
$letzterReifen = getLetzterEintrag($fahrzeug_id, 'Reifen');
$letzteWartung = getLetzterEintrag($fahrzeug_id, 'Wartung');
$letzterTUV = getLetzterEintrag($fahrzeug_id, 'TUV');

// Berechne die Fahrleistung (aktuelle km - initiale km)
$fahrleistung = berechneFahrleistung($fahrzeug_id, $initial_tachostand);
?>

<div class="mt-4">
    <div class="row">
        <div class="col-md-4">
            <!-- Fahrzeugbild -->
            <?php if (!empty($fahrzeug['bild'])): ?>
                <img src="../images/<?php echo htmlspecialchars($fahrzeug['bild']); ?>" alt="Fahrzeugbild" class="img-fluid">
            <?php else: ?>
                <img src="../images/platzhalter.jpg" alt="Fahrzeugbild" class="img-fluid">
            <?php endif; ?>
        </div>
        <div class="col-md-8">
            <!-- Fahrzeugdetails -->
            <h2><?php echo htmlspecialchars($fahrzeug['marke'] . ' ' . $fahrzeug['modell']); ?></h2>
            <p><strong>Tachostand:</strong>
				<?php
					echo $aktueller_tachostand !== null
					  ? number_format($aktueller_tachostand, 0, ',', '.') . ' km'
					  : 'Keine Daten';
				?>
			</p>
            <p><strong>Fahrleistung:</strong>
				<?php
					echo $fahrleistung !== null
					  ? number_format($fahrleistung, 0, ',', '.') . ' km'
					  : 'Keine Daten';
				?>
			</p>
            
            <!-- Kraftstoffverbrauch -->
            <h3>Kraftstoffverbrauch</h3>
            <p><strong>Gesamt:</strong>
			<?php
				$gesamt = berechneGesamtverbrauch($fahrzeug_id);
				echo $gesamt !== null
				  ? number_format($gesamt, 2, ',', '.') . ' l/100km'
				  : 'Keine Daten';
			?>
			</p>
            <p><strong>Aktuell:</strong> 
			<?php
				$aktuell = berechneAktuellenVerbrauch($fahrzeug_id);
				echo $aktuell !== null
				  ? number_format($aktuell, 2, ',', '.') . ' l/100km ' . zeigeVerbrauchstendenz($fahrzeug_id)
				  : 'Keine Daten'; 
			?></p>
            
            <!-- Kosten per Kilometer -->
            <h3>Kosten per Kilometer</h3>
			<?php
				$kostenGesamt = berechneKostenProKmGesamt($fahrzeug_id);
				$kostenTanken = berechneKraftstoffkostenProKm($fahrzeug_id);
			?>
            <p><strong>Gesamt:</strong> 
			<?php
				echo $kostenGesamt !== null
				  ? number_format($kostenGesamt, 2, ',', '.') . ' €/km'
				  : 'Keine Daten';
			?></p>
            <p><strong>Tanken:</strong>
			<?php
				echo $kostenTanken !== null
				  ? number_format($kostenTanken, 2, ',', '.') . ' €/km'
				  : 'Keine Daten';
			?></p>
            
            <!-- Wartungshistorie -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <h3>Wartungshistorie</h3>
                <a href="eintrag_hinzufuegen.php?fahrzeug_id=<?php echo $fahrzeug_id; ?>&kategorie=Wartung" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-plus"></i> Eintrag hinzufügen
                </a>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?php echo getCategoryIcon('Reifen'); ?> text-primary me-2"></i>
                                <?php echo getKategorieName('Reifen'); ?>
                            </h5>
                            <?php if ($letzterReifen): ?>
                                <p class="card-text">
                                    <strong>Letzter Wechsel:</strong><br>
                                    <span class="text-muted">
                                        <?php echo date('d.m.Y', strtotime($letzterReifen['datum'])); ?><br>
                                        bei <?php echo number_format($letzterReifen['tachostand'], 0, ',', '.'); ?> km
                                    </span>
                                </p>
                                <?php if ($letzterReifen['beschreibung']): ?>
                                    <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($letzterReifen['beschreibung']); ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine Reifenwechsel erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?php echo getCategoryIcon('Wartung'); ?> text-primary me-2"></i>
                                Kundendienst
                            </h5>
                            <?php if ($letzteWartung): ?>
                                <p class="card-text">
                                    <strong>Letzte Wartung:</strong><br>
                                    <span class="text-muted">
                                        <?php echo date('d.m.Y', strtotime($letzteWartung['datum'])); ?><br>
                                        bei <?php echo number_format($letzteWartung['tachostand'], 0, ',', '.'); ?> km
                                    </span>
                                </p>
                                <?php if ($letzteWartung['beschreibung']): ?>
                                    <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($letzteWartung['beschreibung']); ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine Wartungen erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas <?php echo getCategoryIcon('TUV'); ?> text-primary me-2"></i>
                                <?php echo getKategorieName('TUV'); ?>
                            </h5>
                            <?php if ($letzterTUV): ?>
                                <p class="card-text">
                                    <strong>Letzter TÜV:</strong><br>
                                    <span class="text-muted">
                                        <?php echo date('d.m.Y', strtotime($letzterTUV['datum'])); ?><br>
                                        bei <?php echo number_format($letzterTUV['tachostand'], 0, ',', '.'); ?> km
                                    </span>
                                </p>
                                <?php if ($letzterTUV['beschreibung']): ?>
                                    <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($letzterTUV['beschreibung']); ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="card-text text-muted">Keine TÜV-Einträge erfasst</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bearbeiten- und Löschen-Buttons -->
	<div class="d-flex gap-2 mt-3">
		<a href="fahrzeug_bearbeiten.php?id=<?php echo $fahrzeug_id; ?>" class="btn btn-primary">
			<i class="fas fa-edit"></i> Bearbeiten
		</a>

		<form method="post" action="fahrzeug_loeschen.php" onsubmit="return confirm('Möchtest du dieses Fahrzeug wirklich löschen? Alle zugehörigen Einträge werden ebenfalls entfernt!');">
			<input type="hidden" name="fahrzeug_id" value="<?php echo $fahrzeug_id; ?>">
			<button type="submit" class="btn btn-danger">
				<i class="fas fa-trash-alt"></i> Löschen
			</button>
		</form>
	</div>

	
</div>



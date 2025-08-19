-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 19. Aug 2025 um 10:15
-- Server-Version: 10.4.32-MariaDB
-- PHP-Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
				   
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `d0417106`
--
CREATE DATABASE IF NOT EXISTS `d0417106` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `d0417106`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer`
--

CREATE TABLE `benutzer` (
  `id` int(32) NOT NULL,
  `username` varchar(128) NOT NULL,
  `password` varchar(128) NOT NULL,
  `email` varchar(128) NOT NULL,
  `mfa_secret` varchar(32) DEFAULT NULL,
  `mfa_enabled` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer_sessions`
--

CREATE TABLE `benutzer_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `eintraege`
--

CREATE TABLE `eintraege` (
  `id` int(32) NOT NULL,
  `fahrzeug_id` int(32) NOT NULL,
  `kraftstoff` enum('diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other') DEFAULT NULL,
  `kategorie` enum('Tankfuellung','Versicherung','Steuer','Inspektion','Reparatur','Reifen','TUV','Wartung','Dekor','Verbrauch') NOT NULL,
  `datum` date NOT NULL,
  `tachostand` int(64) NOT NULL,
  `standort` varchar(128) NOT NULL,
  `kosten` decimal(32,4) NOT NULL,
  `preis_pro_einheit` double(32,4) NOT NULL,
  `menge` decimal(32,4) NOT NULL,
  `vollgetankt` bit(1) NOT NULL,
  `standort_bezeichnung` varchar(32) NOT NULL,
  `beschreibung` text NOT NULL,
  `skip_previous` bit(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  
								 
  

																																																								  
																													   
																													   
																													   
																													
																													  
																														
																															  
																													  
																													 
																														
																													   
																														
																													 
																													   
																														
																														
																											 
																													   
																													   
																											   
																													  
																													  
																													 

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `fahrzeuge`
--

CREATE TABLE `fahrzeuge` (
  `id` int(32) NOT NULL,
  `benutzer_id` int(32) NOT NULL,
  `marke` varchar(128) NOT NULL,
  `modell` varchar(128) NOT NULL,
  `baujahr` year(4) NOT NULL,
  `bild` varchar(256) NOT NULL,
  `tachostand` int(16) NOT NULL,
  `fahrleistung` int(16) NOT NULL,
  `tankgroesse` decimal(16,0) NOT NULL,
  `kraftstoff` enum('diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other') NOT NULL DEFAULT 'diesel',
  `sortierung` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indizes der exportierten Tabellen
--

																																	   
																						  
																					
																						  
																													  
														

														   

--
-- Indizes für die Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  ADD PRIMARY KEY (`id`);

								  
									  
						 
									 
									 
									
								 
									
												
								
										
														
					 
																   

--
-- Indizes für die Tabelle `benutzer_sessions`
--
ALTER TABLE `benutzer_sessions`
  
									  
  
					  
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `eintraege`
--
ALTER TABLE `eintraege`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `fahrzeuge`
--
ALTER TABLE `fahrzeuge`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `benutzer_sessions`
--
ALTER TABLE `benutzer_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `eintraege`
--
ALTER TABLE `eintraege`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `fahrzeuge`
--
ALTER TABLE `fahrzeuge`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

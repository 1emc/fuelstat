-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Erstellungszeit: 01. Mai 2025 um 01:06
-- Server-Version: 10.11.11-MariaDB-0ubuntu0.24.04.2-log
-- PHP-Version: 7.4.33-nmm7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `d0417106`
--

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
-- Tabellenstruktur für Tabelle `eintraege`
--

CREATE TABLE `eintraege` (
  `id` int(32) NOT NULL,
  `fahrzeug_id` int(32) NOT NULL,
  `kraftstoff` ENUM('diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other') DEFAULT NULL,
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

--
-- Daten für Tabelle `eintraege`
--

INSERT INTO `eintraege` (`id`, `fahrzeug_id`, `kraftstoff`, `kategorie`, `datum`, `tachostand`, `standort`, `kosten`, `preis_pro_einheit`, `menge`, `vollgetankt`, `standort_bezeichnung`, `beschreibung`, `skip_previous`) VALUES
(5, 4, 'diesel', 'Tankfuellung', '2018-03-01', 21065, 'Sigmaringen', '59.2800', 1.2500, '47.4600', b'1', '', '', b'0'),
(6, 4, 'diesel', 'Tankfuellung', '2018-03-03', 21663, 'Sigmaringen', '43.5900', 1.2700, '34.3500', b'1', '', '', b'0'),
(7, 4, 'diesel', 'Tankfuellung', '2018-03-05', 22178, 'Sigmaringen', '33.6500', 1.1890, '28.3000', b'1', '', '', b'0'),
(8, 4, 'diesel', 'Tankfuellung', '2018-03-10', 23037, 'Albstadt', '61.4100', 1.1790, '52.0900', b'1', '', '', b'0'),
(9, 4, 'diesel', 'Tankfuellung', '2018-03-17', 23488, 'Tuttlingen', '26.1500', 1.1490, '22.7600', b'1', '', '', b'0'),
(10, 4, 'diesel', 'Tankfuellung', '2018-03-24', 24267, 'Sigmaringen', '52.4200', 1.1990, '43.7200', b'1', '', '', b'0'),
(11, 4, 'Versicherung', '2018-03-24', 24267, 'Sigmaringen', '615.6500', 0.0000, '0.0000', b'0', '', 'AachenMünchener', b'0'),
(12, 4, 'diesel', 'Tankfuellung', '2018-03-28', 25160, 'Fridingen', '58.7700', 1.2290, '47.8200', b'1', '', '', b'0'),
(13, 4, 'diesel', 'Tankfuellung', '2018-04-06', 25949, 'Fridingen', '10.0000', 1.2090, '8.2700', b'0', '', '', b'0'),
(18, 4, 'diesel', 'Tankfuellung', '2018-04-07', 26090, 'Sigmaringen', '58.0000', 1.2090, '47.9700', b'1', '', '', b'0'),
(19, 4, 'diesel', 'Tankfuellung', '2018-04-11', 26792, 'Tuttlingen', '25.0000', 1.2690, '19.7000', b'0', '', '', b'0'),
(20, 4, 'diesel', 'Tankfuellung', '2018-04-12', 27178, 'Sigmaringen', '35.0100', 1.1990, '29.2000', b'0', '', '', b'0'),
(21, 4, 'diesel', 'Tankfuellung', '2018-04-16', 27797, 'Autobahn', '67.8400', 1.4590, '46.5000', b'1', '', '', b'0'),
(22, 4, 'diesel', 'Tankfuellung', '2018-04-24', 28568, 'Tuttlingen', '35.0000', 1.2390, '28.2500', b'0', '', '', b'0'),
(23, 4, 'diesel', 'Tankfuellung', '2018-04-29', 29047, 'Sigmaringen', '56.6300', 1.2100, '46.8000', b'1', '', '', b'0'),
(24, 4, 'diesel', 'Tankfuellung', '2018-05-06', 29785, 'Sigmaringen', '65.3300', 1.4990, '43.5800', b'1', '', '', b'0'),
(27, 4, 'diesel', 'Tankfuellung', '2025-04-26', 31261, '', '65.3300', 1.4990, '43.5800', b'1', '', '', b'0'),
(29, 4, 'diesel', 'Tankfuellung', '2025-04-25', 30523, 'Tuttlingen', '65.3300', 1.4990, '43.5800', b'1', '', '', b'0'),
(30, 4, 'diesel', 'Tankfuellung', '2025-04-29', 31333, 'Tuttlingen', '62.3000', 1.7800, '35.0000', b'1', '', '', b'0'),
(31, 4, 'diesel', 'TUV', '2025-04-29', 31333, '', '785.0000', 0.0000, '0.0000', b'0', '', 'TÃœV halt', b'0'),
(32, 6, 'diesel', 'Tankfuellung', '2025-03-29', 80, 'Tuttlingen', '199.0000', 1.9900, '100.0000', b'1', '', '', b'0'),
(33, 6, 'diesel', 'Tankfuellung', '2025-04-30', 160, 'Tuttlingen', '155.4700', 1.7870, '87.0000', b'1', '', '', b'0'),
(34, 6, 'diesel', 'Tankfuellung', '2025-04-29', 200, 'Tuttlingen', '15.0000', 1.2500, '12.0000', b'1', '', '', b'0');

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
  `kraftstoff` ENUM('diesel','e5','e10','lpg','cng','electric','hybrid','hydrogen','other') NOT NULL DEFAULT 'diesel'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `fahrzeuge`
--

INSERT INTO `fahrzeuge` (`id`, `benutzer_id`, `marke`, `modell`, `baujahr`, `bild`, `tachostand`, `fahrleistung`, `tankgroesse`) VALUES
(4, 1, 'Kia', 'Ceed (Demo)', 1990, '6812a6377fa4c_6810c35024344_ceed.jpg', 21065, 0, '0'),
(5, 1, 'SKODA', 'Fabia', 2017, '6812a640cd0fb_68112b067bf98_fabia.jpg', 0, 0, '45'),
(6, 1, 'Bugatti', 'Chiron', 2016, '6812a649d872b_68112c79add65_Bugatti.jpg', 0, 0, '100'),
(10, 1, 'Renault', 'Clio', 1998, '6812a664118ba_6811f9f12354e_file_00000000983461f7b3434775ac0659ed.jpg', 0, 0, '42'),
(13, 1, 'Volkswagen', 'Golf', 2012, '', 89000, 0, '55');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer_sessions`
--

CREATE TABLE `benutzer_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_id` VARCHAR(128) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `login_time` DATETIME NOT NULL,
  `last_activity` DATETIME NOT NULL,
  UNIQUE KEY `session_id_UNIQUE` (`session_id`),
  KEY `user_id_idx` (`user_id`),
  CONSTRAINT `fk_benutzer_sessions_user`
    FOREIGN KEY (`user_id`) REFERENCES `benutzer` (`id`)
    ON DELETE CASCADE
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
-- AUTO_INCREMENT für Tabelle `eintraege`
--
ALTER TABLE `eintraege`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT für Tabelle `fahrzeuge`
--
ALTER TABLE `fahrzeuge`
  MODIFY `id` int(32) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

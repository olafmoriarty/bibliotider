<?php

/*
Plugin Name:  Åpningstider og romreservering for bibliotek
Plugin URI:   https://github.com/olafmoriarty/bibliotektider/
Description:  Registrering og visning av åpningstider, tilpasset bibliotek. Skiller mellom betjent, selvbetjent og meråpent.
Version:      0.0.1
Author:       Olaf Moriarty Solstrand, Ski bibliotek
Author URI:   http://skibibliotek.no
License:      
License URI:  
Text Domain:  bibliotektider
Domain Path:  /sprak
*/

class bibliotektider {
	function dag($dato, $filial = 1, $format = 'array') {
		// $dato må være i formatet '2018-02-28'
		$aar = substr($dato, 0, 4);

		// Finn unntak
		$query = 'SELECT betjent, starttid, sluttid FROM tider WHERE filial = '.$f.' AND '.$dato.' BETWEEN startdato AND sluttdato ORDER BY betjent';

		// *** HENT RADER FRA DATABASEN

		// *** DERSOM num_rows ER NULL
		$query = 'SELECT t.betjent, t.starttid, t.sluttid FROM tider AS t LEFT JOIN perioder AS p ON t.periode = p.id WHERE t.filial = '.$f.' AND ((p.spesiell = 0 AND '.$d.' BETWEEN DATE_FORMAT(p.startdato, \''.$aar.'-%m-%d\') AND DATE_FORMAT(p.sluttdato, \''.$aar.'-%m-%d\')) OR (p.spesiell = 1 AND ('.$dato.' >= DATE_FORMAT(p.startdato, \''.$aar.'-%m-%d\') OR '.$dato.' <= DATE_FORMAT(p.sluttdato, \''.$aar.'-%m-%d\')))) AND t.ukedag = WEEKDAY('.$dato.') + 1';
	}
}

?

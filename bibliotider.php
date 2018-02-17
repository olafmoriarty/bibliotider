<?php

/*
Plugin Name:  Bibliotekets åpningstider og reservering av grupperom
Plugin URI:   https://github.com/olafmoriarty/bibliotider/
Description:  Registrering og visning av åpningstider, tilpasset bibliotek. Skiller mellom betjent, selvbetjent og meråpent.
Version:      0.0.1
Author:       Ski bibliotek
Author URI:   http://skibibliotek.no
License:      
License URI:  
Text Domain:  bibliotider
Domain Path:  /sprak
*/


// Opprett MySQL-tabeller
$bibliotider = new bibliotider;
register_activation_hook( __FILE__, array($bibliotider, 'mysql_install'));



// ----- Hovedklassen -----

class bibliotider {

	// Wordpress-databasen (settes i tabnavn)
	var $tabnavn;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->tabnavn = $wpdb->prefix.'bibliotider';
	}

	// ----- Opprett MySQL-tabellene -----

	function mysql_install() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$charset_collate = $wpdb->get_charset_collate();

		// Tabell over tider for gitte tidspunkter
		$tabell = $this->tabnavn;

		$sql = "CREATE TABLE $tabell (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  filial tinyint(3) NOT NULL,
		  periode tinyint(3) NOT NULL,
		  betjent tinyint(3) NOT NULL,
		  ukedag tinyint(1) NOT NULL,
		  starttid time DEFAULT '00:00:00' NOT NULL,
		  sluttid time DEFAULT '00:00:00' NOT NULL,
		  u_navn varchar(100) NOT NULL,
		  u_startdato date DEFAULT '0000-00-00' NOT NULL,
		  u_sluttdato date DEFAULT '0000-00-00' NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Tabell over perioder (sommertid/vintertid)
		$tabell = $this->tabnavn.'_perioder';
		$sql = "CREATE TABLE $tabell (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  navn varchar(100) NOT NULL,
		  startdato date DEFAULT '0000-00-00' NOT NULL,
		  sluttdato date DEFAULT '0000-00-00' NOT NULL,
		  spesiell tinyint(1) NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Versjonskontroll
		add_option('bibliotider_tabellversjon', '0.1');

		// Legger til default informasjon om perioder dersom tabellen er tom
		$num = $wpdb->get_var('SELECT COUNT(*) FROM '.$this->tabnavn.'_perioder');
		if (!$num) {
			$wpdb->insert($this->tabnavn.'_perioder', ['navn' => __('Vintertid', 'bibliotider'), 'startdato' => '2012-09-01', 'sluttdato' => '2012-06-30']);
			$wpdb->insert($this->tabnavn.'_perioder', ['navn' => __('Sommertid', 'bibliotider'), 'startdato' => '2012-06-01', 'sluttdato' => '2012-08-31']);
		}

		if (!get_option('bibliotider_filialer')) {
			// Legger default liste over filialer i options
			update_option('bibliotider_filialer', array( __('Hovedbiblioteket', 'bibliotider') ));
		}

		if (!get_option('bibliotider_betjent')) {
			// Legger default liste over typer åpningstid i options
			update_option('bibliotider_betjent', array( [__('Betjent', 'bibliotider'), __('Biblioteket er åpent for alle!', 'bibliotider')], [__('Selvbetjent', 'bibliotider'), __('Biblioteket er åpent for alle, men skranken og telefonen er ikke betjent.', 'bibliotider')], [__('Meråpent', 'bibliotider'), __('Biblioteket er stengt, men brukere som har inngått avtale om tilgang til Meråpent bibliotek kan låse seg inn med lånekortet og benytte biblioteket.', 'bibliotider')] ) );
		}

	}

	// Finn åpningstidene for en bestemt dag
	
	function dag($dato, $filial = 1, $format = 'array') {
		// $dato må være i formatet '2018-02-28'
		$aar = substr($dato, 0, 4);

		// Finn unntak
		$query = 'SELECT betjent, u_starttid AS starttid, u_sluttid AS sluttid, u_navn AS navn FROM '.$this->tabnavn.' WHERE filial = '.$filial.' AND '.$dato.' BETWEEN u_startdato AND u_sluttdato ORDER BY betjent';

		$result = $wpdb->get_results( $query, OBJECT_K );

		if (0 == $result->num_rows) {
			$query = 'SELECT t.betjent, t.starttid, t.sluttid, p.navn FROM '.$this->tabnavn.' AS t LEFT JOIN '.$this->tabnavn.'_perioder AS p ON t.periode = p.id WHERE t.filial = '.$filial.' AND ((p.spesiell = 0 AND '.$d.' BETWEEN DATE_FORMAT(p.startdato, \''.$aar.'-%m-%d\') AND DATE_FORMAT(p.sluttdato, \''.$aar.'-%m-%d\')) OR (p.spesiell = 1 AND ('.$dato.' >= DATE_FORMAT(p.startdato, \''.$aar.'-%m-%d\') OR '.$dato.' <= DATE_FORMAT(p.sluttdato, \''.$aar.'-%m-%d\')))) AND t.ukedag = WEEKDAY('.$dato.') + 1';

			$result = $wpdb->get_results( $query, OBJECT_K );
		}
		return $result;
	}
}
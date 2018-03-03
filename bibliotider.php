<?php

/*
Plugin Name:  Bibliotekets åpningstider
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


// Oppretter MySQL-tabeller
$bibliotider = new bibliotider;
register_activation_hook( __FILE__, array( $bibliotider, 'mysql_install' ) );

// Oppretter widget
add_action( 'widgets_init', function() {
	register_widget( 'Bibliotider_Widget' );
});

// Oppretter Innstillinger-side i dashboardmenyen
add_action( 'admin_menu', array( $bibliotider, 'meny' ) );

// Legger til CSS
add_action( 'wp_enqueue_scripts', array( $bibliotider, 'css_hoved' ) );
add_action( 'admin_enqueue_scripts', array( $bibliotider, 'css_admin' ) );

// Oppretter side for åpningstider
add_filter( 'the_content', array( $bibliotider, 'vis_tider' ) );






// ----- Hovedklassen -----

class bibliotider {

	// Wordpress-databasen (settes i tabnavn)
	var $tabnavn;

	// Er vis_tider() kjørt?
	var $vis_tider_kjort;
	
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->tabnavn = $wpdb->prefix . 'bibliotider';
	}

	// Legg til CSS for innstillingssidene
	function css_admin() {
		wp_register_style( 'bibliotider-admin-css', plugins_url( 'admin.css', __FILE__ ) );
		wp_enqueue_style( 'bibliotider-admin-css' );
	}

	// Legg til CSS for publikumssidene
	function css_hoved() {
		wp_register_style( 'bibliotider-css', plugins_url( 'bt.css', __FILE__ ) );
		wp_enqueue_style( 'bibliotider-css' );
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
		$tabell = $this->tabnavn . '_perioder';
		$sql = "CREATE TABLE $tabell (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  navn varchar(100) NOT NULL,
		  startdato date DEFAULT '0000-00-00' NOT NULL,
		  sluttdato date DEFAULT '0000-00-00' NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta( $sql );

		// Versjonskontroll
		update_option( 'bibliotider_tabellversjon', '0.1' );

		// Legger til default informasjon om perioder dersom tabellen er tom
		$num = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->tabnavn . '_perioder' );
		if ( ! $num ) {
			$wpdb->insert(
				$this->tabnavn . '_perioder', 
				array( 
					'navn'      => __( 'Vintertid', 'bibliotider' ), 
					'startdato' => '2012-09-01', 
					'sluttdato' => '2012-06-30' 
				) 
			);
			$wpdb->insert(
				$this->tabnavn . '_perioder', 
				array( 
					'navn' => __( 'Sommertid', 'bibliotider' ), 
					'startdato' => '2012-06-01', 
					'sluttdato' => '2012-08-31' 
				) 
			);
		}

		if ( ! get_option( 'bibliotider_filialer' ) ) {
			// Legger default liste over filialer i options
			update_option( 'bibliotider_filialer', array( __( 'Hovedbiblioteket', 'bibliotider' ) ) );
		}

		if ( ! get_option( 'bibliotider_betjent' ) ) {
			// Legger default liste over typer åpningstid i options
			update_option( 
				'bibliotider_betjent', 
				array(
					array(
						__('Selvbetjent', 'bibliotider'), 
						__('Biblioteket er åpent for alle, men skranken og telefonen er ikke betjent.', 'bibliotider')
					),
					array(
						__('Betjent', 'bibliotider'), 
						__('Biblioteket er åpent for alle!', 'bibliotider')
					),
					array(
						__('Meråpent', 'bibliotider'), 
						__('Biblioteket er stengt, men brukere som har inngått avtale om tilgang til Meråpent bibliotek kan låse seg inn med lånekortet og benytte biblioteket.', 'bibliotider')
					) 
				) 
			);
		}

	}

	// ----- Finn åpningstidene for en bestemt dag -----
	
	function dag( $dato, $filial = 1 ) {
		global $wpdb;

		// $dato må være i formatet '2018-02-28'
		$aar = substr( $dato, 0, 4 );

		// Finn unntak
		$query = 'SELECT betjent, TIME_FORMAT(u_starttid, \'%H:%i\') AS starttid, TIME_FORMAT(u_sluttid, \'%H:%i\') AS sluttid, u_navn AS navn FROM ' . $this->tabnavn . ' WHERE filial = ' . $filial . ' AND \'' . $dato . '\' BETWEEN u_startdato AND u_sluttdato ORDER BY betjent';

		$result = $wpdb->get_results( $query, OBJECT_K );

		if (0 == $result->num_rows) {
			$query = 'SELECT t.betjent, TIME_FORMAT(t.starttid, \'%H:%i\') AS starttid, TIME_FORMAT(t.sluttid, \'%H:%i\') AS sluttid, p.navn FROM ' . $this->tabnavn . ' AS t LEFT JOIN ' . $this->tabnavn . '_perioder AS p ON t.periode = p.id WHERE t.filial = ' . $filial . ' AND ((p.startdato <= p.sluttdato AND \'' . $dato . '\' BETWEEN DATE_FORMAT(p.startdato, \'' . $aar . '-%m-%d\') AND DATE_FORMAT(p.sluttdato, \'' . $aar . '-%m-%d\')) OR (p.startdato > p.sluttdato AND (\'' . $dato . '\' >= DATE_FORMAT(p.startdato, \'' . $aar . '-%m-%d\') OR \'' . $dato . '\' <= DATE_FORMAT(p.sluttdato, \'' . $aar . '-%m-%d\')))) AND t.ukedag = WEEKDAY(\'' . $dato . '\') + 1';

			$result = $wpdb->get_results( $query, OBJECT_K );
		}
		return $result;
	}

	// ----- Sjekk om oppgitt tekststreng er en gyldig dato i YYYY-MM-DD -----
	function er_gyldig_dato( $dato ) {
		$dt = DateTime::createFromFormat( "Y-m-d", $dato );
		return $dt !== false && !array_sum( $dt->getLastErrors() );
	}

	// ----- Skriv ut en tabell over åpningstidene for en bestemt uke -----

	function uke( $dato, $filial = 0 ) {
		$c = '';
		
		// Typer åpningstid
		$betjent_typer = get_option( 'bibliotider_betjent' );
		$antall_typer = count( $betjent_typer );

		// Konverterer gitt dato til timestamp

		$eksplodert_tid = explode( '-', $dato );
		$datotid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2], $eksplodert_tid[0] );

		// Hvilken ukedag har vi?
		$gitt_ukedag = date( 'N', $datotid );

		$c .= '<table>';

		// Headerrad
		$c .=  '<tr>';
		$c .=  '<th>' . __( 'Dag', 'bibliotider' ) . '</th>';
		for ( $i = 0; $i < $antall_typer; $i++ ) {
			$c .=  '<th>' . $betjent_typer[ $i ][0] . '</th>';
		}
		$c .=  '</tr>';

		for ( $d = 1; $d <= 7; $d++) {
			$dagtid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2] - $gitt_ukedag + $d, $eksplodert_tid[0] );
			$dag = date( 'Y-m-d', $dagtid );

			$c .=  '<tr>';
			$c .=  '<td>';
			$c .=  date_i18n( __( 'l d.m.', 'bibliotider' ), $dagtid );
			$c .=  '</td>';

			// Hent info om denne dagens åpningstider
			$dagtider = $this->dag( $dag, $filial );
				for ( $i = 0; $i < $antall_typer; $i++ ) {
					$c .=  '<td>';
					if ( isset( $dagtider[ $i + 1 ] ) ) {
						$c .=  substr( $dagtider[ $i + 1 ]->starttid, 0, 5 );
						$c .=  '&ndash;';
						$c .=  substr( $dagtider[ $i + 1 ]->sluttid, 0, 5 );
					}
					else {
						$c .=  '&ndash;';
					}
					$c .=  '</td>';
				}
			$c .=  '</tr>';


		}

		$c .=  '</table>';
		return $c;
	}

	function dagsvisning( $dato, $filial = 0 ) {
		$dagtider = $this->dag( $dato, $filial );
		$filialnavn = get_option( 'bibliotider_filialer' );
		$betjenttyper = get_option( 'bibliotider_betjent' );
		$tid_naa = '';
		$tidtabell = '';
		$klokka_er = current_time( 'H:i:s' );
		foreach( $dagtider as $bt => $dagobjekt ) {
			if ( !$tid_naa && $klokka_er >= $dagobjekt->starttid && $klokka_er < $dagobjekt->sluttid ) {
				$tid_naa = $betjenttyper[ $bt - 1 ][0];
			}
			$tidtabell .= '<tr><td class="betjenttype">' . $betjenttyper[ $bt - 1 ][0] . '</td><td>' . $dagobjekt->starttid . '&ndash;' . $dagobjekt->sluttid . '</td></tr>';

		}
		echo '<div class="vis_betjenttype';
		if ( ! $tid_naa ) {
			echo ' betjenttype_stengt';
			$tid_naa = 'Stengt';
		}
		echo '">';
		echo '<p class="vi_er_naa">' . sprintf( __( '%s er nå', 'bibliotider' ), $filialnavn[ $filial ] ) . '</p>';

		echo '<p class="betjenttype_naa">' . $tid_naa . '</p>' . "\n";
		echo '</div>';
		if ( $tidtabell ) {
			echo '<p><strong>' . __( 'Åpningstider i dag:', 'bibliotider' ) . '</strong></p>';
			echo '<table class="bibliotider_tabell">' . $tidtabell . '</table>';
		}

		// Neste dag
		$dagtider = $this->dag( date( 'Y-m-d', strtotime( $dato . ' + 1 day' ) ), $filial );
		$tidtabell = '';
		foreach( $dagtider as $bt => $dagobjekt ) {
			$tidtabell .= '<tr><td class="betjenttype">' . $betjenttyper[ $bt - 1 ][0] . '</td><td>' . $dagobjekt->starttid . '&ndash;' . $dagobjekt->sluttid . '</td></tr>';
		}
		if ( $tidtabell ) {
			echo '<p><strong>' . __( 'Åpningstider i morgen:', 'bibliotider' ) . '</strong></p>';
			echo '<table class="bibliotider_tabell">' . $tidtabell . '</table>';
		}

		$slug = get_option( 'bibliotider_side' );
		if ( $slug ) {
			echo '<p><a href="' . get_page_link( get_page_by_path( $slug ) ) . '">' . __( 'Alle åpningstider ...' , 'bibliotider' ) . '</a></p>';
		}

	}

	// ----- Legg administrasjonssiden for scriptet inn i menyen -----

	function meny() {
		$sidetittel = __( 'Åpningstider', 'bibliotider' );
		$menytittel = __( 'Åpningstider', 'bibliotider' );
		$tilganger = 'manage_options';
		$menyslug = 'bibliotider';
		$funksjon = array( $this, 'innstillinger' );

		add_options_page( $sidetittel, $menytittel, $tilganger, $menyslug, $funksjon );
	}

	// ----- Innstillinger -----

	function innstillinger() {
		global $wpdb;

		$verdier = array();

		// Henter informasjon om filialer
		$filialer = get_option( 'bibliotider_filialer' );
		$antall_filialer = count( $filialer );

		// Henter informasjon om typer åpningstid (betjent, meråpent ...)
		$betjenttyper = get_option( 'bibliotider_betjent' );
		$antall_betjenttyper = count( $betjenttyper );

		// Henter informasjon om perioder i året (sommertid, vintertid ...)
		$perioder = $wpdb->get_results( 'SELECT id, navn, startdato, sluttdato FROM ' . $this->tabnavn . '_perioder ORDER BY id', ARRAY_A );
		$antall_perioder = count( $perioder );

		// Henter tidligere verdier fra basen
		for ( $f = 0; $f < $antall_filialer; $f++ ) {
			// Sommertid/vintertid
			for ( $p = 0; $p < $antall_perioder; $p++ ) {
				$faktisk_p = $perioder[ $p ]['id'];
				// Ukedag
				for ( $d = 1; $d <= 7; $d++ ) {
					// Betjent/selvbetjent/meråpent
					for ( $b = 0; $b < $antall_betjenttyper; $b++ ) {
						$faktisk_b = $b + 1;
						$res = $wpdb->get_row( $wpdb->prepare( 'SELECT TIME_FORMAT(starttid, \'%H:%i\') AS starttid, TIME_FORMAT(sluttid, \'%H:%i\') AS sluttid FROM ' . $this->tabnavn . ' WHERE filial = %d AND periode = %d AND ukedag = %d AND betjent = %d', $f, $faktisk_p, $d, $faktisk_b ), ARRAY_A );
						$verdier[ $f ][ $p ][ $d ][ $b ] = $res;
					}
				}
			}
		}


		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'Du har ikke tilstrekkelig tilgang til å vise innholdet på denne siden.' ), 'bibliotider' );
		}

		// ----- Når brukeren har endret standardåpningstider -----

		if ( isset( $_POST['fane_sendt_inn'] ) && $_POST['fane_sendt_inn'] == 'standardtider' ) {
			// NB legg til masse validering

			// Filial
			for ( $f = 0; $f < $antall_filialer; $f++ ) {
				// Sommertid/vintertid
				for ( $p = 0; $p < $antall_perioder; $p++ ) {
					$faktisk_p = $perioder[ $p ]['id'];
					// Ukedag
					for ( $d = 1; $d <= 7; $d++ ) {
						// Betjent/selvbetjent/meråpent
						for ( $b = 0; $b < $antall_betjenttyper; $b++ ) {
							$faktisk_b = $b + 1;
							
							$eksisterer = false;
							$skal_eksistere = false;
							// Eksisterer verdien i basen fra før?
							if ( isset( $verdier[ $f ][ $p ][ $d ][ $b ]['starttid'] ) ) {
								$eksisterer = true;
							}

							// Er det fylt inn en ny verdi i skjemaet? (Både start og slutt?)
							if ( $_POST[ 'f-' . $f . '-p-' . $p . '-d-' . $d . '-b-' . $b . '-start' ] && $_POST[ 'f-' . $f . '-p-' . $p . '-d-' . $d . '-b-' . $b . '-slutt' ] ) {
								$skal_eksistere = true;
								$starttid = $_POST[ 'f-' . $f . '-p-' . $p . '-d-' . $d . '-b-' . $b . '-start' ];
								$sluttid = $_POST[ 'f-' . $f . '-p-' . $p . '-d-' . $d . '-b-' . $b . '-slutt' ];
							}

							// Dersom verdien ligger i basen, men ikke står i skjemaet, skal den slettes.
							if ( $eksisterer && !$skal_eksistere ) {
								$wpdb->delete( $this->tabnavn, array( 'filial' => $f, 'periode' => $faktisk_p, 'ukedag' => $d, 'betjent' => $faktisk_b ), array( '%d', '%d', '%d', '%d' ) );
								$verdier[ $f ][ $p ][ $d ][ $b ]['starttid'] = '';
								$verdier[ $f ][ $p ][ $d ][ $b ]['sluttid'] = '';
							}

							// Dersom verdien ligger i basen og skjemaet, men ikke er identisk, skal den oppdateres.
							if ( $eksisterer && $skal_eksistere && substr( $starttid, 0, 5 ) != substr( $verdier[ $f ][ $p ][ $d ][ $b ]['starttid'], 0, 5 ) ) {
								$wpdb->update( $this->tabnavn, array( 'starttid' => $starttid, 'sluttid' => $sluttid ), array( 'filial' => $f, 'periode' => $faktisk_p, 'ukedag' => $d, 'betjent' => $faktisk_b ), array( '%s', '%s' ), array( '%d', '%d', '%d', '%d' ) );
								$verdier[ $f ][ $p ][ $d ][ $b ]['starttid'] = $starttid;
								$verdier[ $f ][ $p ][ $d ][ $b ]['sluttid'] = $sluttid;
							}

							// Dersom verdien ikke ligger i basen, men står i skjemaet, skal den legges inn.
							if ( !$eksisterer && $skal_eksistere ) {
								$wpdb->insert( $this->tabnavn, array( 'filial' => $f, 'periode' => $faktisk_p, 'ukedag' => $d, 'betjent' => $faktisk_b, 'starttid' => $starttid, 'sluttid' => $sluttid ), array( '%d', '%d', '%d', '%d', '%s', '%s' ) );
								$verdier[ $f ][ $p ][ $d ][ $b ]['starttid'] = $starttid;
								$verdier[ $f ][ $p ][ $d ][ $b ]['sluttid'] = $sluttid;
							}
						}
					}
				}
			}
		}

		// ----- Når brukeren har endret lista over filialer -----

		elseif ( isset($_POST['fane_sendt_inn']) && $_POST['fane_sendt_inn'] == 'filialer' ) {
			
			$filialliste = explode( "\n", $_POST['filialliste'] );
			$antall = count( $filialliste );
			$filialer_ny = array();
			$antall_filialer_ny = 0;
			for ( $i = 0; $i < $antall; $i++ ) {
				$nf = trim( $filialliste[ $i ] );
				if ( $nf ) {
					$filialer_ny[] = $nf;
					$antall_filialer_ny++;
				}
			}
			if ( $antall_filialer_ny ) {
				update_option( 'bibliotider_filialer', $filialer_ny );
				$filialer = $filialer_ny;
				$antall_filialer = $antall_filialer_ny;
			}
		}

		// ----- Når brukeren har endret lista over betjenttyper -----

		elseif ( isset($_POST['fane_sendt_inn']) && $_POST['fane_sendt_inn'] == 'betjenttyper' ) {
			
			$betjenttypeliste = explode( "\n", $_POST['betjenttypeliste'] );
			$antall = count( $betjenttypeliste );
			$betjenttyper_ny = array();
			$antall_betjenttyper_ny = 0;
			for ( $i = 0; $i < $antall; $i++ ) {
				$nt = trim( $betjenttypeliste[ $i ] );
				if ( $nt ) {
					$nta = explode( ':', $nt, 2 );
					$nta[0] = trim( $nta[0] );
					if ( isset( $nta[1] ) ) {
						$nta[1] = trim( $nta[1] );
					}
					else {
						$nta[1] = '';
					}
					$betjenttyper_ny[] = $nta;
					$antall_betjenttyper_ny++;
				}
			}
			if ( $antall_betjenttyper_ny ) {
				update_option( 'bibliotider_betjent', $betjenttyper_ny );
				$betjenttyper = $betjenttyper_ny;
				$antall_betjenttyper = $antall_betjenttyper_ny;
			}
		}
		elseif ( isset( $_POST['fane_sendt_inn'] ) && $_POST['fane_sendt_inn'] == 'innstillinger' ) {
			update_option( 'bibliotider_side', $_POST['slug'] );
		}

		// ----- INKLUDER SKJEMAET -----

		echo '<div class="wrap">';
		echo '<h1>' . __( 'Endre åpningstider', 'bibliotider' ) . '</h1>';

		include( 'skjema.php' );

		echo '</div>';
	}

	function vis_tider( $innhold ) {
		if ( get_option( 'bibliotider_side' ) && is_page( get_option( 'bibliotider_side' ) ) && !$this->vis_tider_kjort ) {
			$this->vis_tider_kjort = true;
			$c = '';
			$c .=  '<h2>' . __( 'Åpningstider denne uka:', 'bibliotider' ) . '</h2>';
			$c .= $this->uke( date( 'Y-m-d' ) );
			$c .=  '<h2>' . __( 'Avvik den nærmeste måneden:', 'bibliotider' ) . '</h2>';
			$c .=  '<p>Ingen avvik registrert</p>';
			return $c;
		}
		else {
			return $innhold;
		}
	}

}

// ----- Åpningstider-widgeten -----

class Bibliotider_Widget extends WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array( 
			'classname' => 'bibliotider_widget',
			'description' => __( 'Viser åpningstider for denne uka for en valgt filial.', 'bibliotider' ),
		);
		parent::__construct( 'bibliotider_widget', __( 'Bibliotekets åpningstider', 'bibliotider' ), $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		// outputs the content of the widget
		global $bibliotider;
		echo '<section class="widget widget-bibliotider">';
		echo '<h4 class="widgettitle">' . __( 'Åpningstider', 'bibliotider' ) . '</h4>';
		$bibliotider->dagsvisning( date( 'Y-m-d' ) );
		echo '</section>';
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
	}

	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
	}
}

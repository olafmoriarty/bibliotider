<?php

/*
Plugin Name:  Bibliotekets åpningstider
Plugin URI:   https://github.com/skibibliotek/bibliotider/
Description:  Registrering og visning av åpningstider, tilpasset bibliotek. Skiller mellom betjent, selvbetjent og meråpent.
Version:      0.0.2
Author:       Olaf Moriarty Solstrand
Author URI:   http://skibibliotek.no
License:      GNU General Public License v2.0
License URI:  https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Text Domain:  bibliotider
Domain Path:  /sprak
*/


// Oppretter MySQL-tabeller
$bibliotider = new Bibliotider;
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

// Legger til Ajax
add_action('wp_ajax_bibliotider_refresh_widget', array($bibliotider, 'refresh_widget'));
add_action('wp_ajax_nopriv_bibliotider_refresh_widget', array($bibliotider, 'refresh_widget'));
add_action('wp_print_footer_scripts', array($bibliotider, 'ajax_init'));

// Oppretter side for åpningstider
add_filter( 'the_content', array( $bibliotider, 'vis_tider' ) );






/**
 * Håndterer bibliotekets åpningstider.
 *
 * Klassen inneholder alle metodene i pluginen. NB: Ikke kall metodene i klassen
 * direkte. Vennligst bruk den globale variabelen $bibliotider i stedet for.
 *
 * @since 0.0.1
*/
class Bibliotider {

	/**
	 * Navn på hovedtabellen i MySQL.
	 *
	 * @since 0.0.1
	 * @var string $tabnavn
	 */
	var $tabnavn;



	/**
	 * Hvorvidt metoden vis_tider() allerede har kjørt.
	 *
	 * @since 0.0.1
	 * @var boolean $vis_tider_kjort
	 */
	var $vis_tider_kjort;


	/**
	 * Klassekonstruktor.
	 *
	 * Henter databasetabellprefiks fra $wpdb and og bruker det til å sette 
	 * $vis_tider_kjort.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		global $wpdb;
		$this->tabnavn = $wpdb->prefix . 'bibliotider';
	}

// A
	function ajax_init() {
		 ?>
		<script type="text/javascript" >
		jQuery(document).ready(function($) {
			
			jQuery(".bibliotider_widget_content").each(function( index ) {
				var current_thing = jQuery(this);

				var data = {
					'action': 'bibliotider_refresh_widget',
					'tid': '<?php echo date('Y-m-d H:i'); ?>',
					'filial': current_thing.data('filial')
				};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post('<?php echo admin_url( 'admin-ajax.php' ) ?>', data, function(response) { current_thing.html(response) });
			});
			jQuery(".bibliotider_vistider_filial .bibliotider_vistider_perioder").hide();

			jQuery(".bibliotider_vistider_filial .vis_detaljer a").click(function(){
				jQuery(this).parent().hide();
				jQuery("#bt-filial-" + jQuery(this).data("filial") + " .bibliotider_vistider_perioder").show();
			});

		});
		</script> <?php
	}
		

// B
// C

	/**
	 * Legger til CSS på innstillingssidene.
	 *
	 * @since 0.0.1
	 */
	function css_admin() {
		wp_register_style( 'bibliotider-admin-css', plugins_url( 'admin.css', __FILE__ ) );
		wp_enqueue_style( 'bibliotider-admin-css' );
	}

	/**
	 * Legger til CSS på publikumssidene.
	 *
	 * @since 0.0.1
	 */
	function css_hoved() {
		wp_register_style( 'bibliotider-css', plugins_url( 'bt.css', __FILE__ ), array(), '2019.10.06' );
		wp_enqueue_style( 'bibliotider-css' );
	}

// D

	/**
	 * Henter ut åpningstidene for en oppgitt dag.
	 *
	 * @param  string $dato   Dato i YYYY-MM-DD-format.
	 * @param  int    $filial Valgfri. Filial-ID-en det skal hentes ut 
	 *                        åpningstider for. Standardverdi er hovedfilial.
	 * @return array  Matrise av objekter der hvert objekt inneholder starttid
	 *                og sluttid for en type åpningstid. Matrisenøkkelen
	 *                tilsvarer betjenttype.
	 * @since  0.0.1
	 */
	function dag( $dato, $filial = 0 ) {
		global $wpdb;

		// $dato må være i formatet '2018-02-28'
		$aar = substr( $dato, 0, 4 );

		// Finn unntak
		$query = 'SELECT betjent, TIME_FORMAT(starttid, \'%H:%i\') AS starttid, TIME_FORMAT(sluttid, \'%H:%i\') AS sluttid, u_navn AS navn FROM ' . $this->tabnavn . ' WHERE filial = ' . $filial . ' AND \'' . $dato . '\' BETWEEN u_startdato AND u_sluttdato ORDER BY betjent';

		$result = $wpdb->get_results( $query, OBJECT_K );
		
		if (0 == $wpdb->num_rows) {
			$query = 'SELECT t.betjent, TIME_FORMAT(t.starttid, \'%H:%i\') AS starttid, TIME_FORMAT(t.sluttid, \'%H:%i\') AS sluttid, p.navn FROM ' . $this->tabnavn . ' AS t LEFT JOIN ' . $this->tabnavn . '_perioder AS p ON t.periode = p.id WHERE t.filial = ' . $filial . ' AND p.filial = t.filial AND ((p.startdato <= p.sluttdato AND \'' . $dato . '\' BETWEEN DATE_FORMAT(p.startdato, \'' . $aar . '-%m-%d\') AND DATE_FORMAT(p.sluttdato, \'' . $aar . '-%m-%d\')) OR (p.startdato > p.sluttdato AND (\'' . $dato . '\' >= DATE_FORMAT(p.startdato, \'' . $aar . '-%m-%d\') OR \'' . $dato . '\' <= DATE_FORMAT(p.sluttdato, \'' . $aar . '-%m-%d\')))) AND t.ukedag = WEEKDAY(\'' . $dato . '\') + 1';
			$result = $wpdb->get_results( $query, OBJECT_K );
		}
		return $result;
	}

	/**
	 * Viser dagens åpningstider.
	 *
	 * Returnerer, i HTML-format og klart for å vises i en widget eller
	 * shortcode, følgende informasjon fra databasen:
	 *
	 * - Er biblioteket åpent nå? (Dersom vi viser dagens dato.)
	 * - Dagens åpningstider
	 * - Morgendagens åpningstider (Dersom parameteret $morgen ikke er satt til
	 *   FALSE)
	 *
	 * @param  string  $dato   Valgfri. Dato i YYYY-MM-DD-format. Standardverdi 
	 *                         er dagens dato.
	 * @param  int     $filial Valgfri. Filial-ID-en det skal vises åpningstider
	 *                         for. Standardverdi er hovedfilial.
	 * @param  boolean $morgen Valgfri. TRUE dersom morgendagens åpningstider
	 *                         skal vises i tillegg til dagens, FALSE dersom de
	 *                         ikke skal det. Standardverdi er TRUE.
	 * @return string  HTML-kode som kan printes til skjerm.
	 * @since  0.0.1
	 */
	function dagsvisning( $dato = '', $filial = 0, $morgen = true ) {
		$c = '';
		if ( ! $dato ) {
			$dato = current_time( 'Y-m-d' );
		}
		$filialnavn = get_option( 'bibliotider_filialer' );
		$antall_filialer = count($filialnavn);
		$betjenttyper = get_option( 'bibliotider_betjent' );
		$tid_naa = '';
		$tidtabell = '';
		$tidtabell2 = '';
		$klokka_er = current_time( 'H:i:s' );

		// En filial, eller alle?
		if ($filial == -1) {
			$filial_min = 0;
			$filial_max = $antall_filialer - 1;
		}
		else {
			$filial_min = $filial;
			$filial_max = $filial;
		}

		for ($i = $filial_min; $i <= $filial_max; $i++) {

			if ( ! is_array($filialnavn[ $i ] )) {
				break;
			}
			$filial_betjenttider = 0;
			
			$dagtider = $this->dag( $dato, $i );
			/*
			if ( $filial == -1 ) {
				$tidtabell .= '<tr><th colspan="2"><a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a></th></tr>';
			}*/
			foreach( $dagtider as $bt => $dagobjekt ) {
				if ( $bt > 0 ) {
					$aktiv_tid = 0;
					if ( $klokka_er >= $dagobjekt->starttid && $klokka_er < $dagobjekt->sluttid ) {
						$aktiv_tid = 1;
						if ($filial != -1 && !$tid_naa) {
							$tid_naa = $betjenttyper[ $bt - 1 ][0];
						}
					}
					if (!$filial_betjenttider && $bt > 1 && $filial == -1) {
						$tidtabell .= '<tr><td><a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a></td><td></td></tr>';
					}
					$tidtabell .= '<tr class="bibliotider_bt_'.$bt;
					if ($aktiv_tid) {
						$tidtabell .= ' bibliotider_aktiv_tid';
					}
					$tidtabell .= '"><td class="betjenttype">';
					if (1 == $bt) {
						$tidtabell .= '<a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a>';
					}
					else {
						$tidtabell .= $betjenttyper[ $bt - 1 ][0];
					}
					$tidtabell .= '</td><td class="tid">' . $dagobjekt->starttid . '&ndash;' . $dagobjekt->sluttid . '</td></tr>';
					$tidtabell2 .= '<tr';
					if ($aktiv_tid) {
						$tidtabell2 .= ' class="bibliotider_aktiv_tid"';
					}
					$tidtabell2 .= '><td class="betjenttype"><a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a></td><td class="tid">' . $dagobjekt->starttid . '&ndash;' . $dagobjekt->sluttid . '</td></tr>';
					$filial_betjenttider++;
				}
			}
			if ( ! $filial_betjenttider ) {
				// $tidtabell .= '<tr><td class="betjenttype">' . __( 'Stengt', 'bibliotider' ) . '</td><td class="tid">' . __( 'Hele dagen', 'bibliotider' ) . '</td></tr>';
				$tidtabell .= '<tr class="bibliotider_bt_stengt"><td class="betjenttype"><a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a></td><td class="tid">' . __( 'Stengt', 'bibliotider' ) . '</td></tr>';
				$tidtabell2 .= '<tr class="bibliotider_bt_stengt"><td class="betjenttype"><a href="' . $filialnavn[ $i ][ 1 ] . '">' . $filialnavn[ $i ][ 0 ] . '</a></td><td class="tid">' . __( 'Stengt', 'bibliotider' ) . '</td></tr>';
			}
		}

		if ( $filial != -1 && $dato == current_time( 'Y-m-d' ) && is_array($filialnavn[$filial]) ) {
			$c .= '<div class="vis_betjenttype';
			if ( ! $tid_naa ) {
				$c .= ' betjenttype_stengt';
				$tid_naa = __( 'Stengt', 'bibliotider' );
			}
			$c .= '">';
			$c .= '<p class="vi_er_naa">' . sprintf( __( '%s er nå', 'bibliotider' ), $filialnavn[ $filial ][ 0 ] ) . '</p>';

			$c .= '<p class="betjenttype_naa">' . $tid_naa . '</p>' . "\n";
			$c .= '</div>';
		}

		$c .= '<h2>' . __( 'Åpningstider i dag:', 'bibliotider' ) . '</h2>';
		$antall_betjenttyper = count( $betjenttyper );
		if ($antall_betjenttyper < 2 && $filial == -1) {
			$c .= '<table class="bibliotider_tabell">' . $tidtabell2 . '</table>';
		}
		else {
			$c .= '<table class="bibliotider_tabell">' . $tidtabell . '</table>';
		}

		if ($morgen && $filial != -1) {
			// Neste dag
			$dagtider = $this->dag( date( 'Y-m-d', strtotime( $dato . ' + 1 day' ) ), $filial );
			$tidtabell = '';
			foreach( $dagtider as $bt => $dagobjekt ) {
				$tidtabell .= '<tr><td class="betjenttype">' . $betjenttyper[ $bt - 1 ][0] . '</td><td>' . $dagobjekt->starttid . '&ndash;' . $dagobjekt->sluttid . '</td></tr>';
			}

			if ( ! $tidtabell ) {
				$tidtabell = '<tr><td class="betjenttype">' . __( 'Stengt', 'bibliotider' ) . '</td><td>' . __( 'Hele dagen', 'bibliotider' ) . '</td></tr>';
			}

			$c .= '<p><strong>' . __( 'Åpningstider i morgen:', 'bibliotider' ) . '</strong></p>';
			$c .= '<table class="bibliotider_tabell">' . $tidtabell . '</table>';
		}
		$slug = get_option( 'bibliotider_side' );
		if ( $slug ) {
			$c .= '<p><a href="' . get_page_link( get_page_by_path( $slug ) ) . '">' . __( 'Alle åpningstider ...' , 'bibliotider' ) . '</a></p>';
		}
		return $c;
	}

// E

	/**
	 * Sjekker om $dato er en gyldig dato.
	 *
	 * @param  string  $dato Datoen som skal sjekkes.
	 * @return boolean TRUE dersom $dato er YYYY-MM-DD, FALSE om den ikke er det
	 * @since  0.0.1
	 */
	function er_gyldig_dato( $dato ) {
		$dt = DateTime::createFromFormat( "Y-m-d", $dato );
		return $dt !== false && !array_sum( $dt->getLastErrors() );
	}

// F
// G
// H
// I

	/**
	 * Viser Innstillinger-skjemaet som lar brukeren endre åpningstider m.m., og
	 * behandler data fra det samme skjemaet.
	 *
	 * @since 0.0.1
	 */
	function innstillinger() {
		global $wpdb;

		$verdier = array();

		$perioder = array();
		$antall_perioder = array();

		// Henter informasjon om filialer
		$filialer = get_option( 'bibliotider_filialer' );
		$antall_filialer = count( $filialer );

		// Henter informasjon om typer åpningstid (betjent, meråpent ...)
		$betjenttyper = get_option( 'bibliotider_betjent' );
		$antall_betjenttyper = count( $betjenttyper );

		// Henter tidligere verdier fra basen

		for ( $f = 0; $f < $antall_filialer; $f++ ) {

			// Henter informasjon om perioder i året (sommertid, vintertid ...)
			$perioder[$f] = $wpdb->get_results( 'SELECT id, navn, startdato, sluttdato FROM ' . $this->tabnavn . '_perioder WHERE filial = ' . $f . ' ORDER BY id', ARRAY_A );
			$antall_perioder[$f] = count( $perioder[$f] );

			// Sommertid/vintertid
			for ( $p = 0; $p < $antall_perioder[$f]; $p++ ) {
				$faktisk_p = $perioder[$f][ $p ]['id'];
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
				for ( $p = 0; $p < $antall_perioder[ $f ]; $p++ ) {
					$faktisk_p = $perioder[ $f ][ $p ]['id'];
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

		// ----- Når brukeren har lagt til et unntak -----

		elseif ( isset( $_POST['fane_sendt_inn'] ) && $_POST['fane_sendt_inn'] == 'unntak' ) {
			if ($_POST['startdato']) {

				if ( $_POST['filial'] ) {
					$filial = $_POST['filial'];
				}
				else {
					$filial = 0;
				}

				$startdato = $_POST['startdato'];
				if ($_POST['sluttdato']) {
					$sluttdato = $_POST['sluttdato'];
				}
				else {
					$sluttdato = $startdato;
				}
				
				// Er noen av feltene fylt ut?
				$felt_fylt_ut = false;
				for ( $b = 0; $b < $antall_betjenttyper; $b++ ) {
					$faktisk_b = $b + 1;
					if ( $_POST[ $b . '-start' ] && $_POST[ $b . '-slutt' ] ) {
						$felt_fylt_ut = true;
						$starttid = $_POST[ $b . '-start' ];
						$sluttid  = $_POST[ $b . '-slutt' ];

						$wpdb->insert( $this->tabnavn, array( 'filial' => $filial, 'betjent' => $faktisk_b, 'starttid' => $starttid, 'sluttid' => $sluttid, 'u_startdato' =>  $startdato, 'u_sluttdato' => $sluttdato ), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
					}
				}

				if ( ! $felt_fylt_ut ) {
					$wpdb->insert( $this->tabnavn, array( 'filial' => $filial, 'betjent' => 0, 'u_startdato' =>  $startdato, 'u_sluttdato' => $sluttdato ), array( '%d', '%d', '%s', '%s' ) );
					
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
					$filialer_ny[] = explode( '|', $nf );
					$antall_filialer_ny++;
				}
			}
			if ( $antall_filialer_ny ) {
				update_option( 'bibliotider_filialer', $filialer_ny );
				$filialer = $filialer_ny;
				$antall_filialer = $antall_filialer_ny;
			}
		}

		// ----- Når brukeren har endret lista over perioder -----

		elseif ( isset($_POST['fane_sendt_inn']) && $_POST['fane_sendt_inn'] == 'perioder' ) {


			// Oppdater perioder
			
			// Filial
			for ( $f = 0; $f < $antall_filialer; $f++ ) {
				// Sommertid/vintertid
				for ( $p = 0; $p < $antall_perioder[ $f ]; $p++ ) {
					$faktisk_p = $perioder[ $f ][ $p ]['id'];

					$periodenavn = $_POST[ 'periodenavn-' . $faktisk_p ];
					$periodestart = $_POST[ 'periodestart-' . $faktisk_p ];
					$periodeslutt = $_POST[ 'periodeslutt-' . $faktisk_p ];

					$wpdb->update( $this->tabnavn . '_perioder', array( 'navn' => $periodenavn, 'startdato' => date('2012-m-d', strtotime($periodestart . '2012')), 'sluttdato' => date('2012-m-d', strtotime($periodeslutt . '2012')) ), array( 'filial' => $f, 'id' => $faktisk_p ), array( '%s', '%s', '%s' ), array( '%d', '%d' ) );
				}
			}

			// Sett inn nye perioder

			for ($i = 0; $i < $antall_filialer; $i++) {
				if (is_numeric($_POST['ny-periode-'.$i]) && $_POST['ny-periode-'.$i] > 0) {
					for ($j = 0; $j < $_POST['ny-periode-'.$i]; $j++) {
						$wpdb->insert(
							$this->tabnavn . '_perioder', 
							array( 
								'filial' => $i
							) 
						);
					}
					$antall_perioder[ $i ] += $_POST['ny-periode-' . $i];
				}
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

// J
// K
// L
// M

	/**
	 * Legger til en side med innstillinger i administrasjonsmenyen.
	 *
	 * Metoden kalles av add_action( 'admin_menu' ).
	 *
	 * @since  0.0.1
	 */
	function meny() {
		$sidetittel = __( 'Åpningstider', 'bibliotider' );
		$menytittel = __( 'Åpningstider', 'bibliotider' );
		$tilganger = 'manage_options';
		$menyslug = 'bibliotider';
		$funksjon = array( $this, 'innstillinger' );

		add_options_page( $sidetittel, $menytittel, $tilganger, $menyslug, $funksjon );
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
		  filial int(3) DEFAULT 0 NOT NULL,
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
					'sluttdato' => '2012-05-31',
					'filial'    => 0
				) 
			);
			$wpdb->insert(
				$this->tabnavn . '_perioder', 
				array( 
					'navn' => __( 'Sommertid', 'bibliotider' ), 
					'startdato' => '2012-06-01', 
					'sluttdato' => '2012-08-31',
					'filial'    => 0 
				) 
			);
		}

		if ( ! get_option( 'bibliotider_filialer' ) ) {
			// Legger default liste over filialer i options
			update_option( 'bibliotider_filialer', array( array(__( 'Hovedbiblioteket', 'bibliotider' )) ) );
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


// N
// O
// P
// Q
// R

	function refresh_widget() {
		if (isset($_POST['filial']) && $_POST['filial']) {
			$filial = $_POST['filial'];
		}
		else {
			$filial = 0;
		}
		echo $this->dagsvisning( current_time( 'Y-m-d' ), $filial );
		wp_die();
	}
// S
// T
// U

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

// V

	function vis_tider( $innhold ) {
		global $wpdb;
		
		$verdier = array();

		

		// Henter informasjon om filialer
		$filialer = get_option( 'bibliotider_filialer' );
		$antall_filialer = count( $filialer );

		// Henter informasjon om typer åpningstid (betjent, meråpent ...)
		$betjenttyper = get_option( 'bibliotider_betjent' );
		$antall_betjenttyper = count( $betjenttyper );

		// Henter tidligere verdier fra basen
		$perioder = array();
		$antall_perioder = array();
		for ( $f = 0; $f < $antall_filialer; $f++ ) {

			// Henter informasjon om perioder i året (sommertid, vintertid ...)
			$perioder[ $f ] = $wpdb->get_results( 'SELECT id, navn, startdato, sluttdato FROM ' . $this->tabnavn . '_perioder  WHERE filial = ' . $f . ' ORDER BY id', ARRAY_A );
			$antall_perioder[ $f ] = count( $perioder[$f] );


			// Sommertid/vintertid
			for ( $p = 0; $p < $antall_perioder[$f]; $p++ ) {
				$faktisk_p = $perioder[ $f ][ $p ]['id'];
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
		
		if ( get_option( 'bibliotider_side' ) && is_page( get_option( 'bibliotider_side' ) ) && !$this->vis_tider_kjort ) {
			$this->vis_tider_kjort = true;
			$c = '';

			for ($f = 0; $f < $antall_filialer; $f++ ) {
				$c .= '<div class="bibliotider_vistider_filial" id="bt-filial-'.$f.'">';
				if (isset($filialer[$f][1]) && $filialer[$f][1]) {
					$c .= '<h2><a href="' . $filialer[$f][1] . '">'.$filialer[$f][0].'</a></h2>';
				}
				else {
					$c .= '<h2>'.$filialer[$f][0].'</h2>';
				}
				$c .=  '<h3>' . __( 'Åpningstider denne uka:', 'bibliotider' ) . '</h3>';
				$c .= $this->uke( date( 'Y-m-d' ), $f );
				$c .=  '<h3>' . __( 'Avvik den nærmeste måneden:', 'bibliotider' ) . '</h3>';
				$query = 'SELECT u_startdato, u_sluttdato FROM ' . $this->tabnavn . ' WHERE u_startdato BETWEEN CAST(\'' . date( 'Y-m-d' ) . '\' AS DATE) AND CAST(\'' . date( 'Y-m-d', strtotime( '+1 month' ) ) . '\' AS DATE) AND filial = '.$f;
				$result = $wpdb->get_results( $query, OBJECT_K );
				$num = $wpdb->num_rows;
				if (0 == $num) {
					$c .=  '<p>Ingen avvik registrert</p>';
				}
				else {
					ksort( $result );
					$c .= '<table>';

					// Headerrad
					$c .=  '<tr>';
					$c .=  '<th>' . __( 'Dag', 'bibliotider' ) . '</th>';
					for ( $i = 0; $i < $antall_betjenttyper; $i++ ) {
						$c .=  '<th>' . $betjenttyper[ $i ][0] . '</th>';
					}
					$c .=  '</tr>';

					foreach ($result AS $dato => $obj) {
								$eksplodert_tid = explode( '-', $dato );
								//	$datotid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2], $eksplodert_tid[0] );

						$dagtid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2], $eksplodert_tid[0] );

						$c .=  '<tr>';
						$c .=  '<td>';
						$c .=  date_i18n( __( 'l d.m.', 'bibliotider' ), $dagtid );
						$c .=  '</td>';

						// Hent info om denne dagens åpningstider
						$dagtider = $this->dag( $obj->u_startdato, $f );
							for ( $i = 0; $i < $antall_betjenttyper; $i++ ) {
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
				}

				$eksplodert_tid = explode('-', date('Y-m-d'));
				$gitt_ukedag = date('N');

				$i = 0;
				
				$c .= '<p class="vis_detaljer"><a href="#0" data-filial="'.$f.'">'.__('Vis detaljer ...').'</a></p>';

				$c .= '<div class="bibliotider_vistider_perioder">';

				for ($j = 0; $j < $antall_perioder[$f]; $j++) {
						$faktisk_p = $perioder[$f][$j]['id'];
				
					$c .= '<h3>'.sprintf(__('%1$s (fra %2$s til %3$s):', 'bibliotider'), $perioder[$f][$j]['navn'], date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$f][$j]['startdato'])), date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$f][$j]['sluttdato']))).'</h3>';

					$c .= '<table class="apningstider">';

					// Headerrad
					$c .= '<tr>';
					$c .= '<th>'.__('Dag', 'bibliotider').'</th>';
					for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
						$c .= '<th>'.$betjenttyper[$h][0].'</th>';
					}
					$c .= '</tr>';

					for ( $d = 1; $d <= 7; $d++) {
						$dagtid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2] - $gitt_ukedag + $d, $eksplodert_tid[0] );
						$dag = date( 'Y-m-d', $dagtid );

						$c .= '<tr>';
						$c .= '<td>';
						$c .= date_i18n( __('l', 'bibliotider'), $dagtid );
						$c .= '</td>';

						// Hent info om denne dagens åpningstider
						for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
							$c .= '<td>';
							$c .= $verdier[$f][$j][$d][$h]['starttid'];
							$c .= '&ndash;';
							$c .= $verdier[$f][$j][$d][$h]['sluttid'];
							$c .= '</td>';
						}
						$c .= '</tr>';
					}
					$c .= '</table>';
				} 
				$c .= '</div>';
				$c .= '</div>';
			}
			return $c;
		}
		else {
			return $innhold;
		}
	}

// W
// X
// Y
// Z
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
		
		if ( isset($instance['filial']) && $instance['filial'] ) {
			$filial = $instance['filial'];
		}
		else {
			$filial = 0;
		}
		echo '<section class="widget widget-bibliotider">';
		if ( isset($instance['tittel']) && $instance['tittel'] ) {
			echo '<h4 class="widgettitle">' . $instance['tittel'] . '</h4>';
		}
		if ( $instance['visning'] == 'uke' ) {
			echo $bibliotider->uke( current_time( 'Y-m-d' ), $filial );
		}
		else {
//			echo $bibliotider->dagsvisning( current_time( 'Y-m-d' ), $filial );
			echo '<div class="bibliotider_widget_content" data-filial="'.$filial.'">';
			echo '<p><a href="/apningstider/">'.__('Åpningstider', 'bibliotider').'</a></p>';
			echo '</div>';
		}
		echo '</section>';
	}

	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance The widget options
	 */
	public function form( $instance ) {
		// outputs the options form on admin
		
		// Hvilke visninger er tilgjengelige?
		$visninger = array( 'dag', 'uke' );
		// Hvilken visning er valgt?
		if ( isset( $instance['visning'] ) && in_array( $instance['visning'], $visninger ) ) {
			$visning = $instance['visning'];
		}
		else {
			// Standardvisning: Dagsvisning
			$visning = 'dag';
		}
		// Velg visning
		echo '<p><input type="radio" name="'.$this->get_field_name( 'visning' ).'" value="dag"';
		if ('dag' == $visning) {
			echo ' checked="checked"';
		}
		echo ' /> '.__( 'Dagsvisning', 'bibliotider' ).'<br />';
		echo '<input type="radio" name="'.$this->get_field_name( 'visning' ).'" value="uke"';
		if ('uke' == $visning) {
			echo ' checked="checked"';
		}
		echo ' /> '.__( 'Ukesvisning', 'bibliotider' ).'</p>';
		
		// Hvilken filial er valgt?
		if ( isset( $instance['filial'] ) && (int)$instance['filial'] >= -1 && (int)$instance['filial'] < $antall_filialer ) {
			$valgt_filial = $instance['filial'];
		}
		else {
			// Standardfilial: Hovedbiblioteket
			$valgt_filial = 0;
		}
		
		
		// Hent filialoversikten
		$filialer = get_option( 'bibliotider_filialer' );
		$filialtall = count( $filialer );
		
		// Velg filial
		echo '<p>' . __( 'Filial:', 'bibliotider' ) . '<br />';
		echo '<input type="radio" name="'.$this->get_field_name( 'filial' ).'" value="-1"';
		if ( $valgt_filial == -1 ) {
			echo ' checked="checked"';
		}
		echo ' /> ' . __('Vis alle', 'bibliotider');
		for ( $i = 0; $i < $filialtall; $i++ ) {
			echo '<br /><input type="radio" name="'.$this->get_field_name( 'filial' ).'"  value="'.$i.'"';
			if ( $valgt_filial == $i ) {
				echo ' checked="checked"';
			}
			echo ' /> ' . $filialer[ $i ][ 0 ];
		}
		echo '</p>';	
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
		
		// ** LEGG TIL VALIDERING HER **
		
		return $new_instance;
	}
}

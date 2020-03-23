<div class="katalogkort">
<input type="radio" name="katalogkort" id="tab1" checked>
<label for="tab1"><?php _e('Åpningstider', 'bibliotider'); ?></label>
<input type="radio" name="katalogkort" id="tab2">
<label for="tab2"><?php _e('Unntak', 'bibliotider'); ?></label>
<input type="radio" name="katalogkort" id="tab3">
<label for="tab3"><?php _e('Filialer', 'bibliotider'); ?></label>
<input type="radio" name="katalogkort" id="tab4">
<label for="tab4"><?php _e('Perioder', 'bibliotider'); ?></label>
<input type="radio" name="katalogkort" id="tab5">
<label for="tab5"><?php _e('Typer åpningstid', 'bibliotider'); ?></label>
<input type="radio" name="katalogkort" id="tab6">
<label for="tab6"><?php _e('Innstillinger', 'bibliotider'); ?></label>

  <div class="tab-panels">
    <section class="tab-panel">
		<?php
			// ENDRE STANDARD ÅPNINGSTIDER

			echo '<form method="post" action="">';

			$eksplodert_tid = explode('-', date('Y-m-d'));
			$gitt_ukedag = date('N');

			for ($i = 0; $i < $antall_filialer; $i++) {
				echo '<h2>'.$filialer[$i][ 0 ].'</h2>';
				for ($j = 0; $j < $antall_perioder[$i]; $j++) {
					$faktisk_p = $perioder[$j]['id'];

					echo '<h3>'.sprintf(__('%1$s (fra %2$s til %3$s)', 'bibliotider'), $perioder[$i][$j]['navn'], date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$i][$j]['startdato'])), date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$i][$j]['sluttdato']))).'</h3>';

					echo '<table class="apningstider">';

					// Headerrad
					echo '<tr>';
					echo '<th>'.__('Dag', 'bibliotider').'</th>';
					for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
						echo '<th>'.$betjenttyper[$h][0].'</th>';
					}
					echo '</tr>';

					for ( $d = 1; $d <= 7; $d++) {
						$dagtid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2] - $gitt_ukedag + $d, $eksplodert_tid[0] );
						$dag = date( 'Y-m-d', $dagtid );

						echo '<tr>';
						echo '<td>';
						echo date_i18n( __('l', 'bibliotider'), $dagtid );
						echo '</td>';

						// Hent info om denne dagens åpningstider
						for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
							echo '<td>';
							echo '<input type="time" name="f-'.$i.'-p-'.$j.'-d-'.$d.'-b-'.$h.'-start" value="'.$verdier[$i][$j][$d][$h]['starttid'].'" />';
							echo '&ndash;';
							echo '<input type="time" name="f-'.$i.'-p-'.$j.'-d-'.$d.'-b-'.$h.'-slutt" value="'.$verdier[$i][$j][$d][$h]['sluttid'].'" />';
							echo '</td>';
						}
						echo '</tr>';


					}

					echo '</table>';
				}
			}
			echo '<p><input type="hidden" name="fane_sendt_inn" value="standardtider"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
			echo '</form>';
		?>
	</section>
    <section class="tab-panel">
		<?php
			echo '<form method="post" action="">';
			echo '<h2>Registrer unntak</h2>';
			echo '<p>'.__('Filial:', 'bibliotider').'<br/>';
			echo '<select name="filial">';
			for ($i = 0; $i < $antall_filialer; $i++) {
				echo '<option value="'.$i.'">'.$filialer[$i][0].'</option>';
			}
			echo '</select>';
			echo '</p>';
			echo '<p>'.__('Fra dato:', 'bibliotider').'<br/>';
			echo '<input type="date" name="startdato" /></p>'."\n";
			echo '<p>'.__('Til dato:', 'bibliotider').'<br/>';
			echo '<input type="date" name="sluttdato" /></p>'."\n";

			echo '<table class="apningstider">';

			// Headerrad
			echo '<tr>';
			for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
				echo '<th>'.$betjenttyper[$h][0].'</th>';
			}
			echo '</tr>';

			for ( $h = 0; $h < $antall_betjenttyper; $h++ ) {
				echo '<td>';
				echo '<input type="time" name="'.$h.'-start" />';
				echo '&ndash;';
				echo '<input type="time" name="'.$h.'-slutt" />';
				echo '</td>';
			}

			echo '</table>';

			echo '<p><input type="hidden" name="fane_sendt_inn" value="unntak"><input type="submit" value="'.__('Lagre unntak', 'bibliotider').'" /></p>';
			echo '</form>';
			echo '<h2>'.__('Registrerte unntak', 'bibliotider').'</h2>';

			$query = 'SELECT u_startdato, u_sluttdato, id, filial, betjent, starttid, sluttid FROM ' . $this->tabnavn . ' WHERE u_startdato > CAST(\'' . date( 'Y-m-d', strtotime('-1 week') ) . '\' AS DATE) ORDER BY u_startdato';
			$result = $wpdb->get_results( $query, OBJECT_K );
			$num = $wpdb->num_rows;
			if (0 == $num) {
				echo '<p>Ingen avvik registrert</p>';
			}
			else {
				ksort( $result );
				$c = '<table class="apningstider">';

				// Headerrad
				$c .=  '<tr>';
				$c .=  '<th>' . __( 'Startdato', 'bibliotider' ) . '</th>';
				$c .=  '<th>' . __( 'Sluttdato', 'bibliotider' ) . '</th>';
				$c .=  '<th>' . __( 'Filial', 'bibliotider' ) . '</th>';
				$c .=  '<th>' . __( 'Type åpningstid', 'bibliotider' ) . '</th>';
				$c .=  '<th>' . __( 'Fra', 'bibliotider' ) . '</th>';
				$c .=  '<th>' . __( 'Til', 'bibliotider' ) . '</th>';
				$c .=  '<th></th>';
				$c .=  '</tr>';

				foreach ($result AS $dato => $obj) {
					$eksplodert_tid = explode( '-', $dato );
					//	$datotid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2], $eksplodert_tid[0] );

					$dagtid = mktime( 12, 0, 0, $eksplodert_tid[1], $eksplodert_tid[2], $eksplodert_tid[0] );

					$c .=  '<tr>';
					$c .= '<td>' . $obj->u_startdato . '</td>';
					$c .= '<td>' . $obj->u_sluttdato . '</td>';
					$c .= '<td>' . $filialer[ $obj->filial ][0] . '</td>';
					$c .= '<td>' . $betjenttyper[ $obj->betjent - 1 ][0] . '</td>';
					$c .= '<td>' . $obj->starttid . '</td>';
					$c .= '<td>' . $obj->sluttid . '</td>';
					$c .= '<td>'; 
					$c .= '<form method="post" action="">';
					$c .= '<input type="hidden" name="fane_sendt_inn" value="slettunntak"><input type="hidden" name="id" value="'.$obj->id.'"><input type="submit" value="'.__('Slett unntak', 'bibliotider').'" />';
					$c .= '</form>';
					$c .= '</td>';
					$c .=  '</tr>';

					
				}
				$c .=  '</table>';
				echo $c;
			}

			
		?>
    </section>
    <section class="tab-panel">

	<?php
		// REDIGER FILIALER
		echo '<form method="post" action="">';

		echo '<p>'.__('Rediger lista over avdelinger/filialer ved å redigere teksten i feltet under. Hver filial må stå på en egen linje.', 'bibliotider').'</p>';

		$antall_linjer = count( $filialer );
		$filialer_txt = '';

		for ($i = 0; $i < $antall_linjer; $i++) {
			if ($i > 0) {
				$filialer_txt .= "\n";
			}
			$filialer_txt .= implode('|', $filialer[$i]);
		}

		echo '<textarea name="filialliste">'.$filialer_txt.'</textarea>';
		echo '<p><input type="hidden" name="fane_sendt_inn" value="filialer"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
		echo '</form>';
	?>

	</section>
    <section class="tab-panel">
	<?php
		// REDIGER PERIODER
		echo '<form method="post" action="">';

		echo '<p>'.__('Her kan du redigere hvilke perioder året er delt inn i, f.eks. sommertid og vintertid.', 'bibliotider').'</p>';

			for ($i = 0; $i < $antall_filialer; $i++) {
				echo '<h2>'.$filialer[$i][0].'</h2>';
				if (!$antall_perioder[$i]) {
					echo '<p>'.__('Ingen perioder er registrert for denne filialen. Filialen er oppført som stengt hele året inntil du legger til minst en periode.').'</p>';
					echo '<p>'.sprintf(__('Legg til %1$s nye perioder på filialen %2$s', 'bibliotider'), '<input type="text" name="ny-periode-'.$i.'" value="0" size="2" />', '<strong>'.$filialer[$i][0].'</strong>').'</p>';
				}
				else {
					echo '<table>';
					for ($j = 0; $j < $antall_perioder[$i]; $j++) {
						echo '<tr>';
						echo '<td><input type="text" name="periodenavn-'.$perioder[$i][$j]['id'].'" value="'.sanitize_text_field($perioder[$i][$j]['navn']).'" /></td>';
						echo '<td><input type="text" name="periodestart-'.$perioder[$i][$j]['id'].'" size="6" value="'.date('d.m.', strtotime($perioder[$i][$j]['startdato'])).'" /></td>';
						echo '<td><input type="text" name="periodeslutt-'.$perioder[$i][$j]['id'].'" size="6" value="'.date('d.m.', strtotime($perioder[$i][$j]['sluttdato'])).'" /></td>';
						echo '</tr>';
					}
					echo '</table>';
					echo '<p>'.sprintf(__('Legg til %1$s nye perioder på filialen %2$s', 'bibliotider'), '<input type="text" name="ny-periode-'.$i.'" value="0" size="2" />', '<strong>'.$filialer[$i].'</strong>').'</p>';
				}
			}

		

		echo '<p><input type="hidden" name="fane_sendt_inn" value="perioder"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
		echo '</form>';
	?>
    </section>
    <section class="tab-panel">
	<?php
		// REDIGER TYPER ÅPNINGSTID
		echo '<form method="post" action="">';

		echo '<p>'.__('Biblioteket kan ha flere typer åpningstid (f.eks. betjent og meråpent). Du kan redigere lista over typer åpningstid ved å redigere teksten i feltet under.', 'bibliotider').'</p>';
		echo '<p>'.__('Hver type åpningstid må stå på en egen linje, i dette formatet: [Navn på type]: [Beskrivelse av type]', 'bibliotider').'</p>';
		echo '<p><em>['.__('Navn på type', 'bibliotider').']</em><strong>: </strong><em>['.__('Beskrivelse av type', 'bibliotider').']</em></p>';

		$betjenttyper_tekst = '';
		for ($i = 0; $i < $antall_betjenttyper; $i++) {
			if ($i > 0) {
				$betjenttyper_tekst .= "\n";
			}
			$betjenttyper_tekst .= $betjenttyper[$i][0].': '.$betjenttyper[$i][1];
		}

		echo '<textarea name="betjenttypeliste">'.$betjenttyper_tekst.'</textarea>';
		echo '<p><input type="hidden" name="fane_sendt_inn" value="betjenttyper"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
		echo '</form>';
	?>

    </section>
    <section class="tab-panel">
	<?php
		// ANDRE INNSTILLINGER
		echo '<form method="post" action="">';
		echo '<p>'.__('Slug til Åpningstider-side:', 'bibliotider').'<br /><input type="text" name="slug" value="'.get_option('bibliotider_side').'"></p>';
		echo '<p><input type="hidden" name="fane_sendt_inn" value="innstillinger"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
		echo '</form>';
	?>
    </section>
  </div>
  
</div>

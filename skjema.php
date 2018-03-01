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
				echo '<h2>'.$filialer[$i].'</h2>';
				for ($j = 0; $j < $antall_perioder; $j++) {
					$faktisk_p = $perioder[$j]['id'];

					echo '<h3>'.sprintf(__('%1$s (fra %2$s til %3$s)', 'bibliotider'), $perioder[$j]['navn'], date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$j]['startdato'])), date_i18n(__('j. F', 'bibliotider'), strtotime($perioder[$j]['sluttdato']))).'</h3>';

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

    </section>
    <section class="tab-panel">

	<?php
		// REDIGER FILIALER
		echo '<form method="post" action="">';

		echo '<p>'.__('Rediger lista over avdelinger/filialer ved å redigere teksten i feltet under. Hver filial må stå på en egen linje.', 'bibliotider').'</p>';
		echo '<textarea name="filialliste">'.implode("\n", $filialer).'</textarea>';
		echo '<p><input type="hidden" name="fane_sendt_inn" value="filialer"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
		echo '</form>';
	?>

	</section>
    <section class="tab-panel">

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
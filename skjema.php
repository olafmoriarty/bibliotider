<div class="katalogkort">
    
   <div class="tab">
       <input type="radio" id="bt-standardtider" name="tab-group-1" checked>
       <label for="bt-standardtider"><?php _e('Standardåpningstider', 'bibliotider'); ?></label>
       
       <div class="innhold">
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

					echo '<table>';

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
			echo '<p><input type="hidden" name="submit_standardtider" value="1"><input type="submit" value="'.__('Lagre endringer', 'bibliotider').'" /></p>';
			echo '</form>';
		?>
       </div> 
   </div>
    
    <div class="tab">
       <input type="radio" id="bt-unntak" name="tab-group-1">
       <label for="bt-unntak"><?php _e('Unntak', 'bibliotider'); ?></label>
     
       <div class="innhold">
           stuff
       </div> 
   </div>
   <div class="tab">
       <input type="radio" id="bt-filialer" name="tab-group-1">
       <label for="bt-filialer"><?php _e('Filialer', 'bibliotider'); ?></label>
       
       <div class="innhold">
           stuff
       </div> 
   </div>
    
    <div class="tab">
       <input type="radio" id="bt-perioder" name="tab-group-1">
       <label for="bt-perioder"><?php _e('Perioder', 'bibliotider'); ?></label>
     
       <div class="innhold">
           stuff
       </div> 
   </div>

    <div class="tab">
       <input type="radio" id="bt-typer" name="tab-group-1">
       <label for="bt-typer"><?php _e('Typer åpningstid', 'bibliotider'); ?></label>
     
       <div class="innhold">
           stuff
       </div> 
   </div>

</div>
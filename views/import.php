<div class="sejm-api-header-container">
	<header class="sejm-api-header">
		<div class="sejm-api-logo">	
			<span class="dashicons dashicons-bank" style="font-size: 26px; width: 30px; height: 30px; vertical-align: middle"></span>
		</div>
		<h1>
			Sejm API – Import
		</h1>
	</header>
</div>
<div class="sejm-api-plugin-container">
	<?php if (0 === $count_poslowie): ?>
		<div class="notice notice-info is-dismissible" style="display: block;">
			<p>W bazie nie ma jeszcze żadnych danych. Uruchom import, aby pobrać listę posłów i klubów.</p>
		</div>
	<?php endif; ?>

	<?php if (!$acf_active): ?>
		<main class="sejm-api-container sejm-api-warning">
			<h2><span class="dashicons dashicons-warning" style="vertical-align: text-bottom; margin-right: 5px;"></span> Brak wymaganej wtyczki</h2>
			<p>Do poprawnego działania importera wymagana jest wtyczka <strong>Advanced Custom Fields (ACF)</strong>.</p>
			<p>Bez niej nie możemy zapisać dodatkowych pól (klub, data urodzenia, itp.). Zainstaluj i aktywuj ACF, aby kontynuować.</p>
		</main>
	<?php else: ?>
		<main class="sejm-api-container">
			<h2>Zaimportuj aktualne dane o posłach (X kadencja) z API Sejmu RP</h2>
			<p>Import obejmuje listę wszystkich posłów (wraz z danymi kontaktowymi, datą urodzenia, zawodem itp.) oraz informacje o klubach i kołach poselskich.</p>
			<p>Nie są pobierane aktualnie dane na temat aktywności posłów (głosowania, interpelacje itp.) oraz ich oświadczeń.</p>
		</main>
		<main class="sejm-api-container">
			<button id="sejm-api-run-import" class="button button-primary button-hero sejm-api-import-button">
				<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: 5px;"></span> Uruchom Import Danych
			</button>

			<div id="sejm-api-progress-container" class="sejm-api-import-button" style="display:none; position: relative; overflow: hidden; background: #e5e5e5; cursor: default; border: none;">
				<div id="sejm-api-progress-fill" style="height: 100%; width: 0%; background: var(--sejm-api-primary); position: absolute; top:0; left:0; transition: width 0.2s;"></div>
				<div id="sejm-api-progress-text" style="position: relative; z-index: 2; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #333; font-weight: 700;">Inicjalizacja...</div>
			</div>

			<div id="sejm-api-log" style="text-align: left; background: #f0f0f1; padding: 10px; height: 150px; overflow-y: auto; border: 1px solid #ccc; font-family: monospace; font-size: 12px; margin-top: 20px;"></div>
		</main>
		<main class="sejm-api-container sejm-api-notice" id="sejm-api-acf-notice" style="display:none">
			<h2>Ważne: Synchronizacja ACF</h2>
			<p>Aby wszystkie pola (np. Klub, Data urodzenia) działały poprawnie, zsynchronizuj definicje JSON.</p>
			<div style="display: flex; gap: 10px; margin-top: 10px;">
				<a href="<?php echo admin_url('edit.php?post_type=acf-field-group&post_status=sync'); ?>" class="button" target="_blank">
					Grupy pól
				</a>
				<a href="<?php echo admin_url('edit.php?post_type=acf-post-type&post_status=sync'); ?>" class="button" target="_blank">
					Typy treści
				</a>
				<a href="<?php echo admin_url('edit.php?post_type=acf-taxonomyp&post_status=sync'); ?>" class="button" target="_blank">
					Taksonomie
				</a>
			</div>
		</main>
	<?php endif; ?>
</div>


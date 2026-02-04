<?php

class Sejm_API_Importer {

	private $api_url = 'https://api.sejm.gov.pl/sejm/term10/MP';

	public function fetch_and_cache_list() {
		$response = wp_remote_get($this->api_url);

		if (is_wp_error($response)) {
			return new WP_Error('api_error', 'Błąd: Nie udało się połączyć z API Sejmu.');
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (empty($data) || ! is_array($data)) {
			return new WP_Error('api_empty', 'Błąd: API zwróciło puste dane.');
		}

		if (!empty($data[0])) {
			$this->generate_acf_json($data[0]);
			$this->generate_acf_post_type_json();
			$this->generate_acf_club_json();
			$this->generate_acf_taxonomy_json();
		}

		set_transient('sejm_api_import_queue', $data, 3600);

		return count($data);
	}

	public function process_item_by_index($index) {
		$data = get_transient('sejm_api_import_queue');

		if (empty($data) || !isset($data[$index])) {
			return new WP_Error('queue_error', 'Błąd kolejki lub koniec danych.');
		}

		$posel = $data[$index];
		$is_new = $this->process_single_posel($posel);

		return [
			'name'   => $posel->firstLastName,
			'status' => $is_new ? 'new' : 'updated'
		];
	}

	public function import_data() {
		$count = $this->fetch_and_cache_list();
		if (is_wp_error($count)) return $count->get_error_message();
 
		$data = get_transient( 'sejm_api_import_queue' );
		foreach ($data as $posel) {
			$this->process_single_posel($posel);
		}
		
		return "Zakończono. Przetworzono: $count.";
	}

	private function process_single_posel($posel_data) {
		$existing_post = $this->get_posel_by_api_id($posel_data->id);
		$is_new        = false;

		$post_data = [
			'post_title'  => $posel_data->firstLastName,
			'post_type'   => 'posel',
			'post_status' => 'publish',
		];

		if ($existing_post) {
			$post_data['ID'] = $existing_post->ID;
			$post_id         = wp_update_post($post_data);
		} else {
			$post_id = wp_insert_post($post_data);
			$is_new  = true;
		}

		if ($post_id && ! is_wp_error($post_id)) {
			$this->update_all_meta_fields($post_id, $posel_data);
			$this->import_mp_photo($post_id, $posel_data->id);

			if (!empty( $posel_data->club)) {
				$this->process_club($posel_data->club, $post_id);
			}
		}

		return $is_new;
	}

	private function update_all_meta_fields($post_id, $data_object) {
		update_post_meta($post_id, 'sejm_id', $data_object->id);

		foreach ($data_object as $key => $value) {
			if ($key === 'id' || $key === 'firstLastName') {
				continue;
			}

			if (is_scalar($value) || is_null($value)) {
				if (function_exists('update_field')) {
					update_field($key, $value, $post_id);
				} else {
					update_post_meta($post_id, $key, $value);
				}
			} elseif (is_array($value) || is_object($value)) {
				$json_value = json_encode($value, JSON_UNESCAPED_UNICODE);
				
				if (function_exists('update_field')) {
					update_field($key, $json_value, $post_id);
				} else {
					update_post_meta($post_id, $key, $json_value);
				}
			}
		}
	}

	private function get_posel_by_api_id($api_id) {
		$query = new WP_Query([
			'post_type'      => 'posel',
			'meta_key'       => 'sejm_id',
			'meta_value'     => $api_id,
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		]);

		if ($query->have_posts()) {
			return $query->posts[0];
		}

		return null;
	}

	private function import_mp_photo($post_id, $api_id) {
		if (has_post_thumbnail($post_id)) {
			return;
		}

		$photo_url = "https://api.sejm.gov.pl/sejm/term10/MP/{$api_id}/photo";
		
		$response = wp_remote_get($photo_url);
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ) {
			return;
		}

		$image_data = wp_remote_retrieve_body( $response );
		if (empty($image_data)) {
			return;
		}

		$filename = "posel_{$api_id}.jpg";
		$upload_dir = wp_upload_dir();
		
		if (wp_mkdir_p($upload_dir['path'])) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents($file, $image_data);
		$wp_filetype = wp_check_filetype($filename, null);

		$attachment = [
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name($filename),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment($attachment, $file, $post_id);
		
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		
		$attach_data = wp_generate_attachment_metadata($attach_id, $file);
		wp_update_attachment_metadata($attach_id, $attach_data);

		set_post_thumbnail($post_id, $attach_id);
	}

	private function process_club($club_id, $post_id) {
		$term = get_term_by('slug', $club_id, 'klub');

		if (!$term) {
			$inserted = wp_insert_term($club_id, 'klub', [ 
				'slug' => $club_id 
			]);

			if (is_wp_error($inserted)) {
				return;
			}
			
			$term_id = $inserted['term_id'];
			$this->sync_club_details($club_id, $term_id);
		} else {
			$term_id = $term->term_id;
			
			if (!get_term_meta( $term_id, 'contact_email', true)) {
				$this->sync_club_details($club_id, $term_id);
			}
		}

		wp_set_post_terms($post_id, [$term_id], 'klub');
	}

	private function sync_club_details($api_club_id, $term_id) {
		$url = "https://api.sejm.gov.pl/sejm/term10/clubs/{$api_club_id}";
		$response = wp_remote_get($url);
		
		if (is_wp_error($response)) {
			return;
		}

		$data = json_decode(wp_remote_retrieve_body($response));
		
		if (!$data) {
			return;
		}

		if (!empty($data->name)) {
			wp_update_term($term_id, 'klub', ['name' => $data->name]);
		}

		if (!empty( $data->phone)) update_term_meta($term_id, 'contact_phone', $data->phone);
		if (!empty( $data->email)) update_term_meta($term_id, 'contact_email', $data->email);
		if (!empty( $data->fax))   update_term_meta($term_id, 'contact_fax', $data->fax);
		if (!empty( $data->membersCount )) update_term_meta($term_id, 'members_count', $data->membersCount);

		$this->import_club_logo($api_club_id, $term_id);
	}

	private function import_club_logo($api_club_id, $term_id) {
		if (get_term_meta($term_id, 'logo_id', true)) {
			return;
		}

		$logo_url = "https://api.sejm.gov.pl/sejm/term10/clubs/{$api_club_id}/logo";
		$response = wp_remote_get($logo_url);
		
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 ) {
			return;
		}
		
		$image_data = wp_remote_retrieve_body($response);
		$filename = "klub_{$api_club_id}.png";
		
		$upload_dir = wp_upload_dir();
		
		if (wp_mkdir_p($upload_dir['path'])) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		file_put_contents($file, $image_data);
		$wp_filetype = wp_check_filetype($filename, null);

		$attachment = [
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => 'Klub ' . $api_club_id,
			'post_status'    => 'inherit',
		];
		
		$attach_id = wp_insert_attachment($attachment, $file, 0);
		
		require_once( ABSPATH . 'wp-admin/includes/image.php');
		
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file);
		wp_update_attachment_metadata($attach_id, $attach_data);

		update_term_meta($term_id, 'logo_id', $attach_id);
	}

	private function generate_acf_json($sample_data) {
		if (!is_object($sample_data)) {
			return;
		}

		$fields = [];
		$fields[] = [
			'key'               => 'field_sejm_api_id',
			'label'             => 'ID (Sejm API)',
			'name'              => 'sejm_id',
			'type'              => 'number',
			'instructions'      => 'Techniczne ID z systemu API Sejmu.',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => ['width' => '50'],
			'default_value'     => '',
			'readonly'          => 1,
		];

		foreach ($sample_data as $key => $value) {
			if ($key === 'id' || $key === 'firstLastName') {
				continue;
			}

			$label = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', $key));
			$type = 'text';
			
			if (is_array($value) || is_object($value)) {
				$type = 'textarea';
			}

			$fields[] = [
				'key'               => 'field_sejm_api_' . strtolower($key),
				'label'             => $label,
				'name'              => $key,
				'type'              => $type,
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => ['width' => ''],
				'default_value'     => '',
			];
		}

		$group = [
			'key'                   => 'group_sejm_api_auto',
			'title'                 => 'Dane Posła (Sejm API)',
			'fields'                => $fields,
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'posel',
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => 'Automatycznie wygenerowana grupa pól na podstawie importu z API.',
			'modified'              => time(),
		];

		$dir = SEJM_API_PATH . 'acf-json';
		
		if (!file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$file_path = $dir . '/group_sejm_api_auto.json';
		
		file_put_contents( 
			$file_path, 
			json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
		);
	}

	private function generate_acf_post_type_json() {
		$post_type_data = [
			'key'                    => 'post_type_posel_sejm_api',
			'title'                  => 'Posłowie',
			'menu_order'             => 0,
			'active'                 => true,
			'post_type'              => 'posel',
			'advanced_configuration' => true,
			'import_source'          => '',
			'import_date'            => '',
			'labels'        => [
				'name'               => 'Posłowie',
				'singular_name'      => 'Poseł',
				'menu_name'          => 'Posłowie',
				'name_admin_bar'     => 'Poseł',
				'add_new'            => 'Dodaj nowego',
				'add_new_item'       => 'Dodaj nowego posła',
				'new_item'           => 'Nowy poseł',
				'edit_item'          => 'Edytuj posła',
				'view_item'          => 'Zobacz posła',
				'all_items'          => 'Wszyscy posłowie',
				'search_items'       => 'Szukaj posłów',
				'not_found'          => 'Nie znaleziono posłów',
				'not_found_in_trash' => 'Nie znaleziono posłów w koszu',
			],
			'description'         => '',
			'public'              => true,
			'hierarchical'        => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true, 
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-businessperson',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => [
				'title',
				'thumbnail',
				'custom-fields',
			],
			'taxonomies'          => [],
			'has_archive'         => true,
			'rewrite'             => [
				'permalink' => 'posel',
				'feeds'     => false,
				'pages'     => true,
				'with_front'=> true,
			],
			'query_var'             => 'posel',
			'show_in_rest'          => true,
			'rest_base'             => '',
			'rest_controller_class' => '',
		];
		
		$post_type_data['show_in_menu'] = 'sejm-api'; 
		$dir = SEJM_API_PATH . 'acf-json';
		
		if (!file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$file_path = $dir . '/post_type_posel_sejm_api.json';
		
		file_put_contents( 
			$file_path, 
			json_encode($post_type_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
		);
	}

	private function generate_acf_club_json() {
		$fields = [
			[
				'key'           => 'field_klub_logo',
				'label'         => 'Logo Klubu',
				'name'          => 'logo_id',
				'type'          => 'image',
				'return_format' => 'array',
				'library'       => 'all',
			],
			[
				'key'     => 'field_klub_email',
				'label'   => 'Email',
				'name'    => 'contact_email',
				'type'    => 'email',
			],
			[
				'key'     => 'field_klub_phone',
				'label'   => 'Telefon',
				'name'    => 'contact_phone',
				'type'    => 'text',
			],
			[
				'key'     => 'field_klub_fax',
				'label'   => 'Fax',
				'name'    => 'contact_fax',
				'type'    => 'text',
			],
			[
				'key'     => 'field_klub_members',
				'label'   => 'Liczba członków',
				'name'    => 'members_count',
				'type'    => 'number',
			],
		];

		$group = [
			'key'                   => 'group_sejm_api_kluby',
			'title'                 => 'Dane Klubu (Sejm API)',
			'fields'                => $fields,
			'location'              => [
				[
					[
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => 'klub',
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => 'Automatycznie wygenerowane pola dla Klubów.',
			'modified'              => time(),
		];

		$dir = SEJM_API_PATH . 'acf-json';
		if (!file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$file_path = $dir . '/group_sejm_api_kluby.json';

		file_put_contents( 
			$file_path, 
			json_encode($group, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
		);
	}

	private function generate_acf_taxonomy_json() {
		$taxonomy_data = [
			'key'                    => 'taxonomy_klub_sejm_api',
			'title'                  => 'Kluby',
			'menu_order'             => 0,
			'active'                 => true,
			'taxonomy'               => 'klub',
			'object_type'            => [ 'posel' ],
			'advanced_configuration' => true,
			'import_source'          => '',
			'import_date'            => '',
			'labels'                 => [
				'name'              => 'Kluby',
				'singular_name'     => 'Klub',
				'search_items'      => 'Szukaj klubów',
				'all_items'         => 'Wszystkie kluby',
				'parent_item'       => 'Klub nadrzędny',
				'parent_item_colon' => 'Klub nadrzędny:',
				'edit_item'         => 'Edytuj klub',
				'update_item'       => 'Zaktualizuj klub',
				'add_new_item'      => 'Dodaj nowy klub',
				'new_item_name'     => 'Nazwa nowego klubu',
				'menu_name'         => 'Kluby',
			],
			'description'       => '',
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_menu'      => true, 
			'show_admin_column' => true,
			'query_var'         => 'klub',
			'rewrite'           => [
				'slug'         => 'klub',
				'with_front'   => true,
				'hierarchical' => false,
			],
			'show_in_rest'          => true,
			'rest_base'             => '',
			'rest_controller_class' => '',
		];

		$dir = SEJM_API_PATH . 'acf-json';
		if (!file_exists($dir)) {
			mkdir($dir, 0755, true);
		}

		$file_path = $dir . '/taxonomy_klub_sejm_api.json';
		
		file_put_contents( 
			$file_path, 
			json_encode($taxonomy_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
		);
	}
}

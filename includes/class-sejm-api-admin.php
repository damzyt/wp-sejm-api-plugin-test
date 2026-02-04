<?php

class Sejm_API_Admin {

	private $importer;

	public function __construct( $importer ) {
		$this->importer = $importer;
	}

	public function add_menu_page() {
		add_menu_page(
			'Sejm API',
			'Sejm API',
			'manage_options',
			'sejm-api',
			[ $this, 'render_import_page' ],
			'dashicons-bank',
			5
		);

		add_submenu_page(
			'sejm-api',
			'Kluby Parlamentarne',
			'Kluby',
			'manage_categories',
			'edit-tags.php?taxonomy=klub&post_type=posel'
		);

		add_submenu_page(
			'sejm-api',
			'Import Danych',
			'Import',
			'manage_options',
			'sejm-api',
			[ $this, 'render_import_page' ]
		);
	}

	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'page_sejm-api' ) === false ) {
			return;
		}

		wp_enqueue_script(
			'sejm-api-admin-import',
			SEJM_API_URL . 'assets/js/admin-import.js',
			[ 'jquery' ],
			SEJM_API_VERSION,
			true
		);

		wp_localize_script( 'sejm-api-admin-import', 'sejmApiImport', [
			'nonce' => wp_create_nonce( 'sejm_api_import_ajax' )
		] );

		wp_enqueue_style( 'sejm-api-admin-css', SEJM_API_URL . 'assets/style.css', [], SEJM_API_VERSION ); 
	}

	public function render_import_page() {
		$acf_active = function_exists( 'update_field' );

		$count_poslowie = wp_count_posts( 'posel' )->publish;
		$count_kluby    = wp_count_terms( [ 'taxonomy' => 'klub', 'hide_empty' => false ] );

		$view_path = SEJM_API_PATH . 'views/import.php';
		if ( file_exists( $view_path ) ) {
			require_once $view_path;
		}
	}

    public function ajax_start_import() {
        check_ajax_referer( 'sejm_api_import_ajax', 'nonce' );
        
        $result = $this->importer->fetch_and_cache_list();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( [ 'total' => $result ] );
    }

    public function ajax_process_item() {
        check_ajax_referer( 'sejm_api_import_ajax', 'nonce' );

        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $result = $this->importer->process_item_by_index( $offset );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

	public function add_custom_columns( $columns ) {
		$new_columns = [];
		$new_columns['cb'] = $columns['cb'];
		$new_columns['sejm_id'] = 'ID (Sejm)';
		$new_columns['title']   = 'Imię i Nazwisko';
		$new_columns['klub']    = 'Klub';
		$new_columns['date']    = 'Data';
		return $new_columns;
	}

	public function render_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'sejm_id':
				echo esc_html( get_post_meta( $post_id, 'sejm_id', true ) );
				break;
			
			case 'klub':
				$terms = get_the_terms( $post_id, 'klub' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$out = [];
					foreach ( $terms as $term ) {
						$link = add_query_arg([
							'post_type' => 'posel',
							'klub'      => $term->slug,
						], admin_url('edit.php'));
						$out[] = sprintf('<a href="%s">%s</a>', esc_url($link), esc_html($term->name));
					}
					echo implode(', ', $out);
				} else {
					echo '—';
				}
				break;
		}
	}
}
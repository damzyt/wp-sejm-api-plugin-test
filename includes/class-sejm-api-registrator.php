<?php

class Sejm_API_Registrator 
{
    public function register_post_type() 
    {
		$labels = [
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
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'sejm-api', 
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'posel' ], 
			'capability_type'    => 'post',
			'has_archive'        => true, 
			'hierarchical'       => false,
			'menu_position'      => 5, 
			'menu_icon'          => 'dashicons-businessperson', 
			'supports'           => [ 'title', 'thumbnail', 'custom-fields' ], 
			'show_in_rest'       => true, 
		];

		register_post_type('posel', $args);

		$taxonomy_labels = [
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
		];

		$taxonomy_args = [
			'hierarchical'      => true,
			'labels'            => $taxonomy_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => [ 'slug' => 'klub' ],
			'show_in_rest'      => true,
		];

		register_taxonomy( 'klub', [ 'posel' ], $taxonomy_args );
	}
}
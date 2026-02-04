<?php

class Sejm_API_Blocks {

	public function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	public function register_blocks() {
		wp_register_script(
			'sejm-api-block-field',
			SEJM_API_URL . 'assets/js/block-sejm-field.js',
			[ 'wp-blocks', 'wp-editor', 'wp-components', 'wp-element', 'wp-data' ],
			SEJM_API_VERSION,
			true
		);

		wp_register_script(
			'sejm-api-block-club',
			SEJM_API_URL . 'assets/js/block-sejm-club.js',
			[ 'wp-blocks', 'wp-editor', 'wp-components', 'wp-element', 'wp-data' ],
			SEJM_API_VERSION,
			true
		);

		register_block_type( 'sejm-api/dane-posla', [
			'editor_script'   => 'sejm-api-block-field',
			'render_callback' => [ $this, 'render_block' ],
			'attributes'      => [
				'fieldKey' => [
					'type'    => 'string',
					'default' => 'club',
				],
			],
			'uses_context'    => [ 'postId' ],
			'supports'        => [
				'typography' => [
					'fontSize'                        => true,
					'lineHeight'                      => true,
					'__experimentalFontFamily'        => true,
					'__experimentalFontWeight'        => true,
					'__experimentalFontStyle'         => true,
					'__experimentalTextTransform'     => true,
					'__experimentalTextDecoration'    => true,
					'__experimentalLetterSpacing'     => true,
				],
				'color' => [ 
					'text'       => true,
					'background' => true,
				],
			],

		] );

		register_block_type( 'sejm-api/dane-klubu', [
			'editor_script'   => 'sejm-api-block-club',
			'render_callback' => [ $this, 'render_block_club' ],
			'attributes'      => [
				'fieldKey' => [
					'type'    => 'string',
					'default' => 'name',
				],
			],
			'uses_context'    => [ 'postId' ],
			'supports'        => [
				'typography' => [
					'fontSize' => true,
					'lineHeight' => true,
					'__experimentalFontWeight' => true,
				],
				'color' => [
					'text' => true,
					'background' => true,
				],
			],
		] );
	}

	public function render_block( $attributes, $content, $block ) {
		$post_id = 0;

		if ( isset( $block->context['postId'] ) ) {
			$post_id = $block->context['postId'];
		} else {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '';
		}

		$key = isset( $attributes['fieldKey'] ) ? $attributes['fieldKey'] : 'club';

		$val = '';
		if ( $key === 'district_composite' ) {
			$nazwa = get_post_meta( $post_id, 'districtName', true );
			$numer = get_post_meta( $post_id, 'districtNum', true );
			if ( $nazwa && $numer ) {
				$val = sprintf( '%s (nr %s)', $nazwa, $numer );
			} else {
				$val = $nazwa;
			}
		} elseif ( $key === 'birth_composite' ) {
			$data_ur = get_post_meta( $post_id, 'birthDate', true );
			$miejsce = get_post_meta( $post_id, 'birthLocation', true );
			
			$parts = [];
			if ( $data_ur ) $parts[] = $data_ur;
			if ( $miejsce ) $parts[] = $miejsce;
			
			$val = implode( ', ', $parts );
		} else {
			$val = get_post_meta( $post_id, $key, true );
		}

		if ( empty( $val ) ) {
			return '';
		}

		if ( is_array( $val ) || is_object( $val ) ) {
			$val = json_encode( $val, JSON_UNESCAPED_UNICODE );
		}
		$val = esc_html( $val );
		
		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => sprintf( 'sejm-api-field sejm-api-field-%s', esc_attr( $key ) ) ] );

		return sprintf( '<div %s>%s</div>', $wrapper_attributes, $val );
	}

	public function render_block_club( $attributes, $content, $block ) {
		$post_id = 0;
		if ( isset( $block->context['postId'] ) ) {
			$post_id = $block->context['postId'];
		} else {
			$post_id = get_the_ID();
		}

		$term_id = 0;

		if ( is_tax( 'klub' ) ) {
			$term_id = get_queried_object_id();
		} 
		elseif ( $post_id ) {
			$terms = get_the_terms( $post_id, 'klub' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term_id = $terms[0]->term_id;
			}
		}

		if ( ! $term_id ) {
			return '';
		}

		$key = isset( $attributes['fieldKey'] ) ? $attributes['fieldKey'] : 'name';
		$val = '';

		if ( $key === 'name' ) {
			$term = get_term( $term_id );
			$val  = $term->name;
		} elseif ( $key === 'logo' ) {
			$logo_id = get_term_meta( $term_id, 'logo_id', true );
			if ( $logo_id ) {
				$val = wp_get_attachment_image( $logo_id, 'thumbnail' );
			}
		} else {
			$val = get_term_meta( $term_id, $key, true );
		}

		if ( empty( $val ) ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => sprintf( 'sejm-api-club-field sejm-api-club-field-%s', esc_attr( $key ) ) ] );

		if ( $key !== 'logo' ) {
			$val = esc_html( $val );
		}

		return sprintf( '<div %s>%s</div>', $wrapper_attributes, $val );
	}
}

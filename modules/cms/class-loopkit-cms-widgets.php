<?php
/**
 * CMS widgets for the builder.
 *
 * Collection List is the widget that puts a collection on a page: pick a
 * collection, say how many rows and in what order, and lay the card out by
 * binding each part to a field. Without it the CMS is an admin screen with
 * nowhere to show its content.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection List — rows from a CMS collection, laid out as cards.
 */
class Loopkit_Widget_Collection_List extends Loopkit_Widget_Base {

	public function get_name() {
		return 'collection-list';
	}

	public function get_title() {
		return __( 'Collection List', 'loopkit' );
	}

	public function get_icon() {
		return 'dashicons-database';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'cms', 'collection', 'list', 'repeater', 'grid', 'items', 'content' );
	}

	/**
	 * Collections as a select list, newest content first.
	 *
	 * @return array id => name
	 */
	protected function collection_options() {
		$out = array( '' => __( 'Choose a collection', 'loopkit' ) );
		if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
			return $out;
		}
		foreach ( Loopkit_Module_CMS::get_collections() as $c ) {
			$out[ (string) $c->id ] = $c->name;
		}
		return $out;
	}

	/**
	 * Every field across every collection, so the binding dropdowns have
	 * something to offer before a collection is picked. Each option is
	 * labelled with its collection so two "Title" fields stay apart.
	 *
	 * @return array key => label
	 */
	protected function field_options( $types = array() ) {
		$out = array( '' => __( '— none —', 'loopkit' ) );
		if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
			return $out;
		}
		foreach ( Loopkit_Module_CMS::get_collections() as $c ) {
			foreach ( Loopkit_Module_CMS::get_fields( (int) $c->id ) as $f ) {
				// A slot that can only show a picture should not offer an audio
				// field: picking one used to render a broken image.
				if ( $types && ! in_array( $f->type, $types, true ) ) {
					continue;
				}
				$label = $f->label . ' — ' . $c->name;
				if ( ! isset( $out[ $f->field_key ] ) ) {
					$out[ $f->field_key ] = $label;
				}
			}
		}
		return $out;
	}

	/**
	 * The type of a field, by key, across every collection.
	 *
	 * The widget binds by key rather than by id, so the same key in two
	 * collections resolves to whichever is found first — which is already
	 * how the bindings work, and is why the labels carry the collection.
	 *
	 * @param string $key Field key.
	 * @return string Field type, or '' when nothing matches.
	 */
	protected function field_type( $key ) {
		if ( '' === $key || ! class_exists( 'Loopkit_Module_CMS' ) ) {
			return '';
		}
		foreach ( Loopkit_Module_CMS::get_collections() as $c ) {
			foreach ( Loopkit_Module_CMS::get_fields( (int) $c->id ) as $f ) {
				if ( $f->field_key === $key ) {
					return $f->type;
				}
			}
		}
		return '';
	}

	protected function register_controls() {
		$collections = $this->collection_options();
		$fields      = $this->field_options();
		// Image and SVG are the only two a picture slot can show; video and
		// audio need their own player rather than an <img>.
		$pic_fields  = $this->field_options( array( 'image', 'svg' ) );
		$play_fields = $this->field_options( array( 'video', 'audio' ) );

		return array(
			'content' => array(
				array(
					'id'       => 'section_cl_source',
					'title'    => __( 'Content', 'loopkit' ),
					'tab'      => 'content',
					'controls' => array(
						array( 'name' => 'cl_collection', 'label' => __( 'Collection', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $collections ),
						array( 'name' => 'cl_count', 'label' => __( 'How many', 'loopkit' ), 'type' => 'number', 'default' => 6 ),
						array(
							'name'    => 'cl_orderby',
							'label'   => __( 'Order by', 'loopkit' ),
							'type'    => 'select',
							'default' => 'id',
							'options' => array(
								'id'         => __( 'Newest first', 'loopkit' ),
								'id_asc'     => __( 'Oldest first', 'loopkit' ),
								'field'      => __( 'A field', 'loopkit' ),
								'updated_at' => __( 'Recently updated', 'loopkit' ),
							),
						),
						array( 'name' => 'cl_order_field', 'label' => __( 'Order by this field', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields, 'condition' => array( 'cl_orderby' => 'field' ) ),
						array(
							'name'    => 'cl_order_dir',
							'label'   => __( 'Direction', 'loopkit' ),
							'type'    => 'select',
							'default' => 'DESC',
							'options' => array( 'DESC' => __( 'Descending', 'loopkit' ), 'ASC' => __( 'Ascending', 'loopkit' ) ),
							'condition' => array( 'cl_orderby' => 'field' ),
						),
						array( 'name' => 'cl_filter_field', 'label' => __( 'Only rows where this field…', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields ),
						array( 'name' => 'cl_filter_value', 'label' => __( '…contains this', 'loopkit' ), 'type' => 'text', 'default' => '', 'condition' => array( 'cl_filter_field!' => '' ) ),
						array( 'name' => 'cl_empty_text', 'label' => __( 'Shown when there is nothing', 'loopkit' ), 'type' => 'text', 'default' => __( 'Nothing here yet.', 'loopkit' ) ),
					),
				),
				array(
					'id'       => 'section_cl_card',
					'title'    => __( 'Card', 'loopkit' ),
					'tab'      => 'content',
					'controls' => array(
						array(
							'name'    => 'cl_layout',
							'label'   => __( 'Layout', 'loopkit' ),
							'type'    => 'select',
							'default' => 'grid',
							'options' => array(
								'grid' => __( 'Grid', 'loopkit' ),
								'list' => __( 'List', 'loopkit' ),
							),
						),
						array( 'name' => 'cl_columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 1, 'max' => 6, 'default' => array( 'size' => 3, 'unit' => 'px' ), 'condition' => array( 'cl_layout' => 'grid' ) ),
						array( 'name' => 'cl_image_field', 'label' => __( 'Image', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $pic_fields ),
						array( 'name' => 'cl_media_field', 'label' => __( 'Video or audio', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $play_fields ),
						array( 'name' => 'cl_media_controls', 'label' => __( 'Show player controls', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
						array( 'name' => 'cl_title_field', 'label' => __( 'Title', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields ),
						array( 'name' => 'cl_meta_field', 'label' => __( 'Small line above the title', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields ),
						array( 'name' => 'cl_text_field', 'label' => __( 'Description', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields ),
						array( 'name' => 'cl_link_field', 'label' => __( 'Link', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => $fields ),
						array( 'name' => 'cl_link_text', 'label' => __( 'Link text', 'loopkit' ), 'type' => 'text', 'default' => __( 'Read more', 'loopkit' ), 'condition' => array( 'cl_link_field!' => '' ) ),
						array( 'name' => 'cl_card_links', 'label' => __( 'Make the whole card clickable', 'loopkit' ), 'type' => 'switcher', 'default' => 'no', 'condition' => array( 'cl_link_field!' => '' ) ),
						array(
							'name'    => 'cl_title_tag',
							'label'   => __( 'Title HTML tag', 'loopkit' ),
							'type'    => 'select',
							'default' => 'h3',
							'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div', 'span' => 'span' ),
						),
					),
				),
			),
			'style' => array(
				array(
					'id'       => 'section_cl_style',
					'title'    => __( 'Layout', 'loopkit' ),
					'tab'      => 'style',
					'controls' => array(
						array( 'name' => 'cl_gap', 'label' => __( 'Gap', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 0, 'max' => 80, 'default' => array( 'size' => 24, 'unit' => 'px' ) ),
						array( 'name' => 'cl_card_bg', 'label' => __( 'Card Background', 'loopkit' ), 'type' => 'color' ),
						array( 'name' => 'cl_card_padding', 'label' => __( 'Card Padding', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
						array( 'name' => 'cl_card_radius', 'label' => __( 'Card Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
						array( 'name' => 'cl_card_border_type', 'label' => __( 'Card Border', 'loopkit' ), 'type' => 'select', 'default' => '', 'options' => array( '' => __( 'None', 'loopkit' ), 'solid' => __( 'Solid', 'loopkit' ), 'dashed' => __( 'Dashed', 'loopkit' ) ) ),
						array( 'name' => 'cl_card_border_width', 'label' => __( 'Border Width', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true, 'condition' => array( 'cl_card_border_type!' => '' ) ),
						array( 'name' => 'cl_card_border_color', 'label' => __( 'Border Colour', 'loopkit' ), 'type' => 'color', 'condition' => array( 'cl_card_border_type!' => '' ) ),
					),
				),
				array(
					'id'       => 'section_cl_parts',
					'title'    => __( 'Card Parts', 'loopkit' ),
					'tab'      => 'style',
					'controls' => array(
						array( 'name' => 'cl_img_height', 'label' => __( 'Image Height', 'loopkit' ), 'type' => 'slider', 'responsive' => true, 'min' => 80, 'max' => 600, 'default' => array( 'size' => 200, 'unit' => 'px' ) ),
						array( 'name' => 'cl_img_radius', 'label' => __( 'Image Radius', 'loopkit' ), 'type' => 'dimensions', 'responsive' => true ),
						array( 'name' => 'cl_meta_color', 'label' => __( 'Small Line Colour', 'loopkit' ), 'type' => 'color' ),
						array( 'name' => 'cl_meta_typography', 'label' => __( 'Small Line Typography', 'loopkit' ), 'type' => 'typography' ),
						array( 'name' => 'cl_title_color', 'label' => __( 'Title Colour', 'loopkit' ), 'type' => 'color' ),
						array( 'name' => 'cl_title_typography', 'label' => __( 'Title Typography', 'loopkit' ), 'type' => 'typography' ),
						array( 'name' => 'cl_text_color', 'label' => __( 'Description Colour', 'loopkit' ), 'type' => 'color' ),
						array( 'name' => 'cl_text_typography', 'label' => __( 'Description Typography', 'loopkit' ), 'type' => 'typography' ),
						array( 'name' => 'cl_link_color', 'label' => __( 'Link Colour', 'loopkit' ), 'type' => 'color' ),
						array( 'name' => 'cl_link_typography', 'label' => __( 'Link Typography', 'loopkit' ), 'type' => 'typography' ),
					),
				),
			),
		);
	}

	/**
	 * One value out of a row, by field key.
	 *
	 * @param array  $data Row data.
	 * @param string $key  Field key.
	 * @return string
	 */
	protected function value( $data, $key ) {
		if ( '' === $key || ! isset( $data[ $key ] ) ) {
			return '';
		}
		$v = $data[ $key ];
		if ( is_array( $v ) ) {
			$v = implode( ', ', array_filter( array_map( 'strval', $v ) ) );
		}
		return (string) $v;
	}

	public function render( $settings, $el, $editor_mode = false ) {
		if ( ! class_exists( 'Loopkit_Module_CMS' ) ) {
			return $editor_mode
				? '<div class="lk-cl-notice">' . esc_html__( 'Turn the CMS module on to use this widget.', 'loopkit' ) . '</div>'
				: '';
		}

		$collection_id = absint( $settings['cl_collection'] ?? 0 );
		$collection    = $collection_id ? Loopkit_Module_CMS::get_collection( $collection_id ) : null;

		if ( ! $collection ) {
			return $editor_mode
				? '<div class="lk-cl-notice">' . esc_html__( 'Pick a collection in the panel to show its content here.', 'loopkit' ) . '</div>'
				: '';
		}

		$count   = max( 1, absint( $settings['cl_count'] ?? 6 ) );
		$orderby = (string) ( $settings['cl_orderby'] ?? 'id' );
		$args    = array( 'per_page' => $count, 'page' => 1, 'status' => 'published' );

		if ( 'field' === $orderby ) {
			$args['orderby'] = (string) ( $settings['cl_order_field'] ?? '' );
			$args['order']   = 'ASC' === ( $settings['cl_order_dir'] ?? 'DESC' ) ? 'ASC' : 'DESC';
		} elseif ( 'id_asc' === $orderby ) {
			$args['orderby'] = 'id';
			$args['order']   = 'ASC';
		} elseif ( 'updated_at' === $orderby ) {
			$args['orderby'] = 'updated_at';
			$args['order']   = 'DESC';
		} else {
			$args['orderby'] = 'id';
			$args['order']   = 'DESC';
		}

		$result = Loopkit_Module_CMS::get_items( $collection_id, $args );
		$items  = isset( $result['items'] ) ? $result['items'] : array();

		// Optional "field contains" filter, applied after the query because the
		// values live inside the row's JSON rather than their own columns.
		$filter_field = (string) ( $settings['cl_filter_field'] ?? '' );
		$filter_value = trim( (string) ( $settings['cl_filter_value'] ?? '' ) );
		if ( '' !== $filter_field && '' !== $filter_value ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filter_value ) : strtolower( $filter_value );
			$items  = array_values( array_filter( $items, function ( $row ) use ( $filter_field, $needle ) {
				$data = is_array( $row->data ) ? $row->data : array();
				$hay  = $this->value( $data, $filter_field );
				$hay  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
				return '' !== $hay && false !== strpos( $hay, $needle );
			} ) );
		}

		if ( ! $items ) {
			$empty = (string) ( $settings['cl_empty_text'] ?? '' );
			if ( '' === trim( $empty ) ) {
				return '';
			}
			return '<div class="lk-cl-empty">' . esc_html( $empty ) . '</div>';
		}

		$layout     = 'list' === ( $settings['cl_layout'] ?? 'grid' ) ? 'list' : 'grid';
		$img_key    = (string) ( $settings['cl_image_field'] ?? '' );
		$media_key  = (string) ( $settings['cl_media_field'] ?? '' );
		$media_type = $this->field_type( $media_key );
		$media_ctrl = 'no' !== ( $settings['cl_media_controls'] ?? 'yes' );
		$title_key  = (string) ( $settings['cl_title_field'] ?? '' );
		$meta_key   = (string) ( $settings['cl_meta_field'] ?? '' );
		$text_key   = (string) ( $settings['cl_text_field'] ?? '' );
		$link_key   = (string) ( $settings['cl_link_field'] ?? '' );
		$link_text  = (string) ( $settings['cl_link_text'] ?? '' );
		$card_links = self::switcher_on( $settings, 'cl_card_links', 'no' );

		$allowed_tags = array( 'h2', 'h3', 'h4', 'h5', 'div', 'span' );
		$title_tag    = (string) ( $settings['cl_title_tag'] ?? 'h3' );
		if ( ! in_array( $title_tag, $allowed_tags, true ) ) {
			$title_tag = 'h3';
		}

		// Nothing bound yet: say so in the editor rather than drawing blank
		// cards that look like a bug.
		if ( $editor_mode && '' === $img_key && '' === $media_key && '' === $title_key && '' === $meta_key && '' === $text_key ) {
			return '<div class="lk-cl-notice">'
				. esc_html__( 'Choose which field goes where under Card, and the rows will appear.', 'loopkit' )
				. '</div>';
		}

		$out = '<div class="lk-cl lk-cl--' . esc_attr( $layout ) . '">';

		foreach ( $items as $row ) {
			$data = is_array( $row->data ) ? $row->data : array();

			// Put the row in context so dynamic tags inside this card resolve
			// against it, then clear it again after the card is built.
			Loopkit_Module_CMS::set_context( $row, $collection );

			$img   = $this->value( $data, $img_key );
			$media = $media_key ? $this->value( $data, $media_key ) : '';
			$title = $this->value( $data, $title_key );
			$meta  = $this->value( $data, $meta_key );
			$text  = $this->value( $data, $text_key );
			$link  = $link_key ? esc_url( $this->value( $data, $link_key ) ) : '';

			$open  = ( $card_links && $link ) ? '<a class="lk-cl-card is-linked" href="' . $link . '">' : '<div class="lk-cl-card">';
			$close = ( $card_links && $link ) ? '</a>' : '</div>';

			$out .= $open;

			if ( $img ) {
				$out .= '<div class="lk-cl-media"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></div>';
			}

			if ( '' !== $media ) {
				$ctrl = $media_ctrl ? ' controls' : '';
				if ( 'audio' === $media_type ) {
					// No poster, no preload: a grid of ten cards should not pull
					// ten audio files before anyone has pressed play.
					$out .= '<div class="lk-cl-audio"><audio src="' . esc_url( $media ) . '"' . $ctrl . ' preload="none"></audio></div>';
				} else {
					$poster = $img ? ' poster="' . esc_url( $img ) . '"' : '';
					$out .= '<div class="lk-cl-video"><video src="' . esc_url( $media ) . '"' . $ctrl . $poster . ' preload="none" playsinline></video></div>';
				}
			}

			$out .= '<div class="lk-cl-body">';
			if ( '' !== $meta ) {
				$out .= '<div class="lk-cl-meta">' . esc_html( $meta ) . '</div>';
			}
			if ( '' !== $title ) {
				$out .= '<' . $title_tag . ' class="lk-cl-title">' . esc_html( $title ) . '</' . $title_tag . '>';
			}
			if ( '' !== $text ) {
				$out .= '<div class="lk-cl-text">' . wp_kses_post( wpautop( $text ) ) . '</div>';
			}
			if ( ! $card_links && $link && '' !== trim( $link_text ) ) {
				$out .= '<a class="lk-cl-link" href="' . $link . '">' . esc_html( $link_text ) . '</a>';
			}
			$out .= '</div>';

			$out .= $close;

			Loopkit_Module_CMS::set_context( null, null );
		}

		$out .= '</div>';
		return $out;
	}
}

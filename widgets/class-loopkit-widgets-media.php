<?php
/**
 * Loopkit widgets, Part 10: Image Carousel, Image Gallery, Google Maps, Contact Form,
 * Tooltip, Table of Contents.
 *
 * Rendered server-side in the editor canvas too (POST loopkit/v1/render-widget), so canvas
 * and front end markup are identical. Behaviour lives in assets/js/loopkit-widgets.js.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Image URL from a media control value.
 *
 * @param mixed  $media Media value ({id,url} or URL).
 * @param string $size  Image size.
 * @return array { url, alt, full }
 */
function loopkit_media_image( $media, $size = 'large' ) {
    $url = is_array( $media ) ? ( $media['url'] ?? '' ) : (string) $media;
    $id  = is_array( $media ) ? absint( $media['id'] ?? 0 ) : 0;
    $alt = '';
    $full = $url;
    if ( $id ) {
        $src  = wp_get_attachment_image_src( $id, $size );
        $url  = $src ? $src[0] : $url;
        $f    = wp_get_attachment_image_src( $id, 'full' );
        $full = $f ? $f[0] : $url;
        $alt  = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
    }
    if ( '' === $url ) {
        $url  = LOOPKIT_PLUGIN_URL . 'assets/images/placeholder.svg';
        $full = $url;
    }
    return array( 'url' => $url, 'alt' => $alt, 'full' => $full );
}

/**
 * Image Carousel / Slider.
 */
class Loopkit_Widget_Image_Carousel extends Loopkit_Interactive_Widget {

    const ARROW_PREV = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M41.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.3 256 246.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160z"/></svg>';
    const ARROW_NEXT = '<svg aria-hidden="true" viewBox="0 0 320 512" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M278.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-160 160c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L210.7 256 73.4 118.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l160 160z"/></svg>';

    public function get_name() { return 'image-carousel'; }
    public function get_title() { return esc_html__( 'Image Carousel', 'loopkit' ); }
    public function get_icon() { return 'dashicons-slides'; }
    public function get_keywords() { return array( 'carousel', 'slider', 'slides', 'images', 'gallery' ); }

    protected function register_controls() {
        $slides = array();
        foreach ( array( 1, 2, 3 ) as $i ) {
            /* translators: %d: slide number */
            $slides[] = array( '_id' => 'slide' . $i, 'image' => array( 'id' => 0, 'url' => '' ), 'title' => sprintf( esc_html__( 'Slide %d', 'loopkit' ), $i ), 'content' => '', 'link' => array( 'url' => '' ) );
        }
        return array(
            'content' => array(
                array(
                    'id'       => 'section_slides',
                    'title'    => __( 'Slides', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'slides',
                            'label'       => __( 'Slides', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'title',
                            'default'     => $slides,
                            'fields'      => array(
                                array( 'name' => 'image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'media' ),
                                array( 'name' => 'title', 'label' => __( 'Caption Title', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                                array( 'name' => 'content', 'label' => __( 'Caption Text', 'loopkit' ), 'type' => 'textarea', 'default' => '' ),
                                array( 'name' => 'link', 'label' => __( 'Link', 'loopkit' ), 'type' => 'url', 'default' => array( 'url' => '' ) ),
                            ),
                        ),
                        array( 'name' => 'image_size', 'label' => __( 'Image Size', 'loopkit' ), 'type' => 'select', 'default' => 'large', 'options' => array( 'medium' => esc_html__( 'Medium', 'loopkit' ), 'medium_large' => esc_html__( 'Medium Large', 'loopkit' ), 'large' => esc_html__( 'Large', 'loopkit' ), 'full' => esc_html__( 'Full', 'loopkit' ) ) ),
                        array( 'name' => 'slides_to_show', 'label' => __( 'Slides to Show', 'loopkit' ), 'type' => 'number', 'responsive' => true, 'default' => 1, 'min' => 1, 'max' => 6 ),
                        array( 'name' => 'arrows', 'label' => __( 'Arrows', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'dots', 'label' => __( 'Dots', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'autoplay', 'label' => __( 'Autoplay', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'autoplay_speed', 'label' => __( 'Autoplay Speed (ms)', 'loopkit' ), 'type' => 'number', 'default' => 5000, 'min' => 1000, 'max' => 20000, 'condition' => array( 'autoplay' => 'yes' ) ),
                        array( 'name' => 'loop', 'label' => __( 'Infinite Loop', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_carousel', esc_html__( 'Carousel', 'loopkit' ), array(
                    self::slider( 'height', esc_html__( 'Image Height', 'loopkit' ), 100, 900 ),
                    self::slider( 'gap', esc_html__( 'Space Between Slides', 'loopkit' ), 0, 60 ),
                    self::slider( 'radius', esc_html__( 'Image Radius', 'loopkit' ), 0, 40, array( 'px' ), false ),
                    self::c( 'arrow_color', esc_html__( 'Arrow Color', 'loopkit' ) ),
                    self::c( 'arrow_bg', esc_html__( 'Arrow Background', 'loopkit' ) ),
                    self::c( 'dot_color', esc_html__( 'Dot Color', 'loopkit' ) ),
                    self::c( 'dot_active_color', esc_html__( 'Active Dot Color', 'loopkit' ) ),
                    self::c( 'caption_color', esc_html__( 'Caption Color', 'loopkit' ) ),
                    self::c( 'caption_bg', esc_html__( 'Caption Background', 'loopkit' ) ),
                    self::t( 'caption_typography', esc_html__( 'Caption Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $slides = $settings['slides'] ?? $this->get_default_settings()['slides'];
        if ( ! is_array( $slides ) || ! $slides ) {
            return '';
        }
        $slides = array_values( $slides );
        $n      = count( $slides );
        $size   = $settings['image_size'] ?? 'large';

        $html  = '<div class="lk-carousel" data-lk-carousel data-autoplay="' . ( $this->switcher_on( $settings, 'autoplay', true ) ? 'yes' : 'no' ) . '" data-speed="' . esc_attr( max( 1000, (int) ( $settings['autoplay_speed'] ?? 5000 ) ) ) . '" data-loop="' . ( $this->switcher_on( $settings, 'loop', true ) ? 'yes' : 'no' ) . '" aria-roledescription="carousel">';
        $html .= '<div class="lk-carousel-track">';
        foreach ( $slides as $i => $slide ) {
            $img   = loopkit_media_image( $slide['image'] ?? array(), $size );
            $inner = '<img class="lk-carousel-image" src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ) . '"' . ( $i > 0 ? ' loading="lazy"' : '' ) . '>';
            $title = trim( (string) ( $slide['title'] ?? '' ) );
            $text  = trim( (string) ( $slide['content'] ?? '' ) );
            if ( '' !== $title || '' !== $text ) {
                $inner .= '<div class="lk-carousel-caption">' . ( '' !== $title ? '<div class="lk-carousel-caption-title">' . self::rich( $title, false ) . '</div>' : '' ) . ( '' !== $text ? '<div class="lk-carousel-caption-text">' . self::rich( $text, false ) . '</div>' : '' ) . '</div>';
            }
            $url = is_array( $slide['link'] ?? null ) ? ( $slide['link']['url'] ?? '' ) : '';
            if ( '' !== $url ) {
                $inner = '<a href="' . esc_url( $url ) . '"' . ( ! empty( $slide['link']['is_external'] ) ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a>';
            }
            /* translators: 1: slide number, 2: total */
            $html .= '<div class="lk-carousel-slide" role="group" aria-roledescription="slide" aria-label="' . esc_attr( sprintf( __( '%1$d of %2$d', 'loopkit' ), $i + 1, $n ) ) . '">' . $inner . '</div>';
        }
        $html .= '</div>';
        if ( $this->switcher_on( $settings, 'arrows', true ) && $n > 1 ) {
            $html .= '<button type="button" class="lk-carousel-arrow lk-carousel-prev" aria-label="' . esc_attr__( 'Previous slide', 'loopkit' ) . '">' . self::ARROW_PREV . '</button>';
            $html .= '<button type="button" class="lk-carousel-arrow lk-carousel-next" aria-label="' . esc_attr__( 'Next slide', 'loopkit' ) . '">' . self::ARROW_NEXT . '</button>';
        }
        if ( $this->switcher_on( $settings, 'dots', true ) && $n > 1 ) {
            $html .= '<div class="lk-carousel-dots">';
            for ( $i = 0; $i < $n; $i++ ) {
                /* translators: %d: slide number */
                $html .= '<button type="button" class="lk-carousel-dot' . ( 0 === $i ? ' lk-active' : '' ) . '" aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'loopkit' ), $i + 1 ) ) . '"></button>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }
}

/**
 * Image Gallery.
 */
class Loopkit_Widget_Image_Gallery extends Loopkit_Interactive_Widget {

    public function get_name() { return 'image-gallery'; }
    public function get_title() { return esc_html__( 'Image Gallery', 'loopkit' ); }
    public function get_icon() { return 'dashicons-format-gallery'; }
    public function get_keywords() { return array( 'gallery', 'images', 'grid', 'photos', 'lightbox' ); }

    protected function register_controls() {
        $items = array();
        foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
            $items[] = array( '_id' => 'img' . $i, 'image' => array( 'id' => 0, 'url' => '' ), 'title' => '' );
        }
        return array(
            'content' => array(
                array(
                    'id'       => 'section_gallery',
                    'title'    => __( 'Gallery', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'images',
                            'label'   => __( 'Images', 'loopkit' ),
                            'type'    => 'repeater',
                            'default' => $items,
                            'fields'  => array(
                                array( 'name' => 'image', 'label' => __( 'Image', 'loopkit' ), 'type' => 'media' ),
                                array( 'name' => 'title', 'label' => __( 'Caption', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                            ),
                        ),
                        array( 'name' => 'image_size', 'label' => __( 'Image Size', 'loopkit' ), 'type' => 'select', 'default' => 'medium_large', 'options' => array( 'thumbnail' => esc_html__( 'Thumbnail', 'loopkit' ), 'medium' => esc_html__( 'Medium', 'loopkit' ), 'medium_large' => esc_html__( 'Medium Large', 'loopkit' ), 'large' => esc_html__( 'Large', 'loopkit' ), 'full' => esc_html__( 'Full', 'loopkit' ) ) ),
                        array( 'name' => 'columns', 'label' => __( 'Columns', 'loopkit' ), 'type' => 'number', 'responsive' => true, 'default' => 3, 'min' => 1, 'max' => 8 ),
                        array( 'name' => 'lightbox', 'label' => __( 'Open in Lightbox', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'show_caption', 'label' => __( 'Captions', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_gallery', esc_html__( 'Images', 'loopkit' ), array(
                    self::slider( 'gap', esc_html__( 'Gap', 'loopkit' ), 0, 60 ),
                    self::slider( 'height', esc_html__( 'Image Height', 'loopkit' ), 50, 600 ),
                    self::slider( 'radius', esc_html__( 'Radius', 'loopkit' ), 0, 40, array( 'px' ), false ),
                    self::c( 'caption_color', esc_html__( 'Caption Color', 'loopkit' ) ),
                    self::t( 'caption_typography', esc_html__( 'Caption Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $images = $settings['images'] ?? $this->get_default_settings()['images'];
        if ( ! is_array( $images ) || ! $images ) {
            return '';
        }
        $size     = $settings['image_size'] ?? 'medium_large';
        $lightbox = ( $this->switcher_on( $settings, 'lightbox', true ) );
        $captions = ( $this->switcher_on( $settings, 'show_caption', false ) );
        $html     = '<div class="lk-gallery">';
        foreach ( array_values( $images ) as $item ) {
            $img   = loopkit_media_image( $item['image'] ?? array(), $size );
            $title = trim( (string) ( $item['title'] ?? '' ) );
            $pic   = '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( '' !== $img['alt'] ? $img['alt'] : $title ) . '" loading="lazy">';
            if ( $lightbox ) {
                $pic = '<a href="' . esc_url( $img['full'] ) . '" data-loopkit-lightbox="gallery">' . $pic . '</a>';
            }
            $html .= '<figure class="lk-gallery-item">' . $pic . ( $captions && '' !== $title ? '<figcaption class="lk-gallery-caption">' . esc_html( $title ) . '</figcaption>' : '' ) . '</figure>';
        }
        return $html . '</div>';
    }
}

/**
 * Google Maps (embed, no API key).
 */
class Loopkit_Widget_Google_Maps extends Loopkit_Interactive_Widget {

    public function get_name() { return 'google-maps'; }
    public function get_title() { return esc_html__( 'Google Maps', 'loopkit' ); }
    public function get_icon() { return 'dashicons-location-alt'; }
    public function get_keywords() { return array( 'map', 'google', 'location', 'address', 'embed' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_map',
                    'title'    => __( 'Map', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'address', 'label' => __( 'Location', 'loopkit' ), 'type' => 'text', 'default' => 'London Eye, London, United Kingdom' ),
                        array( 'name' => 'zoom', 'label' => __( 'Zoom', 'loopkit' ), 'type' => 'number', 'default' => 14, 'min' => 1, 'max' => 20 ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_map', esc_html__( 'Map', 'loopkit' ), array(
                    self::slider( 'height', esc_html__( 'Height', 'loopkit' ), 100, 1000 ),
                    self::slider( 'radius', esc_html__( 'Radius', 'loopkit' ), 0, 40, array( 'px' ), false ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $address = trim( (string) ( $settings['address'] ?? 'London Eye, London, United Kingdom' ) );
        if ( '' === $address ) {
            return $editor_mode ? '<div class="lk-map lk-map-empty">' . esc_html__( 'Enter a location to show the map.', 'loopkit' ) . '</div>' : '';
        }
        $zoom = max( 1, min( 20, (int) ( $settings['zoom'] ?? 14 ) ) );
        $src  = 'https://maps.google.com/maps?q=' . rawurlencode( $address ) . '&t=m&z=' . $zoom . '&output=embed&iwloc=near';
        return '<div class="lk-map"><iframe src="' . esc_url( $src ) . '" title="' . esc_attr( $address ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
    }
}

/**
 * Contact Form. Submissions go to admin-ajax (loopkit_contact_form); the recipient and
 * field list are read from the saved page data on the server, never from the browser.
 */
class Loopkit_Widget_Contact_Form extends Loopkit_Interactive_Widget {

    public function get_name() { return 'contact-form'; }
    public function get_title() { return esc_html__( 'Contact Form', 'loopkit' ); }
    public function get_icon() { return 'dashicons-feedback'; }
    public function get_keywords() { return array( 'form', 'contact', 'email', 'message', 'input' ); }

    /**
     * Default fields.
     *
     * @return array
     */
    public static function default_fields() {
        return array(
            array( '_id' => 'name', 'label' => __( 'Name', 'loopkit' ), 'field_type' => 'text', 'placeholder' => '', 'required' => 'yes', 'width' => '100' ),
            array( '_id' => 'email', 'label' => __( 'Email', 'loopkit' ), 'field_type' => 'email', 'placeholder' => '', 'required' => 'yes', 'width' => '100' ),
            array( '_id' => 'message', 'label' => __( 'Message', 'loopkit' ), 'field_type' => 'textarea', 'placeholder' => '', 'required' => 'yes', 'width' => '100' ),
        );
    }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_form_fields',
                    'title'    => __( 'Form Fields', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'        => 'fields',
                            'label'       => __( 'Fields', 'loopkit' ),
                            'type'        => 'repeater',
                            'title_field' => 'label',
                            'default'     => self::default_fields(),
                            'fields'      => array(
                                array( 'name' => 'label', 'label' => __( 'Label', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Field', 'loopkit' ) ),
                                array( 'name' => 'field_type', 'label' => __( 'Type', 'loopkit' ), 'type' => 'select', 'default' => 'text', 'options' => array( 'text' => esc_html__( 'Text', 'loopkit' ), 'email' => esc_html__( 'Email', 'loopkit' ), 'tel' => esc_html__( 'Phone', 'loopkit' ), 'url' => esc_html__( 'URL', 'loopkit' ), 'number' => esc_html__( 'Number', 'loopkit' ), 'textarea' => esc_html__( 'Textarea', 'loopkit' ) ) ),
                                array( 'name' => 'placeholder', 'label' => __( 'Placeholder', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                                array( 'name' => 'required', 'label' => __( 'Required', 'loopkit' ), 'type' => 'switcher', 'default' => 'no' ),
                                array( 'name' => 'width', 'label' => __( 'Column Width', 'loopkit' ), 'type' => 'select', 'default' => '100', 'options' => array( '100' => '100%', '50' => '50%', '33' => '33%' ) ),
                            ),
                        ),
                        array( 'name' => 'show_labels', 'label' => __( 'Show Labels', 'loopkit' ), 'type' => 'switcher', 'default' => 'yes' ),
                        array( 'name' => 'button_text', 'label' => __( 'Button Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Send', 'loopkit' ) ),
                    ),
                ),
                array(
                    'id'       => 'section_form_email',
                    'title'    => __( 'Email', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'email_to', 'label' => __( 'Send To', 'loopkit' ), 'type' => 'text', 'default' => '', 'description' => __( 'Leave empty to use the site admin email. Separate several addresses with commas.', 'loopkit' ) ),
                        array( 'name' => 'email_subject', 'label' => __( 'Subject', 'loopkit' ), 'type' => 'text', 'default' => '' ),
                        array( 'name' => 'success_message', 'label' => __( 'Success Message', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Thanks! Your message has been sent.', 'loopkit' ) ),
                        array( 'name' => 'error_message', 'label' => __( 'Error Message', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Sorry, your message could not be sent. Please try again.', 'loopkit' ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_form', esc_html__( 'Form', 'loopkit' ), array(
                    self::slider( 'row_gap', esc_html__( 'Rows Gap', 'loopkit' ), 0, 60 ),
                    self::c( 'label_color', esc_html__( 'Label Color', 'loopkit' ) ),
                    self::t( 'label_typography', esc_html__( 'Label Typography', 'loopkit' ) ),
                    self::c( 'field_text_color', esc_html__( 'Field Text Color', 'loopkit' ) ),
                    self::c( 'field_bg', esc_html__( 'Field Background', 'loopkit' ) ),
                    self::c( 'field_border_color', esc_html__( 'Field Border Color', 'loopkit' ) ),
                    self::slider( 'field_radius', esc_html__( 'Field Radius', 'loopkit' ), 0, 30, array( 'px' ), false ),
                    self::c( 'button_color', esc_html__( 'Button Text Color', 'loopkit' ) ),
                    self::c( 'button_bg', esc_html__( 'Button Background', 'loopkit' ) ),
                    self::c( 'button_hover_bg', esc_html__( 'Button Hover Background', 'loopkit' ) ),
                    self::t( 'button_typography', esc_html__( 'Button Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $fields = $settings['fields'] ?? self::default_fields();
        if ( ! is_array( $fields ) || ! $fields ) {
            return '';
        }
        $labels = ( $this->switcher_on( $settings, 'show_labels', true ) );
        $uid    = sanitize_html_class( $el['id'] ?? wp_rand() );
        $html   = '<form class="lk-form" data-lk-form data-element="' . esc_attr( $el['id'] ?? '' ) . '" novalidate>';
        $html  .= '<div class="lk-form-fields">';
        foreach ( array_values( $fields ) as $i => $f ) {
            $type  = in_array( $f['field_type'] ?? 'text', array( 'text', 'email', 'tel', 'url', 'number', 'textarea' ), true ) ? $f['field_type'] : 'text';
            $width = in_array( (string) ( $f['width'] ?? '100' ), array( '100', '50', '33' ), true ) ? (string) $f['width'] : '100';
            $req   = ( 'yes' === ( $f['required'] ?? 'no' ) );
            $id    = 'lk-form-' . $uid . '-' . $i;
            $label = (string) ( $f['label'] ?? '' );
            $ph    = (string) ( $f['placeholder'] ?? '' );
            $attrs = ' id="' . esc_attr( $id ) . '" name="field_' . $i . '"' . ( '' !== $ph ? ' placeholder="' . esc_attr( $ph ) . '"' : '' ) . ( $req ? ' required aria-required="true"' : '' ) . ( ! $labels && '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' );
            $html .= '<div class="lk-form-field lk-form-col-' . $width . '">';
            if ( $labels && '' !== $label ) {
                $html .= '<label class="lk-form-label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $req ? '<span class="lk-form-required" aria-hidden="true">*</span>' : '' ) . '</label>';
            }
            $html .= ( 'textarea' === $type ) ? '<textarea class="lk-form-input" rows="5"' . $attrs . '></textarea>' : '<input class="lk-form-input" type="' . $type . '"' . $attrs . '>';
            $html .= '</div>';
        }
        // Honeypot: hidden from people, filled by bots.
        $html .= '<div class="lk-form-hp" aria-hidden="true"><label>Website<input type="text" name="lk_hp" tabindex="-1" autocomplete="off"></label></div>';
        $html .= '</div>';
        $btn   = (string) ( $settings['button_text'] ?? __( 'Send', 'loopkit' ) );
        $html .= '<div class="lk-form-submit-wrap"><button type="submit" class="lk-form-submit">' . esc_html( '' !== $btn ? $btn : __( 'Send', 'loopkit' ) ) . '</button></div>';
        $html .= '<div class="lk-form-message" role="status" aria-live="polite"></div>';
        return $html . '</form>';
    }

    /**
     * Find an element by ID in a layout (nested).
     *
     * @param array  $elements Elements.
     * @param string $id       Element ID.
     * @return array|null
     */
    public static function find_element( $elements, $id ) {
        foreach ( (array) $elements as $el ) {
            if ( ! is_array( $el ) ) {
                continue;
            }
            if ( ( $el['id'] ?? '' ) === $id && 'contact-form' === ( $el['widgetType'] ?? '' ) ) {
                return $el;
            }
            if ( ! empty( $el['elements'] ) ) {
                $found = self::find_element( $el['elements'], $id );
                if ( $found ) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * AJAX handler (logged in and logged out).
     */
    public static function handle_submit() {
        check_ajax_referer( 'loopkit_form', 'nonce' );

        if ( ! empty( $_POST['lk_hp'] ) ) { // Honeypot filled: pretend success, send nothing.
            wp_send_json_success( array( 'message' => '' ) );
        }

        // Basic rate limit: 5 submissions per IP per 10 minutes.
        $ip_key = 'lk_form_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
        $count  = (int) get_transient( $ip_key );
        if ( $count >= 5 ) {
            wp_send_json_error( array( 'message' => __( 'Too many messages. Please try again later.', 'loopkit' ) ), 429 );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $el_id   = sanitize_text_field( wp_unslash( $_POST['element_id'] ?? '' ) );
        $el      = null;
        if ( $post_id && $el_id && class_exists( 'Loopkit_Frontend' ) && in_array( get_post_status( $post_id ), array( 'publish', 'private' ), true ) ) {
            $el = self::find_element( Loopkit_Frontend::decode_elements( get_post_meta( $post_id, '_loopkit_data', true ) ), $el_id );
        }
        if ( ! $el ) {
            wp_send_json_error( array( 'message' => __( 'This form is no longer available.', 'loopkit' ) ), 400 );
        }

        $settings = $el['settings'] ?? array();
        $fields   = $settings['fields'] ?? self::default_fields();
        $error    = ( isset( $settings['error_message'] ) && '' !== $settings['error_message'] ) ? $settings['error_message'] : __( 'Sorry, your message could not be sent. Please try again.', 'loopkit' );

        $lines    = array();
        $reply_to = '';
        foreach ( array_values( (array) $fields ) as $i => $f ) {
            $type  = $f['field_type'] ?? 'text';
            $raw   = wp_unslash( $_POST[ 'field_' . $i ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $value = ( 'textarea' === $type ) ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );
            $label = (string) ( $f['label'] ?? ( 'Field ' . ( $i + 1 ) ) );
            if ( 'yes' === ( $f['required'] ?? 'no' ) && '' === trim( $value ) ) {
                /* translators: %s: field label */
                wp_send_json_error( array( 'message' => sprintf( __( '%s is required.', 'loopkit' ), $label ) ), 422 );
            }
            if ( 'email' === $type && '' !== $value ) {
                if ( ! is_email( $value ) ) {
                    /* translators: %s: field label */
                    wp_send_json_error( array( 'message' => sprintf( __( '%s must be a valid email address.', 'loopkit' ), $label ) ), 422 );
                }
                if ( '' === $reply_to ) {
                    $reply_to = $value;
                }
            }
            $lines[] = $label . ":\n" . $value;
        }

        $to = array();
        foreach ( explode( ',', (string) ( $settings['email_to'] ?? '' ) ) as $addr ) {
            $addr = sanitize_email( trim( $addr ) );
            if ( is_email( $addr ) ) {
                $to[] = $addr;
            }
        }
        if ( ! $to ) {
            $to = array( get_option( 'admin_email' ) );
        }
        /* translators: %s: site name */
        $subject = ( isset( $settings['email_subject'] ) && '' !== trim( $settings['email_subject'] ) ) ? $settings['email_subject'] : sprintf( __( 'New message from %s', 'loopkit' ), get_bloginfo( 'name' ) );
        $body    = implode( "\n\n", $lines ) . "\n\n---\n" . get_permalink( $post_id );
        $headers = $reply_to ? array( 'Reply-To: ' . $reply_to ) : array();

        set_transient( $ip_key, $count + 1, 10 * MINUTE_IN_SECONDS );
        $sent = wp_mail( $to, wp_specialchars_decode( $subject ), $body, $headers );
        do_action( 'loopkit_form_submitted', $post_id, $el_id, $lines, $sent );

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => $error ), 500 );
        }
        $ok = ( isset( $settings['success_message'] ) && '' !== $settings['success_message'] ) ? $settings['success_message'] : __( 'Thanks! Your message has been sent.', 'loopkit' );
        wp_send_json_success( array( 'message' => $ok ) );
    }
}
add_action( 'wp_ajax_loopkit_contact_form', array( 'Loopkit_Widget_Contact_Form', 'handle_submit' ) );
add_action( 'wp_ajax_nopriv_loopkit_contact_form', array( 'Loopkit_Widget_Contact_Form', 'handle_submit' ) );

/**
 * Tooltip.
 */
class Loopkit_Widget_Tooltip extends Loopkit_Interactive_Widget {

    public function get_name() { return 'tooltip'; }
    public function get_title() { return esc_html__( 'Tooltip', 'loopkit' ); }
    public function get_icon() { return 'dashicons-testimonial'; }
    public function get_keywords() { return array( 'tooltip', 'hint', 'info', 'hover' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_tooltip',
                    'title'    => __( 'Tooltip', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'trigger_text', 'label' => __( 'Text', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Hover me', 'loopkit' ) ),
                        array( 'name' => 'content', 'label' => __( 'Tooltip Content', 'loopkit' ), 'type' => 'textarea', 'default' => esc_html__( 'This is a tooltip.', 'loopkit' ) ),
                        array( 'name' => 'tooltip_position', 'label' => __( 'Position', 'loopkit' ), 'type' => 'select', 'default' => 'top', 'options' => array( 'top' => esc_html__( 'Top', 'loopkit' ), 'bottom' => esc_html__( 'Bottom', 'loopkit' ), 'left' => esc_html__( 'Left', 'loopkit' ), 'right' => esc_html__( 'Right', 'loopkit' ) ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_tooltip', esc_html__( 'Tooltip', 'loopkit' ), array(
                    self::align(),
                    self::c( 'trigger_color', esc_html__( 'Text Color', 'loopkit' ) ),
                    self::t( 'trigger_typography', esc_html__( 'Text Typography', 'loopkit' ) ),
                    self::c( 'tooltip_color', esc_html__( 'Tooltip Text Color', 'loopkit' ) ),
                    self::c( 'tooltip_bg', esc_html__( 'Tooltip Background', 'loopkit' ) ),
                    self::t( 'tooltip_typography', esc_html__( 'Tooltip Typography', 'loopkit' ) ),
                    self::slider( 'tooltip_width', esc_html__( 'Tooltip Width', 'loopkit' ), 80, 500, array( 'px' ), false ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $pos = in_array( ( $settings['tooltip_position'] ?? $settings['position'] ?? null ) ?? 'top', array( 'top', 'bottom', 'left', 'right' ), true ) ? ( $settings['tooltip_position'] ?? $settings['position'] ?? null ) : 'top';
        $tid = 'lk-tooltip-' . sanitize_html_class( $el['id'] ?? wp_rand() );
        $txt = (string) ( $settings['trigger_text'] ?? __( 'Hover me', 'loopkit' ) );
        $tip = (string) ( $settings['content'] ?? __( 'This is a tooltip.', 'loopkit' ) );
        return '<div class="lk-tooltip-wrapper"><span class="lk-tooltip-wrap lk-tooltip-' . $pos . '"><button type="button" class="lk-tooltip-trigger" aria-describedby="' . esc_attr( $tid ) . '">' . esc_html( $txt ) . '</button><span class="lk-tooltip" role="tooltip" id="' . esc_attr( $tid ) . '">' . self::rich( $tip, false ) . '</span></span></div>';
    }
}

/**
 * Table of Contents (built in the browser from the page's headings).
 */
class Loopkit_Widget_Table_Of_Contents extends Loopkit_Interactive_Widget {

    public function get_name() { return 'table-of-contents'; }
    public function get_title() { return esc_html__( 'Table of Contents', 'loopkit' ); }
    public function get_icon() { return 'dashicons-editor-ol'; }
    public function get_keywords() { return array( 'table of contents', 'toc', 'headings', 'index', 'navigation' ); }

    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_toc',
                    'title'    => __( 'Table of Contents', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array( 'name' => 'title', 'label' => __( 'Title', 'loopkit' ), 'type' => 'text', 'default' => esc_html__( 'Table of Contents', 'loopkit' ) ),
                        array( 'name' => 'headings', 'label' => __( 'Include Headings', 'loopkit' ), 'type' => 'select', 'default' => 'h2,h3', 'options' => array( 'h2' => 'H2', 'h2,h3' => 'H2 – H3', 'h2,h3,h4' => 'H2 – H4', 'h1,h2,h3,h4,h5,h6' => 'H1 – H6' ) ),
                        array( 'name' => 'marker', 'label' => __( 'Marker', 'loopkit' ), 'type' => 'select', 'default' => 'numbers', 'options' => array( 'numbers' => esc_html__( 'Numbers', 'loopkit' ), 'bullets' => esc_html__( 'Bullets', 'loopkit' ), 'none' => esc_html__( 'None', 'loopkit' ) ) ),
                    ),
                ),
            ),
            'style'   => array(
                self::style_section( 'section_style_toc', esc_html__( 'Box', 'loopkit' ), array(
                    self::c( 'bg_color', esc_html__( 'Background', 'loopkit' ) ),
                    self::c( 'border_color', esc_html__( 'Border Color', 'loopkit' ) ),
                    self::c( 'title_color', esc_html__( 'Title Color', 'loopkit' ) ),
                    self::t( 'title_typography', esc_html__( 'Title Typography', 'loopkit' ) ),
                    self::c( 'link_color', esc_html__( 'Link Color', 'loopkit' ) ),
                    self::c( 'link_hover_color', esc_html__( 'Link Hover Color', 'loopkit' ) ),
                    self::t( 'link_typography', esc_html__( 'Link Typography', 'loopkit' ) ),
                ) ),
            ),
        );
    }

    public function render( $settings, $el, $editor_mode = false ) {
        $tags   = (string) ( $settings['headings'] ?? 'h2,h3' );
        $tags   = in_array( $tags, array( 'h2', 'h2,h3', 'h2,h3,h4', 'h1,h2,h3,h4,h5,h6' ), true ) ? $tags : 'h2,h3';
        $marker = (string) ( $settings['marker'] ?? 'numbers' );
        $marker = in_array( $marker, array( 'numbers', 'bullets', 'none' ), true ) ? $marker : 'numbers';
        $list   = ( 'numbers' === $marker ) ? 'ol' : 'ul';
        $title  = (string) ( $settings['title'] ?? __( 'Table of Contents', 'loopkit' ) );
        $html   = '<nav class="lk-toc lk-toc-marker-' . $marker . '" data-lk-toc data-tags="' . esc_attr( $tags ) . '" data-list="' . $list . '" aria-label="' . esc_attr( '' !== $title ? $title : __( 'Table of Contents', 'loopkit' ) ) . '">';
        if ( '' !== trim( $title ) ) {
            $html .= '<div class="lk-toc-title">' . esc_html( $title ) . '</div>';
        }
        $html .= '<' . $list . ' class="lk-toc-list">';
        if ( $editor_mode ) {
            // Editor preview: the list is built from the page's headings on the live page.
            foreach ( array( __( 'First heading', 'loopkit' ), __( 'Second heading', 'loopkit' ), __( 'Third heading', 'loopkit' ) ) as $sample ) {
                $html .= '<li class="lk-toc-item"><a href="#">' . esc_html( $sample ) . '</a></li>';
            }
        }
        return $html . '</' . $list . '></nav>';
    }
}

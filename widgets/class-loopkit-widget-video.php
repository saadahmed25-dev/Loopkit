<?php
/**
 * Loopkit Video Widget.
 *
 * @package Loopkit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loopkit_Widget_Video extends Loopkit_Widget_Base {

    /**
     * Get widget name.
     *
     * @return string
     */
    public function get_name() {
        return 'video';
    }

    /**
     * Get widget title.
     *
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Video', 'loopkit' );
    }

    /**
     * Get widget icon.
     *
     * @return string
     */
    public function get_icon() {
        return 'dashicons-video-alt3';
    }

    /**
     * Get widget category.
     *
     * @return string
     */
    public function get_category() {
        return 'basic';
    }

    /**
     * Get search keywords.
     *
     * @return array
     */
    public function get_keywords() {
        return array( 'video', 'youtube', 'vimeo', 'embed', 'player', 'media', 'movie', 'clip' );
    }

    /**
     * Register controls.
     *
     * @return array
     */
    protected function register_controls() {
        return array(
            'content' => array(
                array(
                    'id'       => 'section_video',
                    'title'    => __( 'Video', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'video_type',
                            'label'   => __( 'Source', 'loopkit' ),
                            'type'    => 'select',
                            'default' => 'youtube',
                            'options' => array(
                                'youtube'     => esc_html__( 'YouTube', 'loopkit' ),
                                'vimeo'       => esc_html__( 'Vimeo', 'loopkit' ),
                                'dailymotion' => esc_html__( 'Dailymotion', 'loopkit' ),
                                'hosted'      => esc_html__( 'Self Hosted', 'loopkit' ),
                            ),
                        ),
                        array(
                            'name'      => 'youtube_url',
                            'label'     => __( 'Link', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => '',
                            'condition' => array(
                                'video_type' => 'youtube',
                            ),
                        ),
                        array(
                            'name'      => 'vimeo_url',
                            'label'     => __( 'Link', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => '',
                            'condition' => array(
                                'video_type' => 'vimeo',
                            ),
                        ),
                        array(
                            'name'      => 'dailymotion_url',
                            'label'     => __( 'Link', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => '',
                            'condition' => array(
                                'video_type' => 'dailymotion',
                            ),
                        ),
                        array(
                            'name'      => 'hosted_url',
                            'label'     => __( 'Link', 'loopkit' ),
                            'type'      => 'text',
                            'default'   => '',
                            'condition' => array(
                                'video_type' => 'hosted',
                            ),
                        ),
                        array(
                            'name'    => 'start',
                            'label'   => __( 'Start Time', 'loopkit' ),
                            'type'    => 'number',
                            'default' => '',
                        ),
                        array(
                            'name'    => 'end',
                            'label'   => __( 'End Time', 'loopkit' ),
                            'type'    => 'number',
                            'default' => '',
                        ),
                        array(
                            'name'    => 'autoplay',
                            'label'   => __( 'Autoplay', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                        array(
                            'name'      => 'play_on_mobile',
                            'label'     => __( 'Play On Mobile', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'no',
                            'condition' => array(
                                'autoplay' => 'yes',
                            ),
                        ),
                        array(
                            'name'    => 'mute',
                            'label'   => __( 'Mute', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                        array(
                            'name'    => 'loop',
                            'label'   => __( 'Loop', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                        array(
                            'name'    => 'controls',
                            'label'   => __( 'Player Controls', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'yes',
                        ),
                        array(
                            'name'      => 'modest_branding',
                            'label'     => __( 'Modest Branding', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'no',
                            'condition' => array(
                                'video_type' => 'youtube',
                            ),
                        ),
                        array(
                            'name'      => 'privacy_mode',
                            'label'     => __( 'Privacy Mode', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'no',
                            'condition' => array(
                                'video_type' => 'youtube',
                            ),
                        ),
                        array(
                            'name'    => 'lazy_load',
                            'label'   => __( 'Lazy Load', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                    ),
                ),
                array(
                    'id'       => 'section_image_overlay',
                    'title'    => __( 'Image Overlay', 'loopkit' ),
                    'tab'      => 'content',
                    'controls' => array(
                        array(
                            'name'    => 'show_image_overlay',
                            'label'   => __( 'Image Overlay', 'loopkit' ),
                            'type'    => 'switcher',
                            'default' => 'no',
                        ),
                        array(
                            'name'      => 'image_overlay',
                            'label'     => __( 'Choose Image', 'loopkit' ),
                            'type'      => 'media',
                            'condition' => array(
                                'show_image_overlay' => 'yes',
                            ),
                        ),
                        array(
                            'name'      => 'image_overlay_size',
                            'label'     => __( 'Image Resolution', 'loopkit' ),
                            'type'      => 'image_size',
                            'default'   => 'large',
                            'condition' => array(
                                'show_image_overlay' => 'yes',
                            ),
                        ),
                        array(
                            'name'      => 'show_play_icon',
                            'label'     => __( 'Play Icon', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'yes',
                            'condition' => array(
                                'show_image_overlay' => 'yes',
                            ),
                        ),
                        array(
                            'name'      => 'play_icon',
                            'label'     => __( 'Icon', 'loopkit' ),
                            'type'      => 'icon',
                            'default'   => array(
                                'library' => 'fa-solid',
                                'value'   => 'play',
                            ),
                            'condition' => array(
                                'show_image_overlay' => 'yes',
                                'show_play_icon'     => 'yes',
                            ),
                        ),
                        array(
                            'name'      => 'lightbox',
                            'label'     => __( 'Lightbox', 'loopkit' ),
                            'type'      => 'switcher',
                            'default'   => 'no',
                            'condition' => array(
                                'show_image_overlay' => 'yes',
                            ),
                        ),
                    ),
                ),
            ),
            'style'   => array(
                array(
                    'id'       => 'section_style_video',
                    'title'    => __( 'Video', 'loopkit' ),
                    'tab'      => 'style',
                    'controls' => array(
                        array(
                            'name'    => 'aspect_ratio',
                            'label'   => __( 'Aspect Ratio', 'loopkit' ),
                            'type'    => 'select',
                            'default' => '169',
                            'options' => array(
                                '169' => '16:9',
                                '219' => '21:9',
                                '43'  => '4:3',
                                '32'  => '3:2',
                                '11'  => '1:1',
                                '916' => '9:16',
                            ),
                        ),
                        array(
                            'name'        => 'css_filters',
                            'label'       => __( 'CSS Filters', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'css_filters',
                        ),
                    ),
                ),
                array(
                    'id'        => 'section_style_play_icon',
                    'title'     => __( 'Play Icon', 'loopkit' ),
                    'tab'       => 'style',
                    'condition' => array(
                        'show_image_overlay' => 'yes',
                        'show_play_icon'     => 'yes',
                    ),
                    'controls'  => array(
                        array(
                            'name'    => 'play_icon_color',
                            'label'   => __( 'Colour', 'loopkit' ),
                            'type'    => 'color',
                            'default' => '#FFFFFF',
                        ),
                        array(
                            'name'       => 'play_icon_size',
                            'label'      => __( 'Size', 'loopkit' ),
                            'type'       => 'slider',
                            'responsive' => true,
                            'range'      => array(
                                'min' => 10,
                                'max' => 200,
                            ),
                            'default'    => array(
                                'size' => 60,
                                'unit' => 'px',
                            ),
                        ),
                        array(
                            'name'        => 'play_icon_text_shadow',
                            'label'       => __( 'Text Shadow', 'loopkit' ),
                            'type'        => 'popover',
                            'popoverType' => 'text_shadow',
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Parse YouTube ID from standard URLs.
     */
    private function get_youtube_id( $url ) {
        preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match );
        return $match[1] ?? '';
    }

    /**
     * Parse Vimeo ID from standard URLs.
     */
    private function get_vimeo_id( $url ) {
        preg_match( '/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/i', $url, $match );
        return $match[1] ?? '';
    }

    /**
     * Render widget output.
     *
     * @param array $settings    Element settings.
     * @param array $el          Full element array.
     * @param bool  $editor_mode Whether rendering in editor/canvas mode.
     * @return string HTML output.
     */
    public function render( $settings, $el, $editor_mode = false ) {
        $type   = $settings['video_type'] ?? 'youtube';
        $aspect = $settings['aspect_ratio'] ?? '169';

        $autoplay = ( ! $editor_mode && ( $settings['autoplay'] ?? 'no' ) === 'yes' );
        $mute     = ( ( $settings['mute'] ?? 'no' ) === 'yes' || $autoplay );
        $controls = ( ( $settings['controls'] ?? 'yes' ) !== 'no' );
        $loop     = ( ( $settings['loop'] ?? 'no' ) === 'yes' );
        $privacy  = ( ( $settings['privacy_mode'] ?? 'no' ) === 'yes' );

        $start = ! empty( $settings['start'] ) ? intval( $settings['start'] ) : 0;
        $end   = ! empty( $settings['end'] ) ? intval( $settings['end'] ) : 0;

        $embed_url = '';
        if ( 'youtube' === $type ) {
            $url = $settings['youtube_url'] ?? '';
            $yt_id = $this->get_youtube_id( $url );
            if ( $yt_id ) {
                $domain = $privacy ? 'www.youtube-nocookie.com' : 'www.youtube.com';
                $params = array(
                    'controls'       => $controls ? 1 : 0,
                    'autoplay'       => $autoplay ? 1 : 0,
                    'mute'           => $mute ? 1 : 0,
                    'loop'           => $loop ? 1 : 0,
                    'playlist'       => $loop ? $yt_id : '',
                    'start'          => $start ?: '',
                    'end'            => $end ?: '',
                    'modestbranding' => ( ( $settings['modest_branding'] ?? 'no' ) === 'yes' ) ? 1 : 0,
                );
                $embed_url = 'https://' . $domain . '/embed/' . $yt_id . '?' . http_build_query( array_filter( $params ) );
            }
        } elseif ( 'vimeo' === $type ) {
            $url = $settings['vimeo_url'] ?? '';
            $vm_id = $this->get_vimeo_id( $url );
            if ( $vm_id ) {
                $params = array(
                    'autoplay' => $autoplay ? 1 : 0,
                    'muted'    => $mute ? 1 : 0,
                    'loop'     => $loop ? 1 : 0,
                );
                $embed_url = 'https://player.vimeo.com/video/' . $vm_id . '?' . http_build_query( array_filter( $params ) );
            }
        } elseif ( 'dailymotion' === $type ) {
            $url = $settings['dailymotion_url'] ?? '';
            preg_match( '/video\/([a-zA-Z0-9]+)/', $url, $dm_match );
            $dm_id = $dm_match[1] ?? '';
            if ( $dm_id ) {
                $embed_url = 'https://www.dailymotion.com/embed/video/' . $dm_id;
            }
        }

        $placeholder_html = '<div class="lk-video-placeholder">' .
            '<div class="lk-video-placeholder-inner">' .
            '<div class="lk-video-placeholder-play">' .
            '<svg viewBox="0 0 64 64" fill="currentColor" width="48" height="48">' .
            '<circle cx="32" cy="32" r="30" fill="rgba(255, 255, 255, 0.12)" stroke="rgba(255, 255, 255, 0.3)" stroke-width="2"/>' .
            '<polygon points="26,20 46,32 26,44" fill="#ffffff"/>' .
            '</svg>' .
            '</div>' .
            '<span class="lk-video-placeholder-text">' . esc_html__( 'Choose your video', 'loopkit' ) . '</span>' .
            '</div>' .
            '</div>';

        $html = '<div class="lk-video-wrapper lk-video-aspect-' . esc_attr( $aspect ) . '">';

        if ( 'hosted' === $type ) {
            $h_url = $settings['hosted_url'] ?? '';
            if ( ! empty( $h_url ) ) {
                $html .= '<video src="' . esc_url( $h_url ) . '"' . ( $controls ? ' controls' : '' ) . ( $autoplay ? ' autoplay' : '' ) . ( $mute ? ' muted' : '' ) . ( $loop ? ' loop' : '' ) . ' playsinline></video>';
            } else {
                $html .= $placeholder_html;
            }
        } else {
            if ( ! empty( $embed_url ) ) {
                $html .= '<iframe src="' . esc_url( $embed_url ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            } else {
                $html .= $placeholder_html;
            }
        }

        $html .= '</div>';

        return $html;
    }
}

<?php
/*
Plugin Name: Responsive srcset images
Plugin URI: http://github.com/statesman/wordpress-srcset-images
Description: Creates Wordpress images that use the srcset attribute
Version: 0.0.1
Author: Andrew Chavez
Author URI: http://github.com/achavez/
License: GPL2
*/

class WPsrcset {

  function __construct() {

    update_option('image_default_link_type', 'none');

    // Alter get_image_tag so the editor automatically returns srcset-ed imgs
    add_filter( 'image_send_to_editor', array( $this, 'create_img_tag' ), 0, 6 );

    // Enqueue scripts
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts') );

    // Keep Wordpress from stripping out srcset tags
    add_filter( 'tiny_mce_before_init', array( $this, 'whitelist_srcset' ) );

  }

  /**
   * Enqueues required JavaScript files
   */
  function enqueue_scripts() {

    // Use PictureFill as a polyfill for non-srcset browsers
    wp_enqueue_script(
      'picturefill',
      plugins_url( 'js/picturefill.min.js', __FILE__ ),
      null,
      '2.1.0',
      TRUE
    );

  }

  /**
   * Create an <img> tag using with a srcset attribute that can be
   * used for responsive images.
   *
   * @param string $html
   * @param int $id
   * @param string $alt
   * @param string $title
   * @param string $align
   * @param string $size
   *
   * @return string
   *
   * @link http://codex.wordpress.org/Function_Reference/get_image_tag
   */
  function create_img_tag( $html, $id, $alt, $title, $align, $size ) {

    // An empty array to hold all the HTML attributes
    $attrs = array();

    // Add fallback src
    $default_image = wp_get_attachment_image_src( $id, $size );
    $attrs['src'] = $default_image[0];

    // Add srcset files
    $widths = $this->get_img_sizes();
    $srcs = array();
    foreach( $widths as $available_size => $width ) {
      $file = wp_get_attachment_image_src( $id, $available_size );
      $url = $file[0];
      $width = $file[1];
      $srcs[ $available_size ] = $url . ' ' . $width . 'w';
    }
    $attrs['srcset'] = implode(', ', $srcs);

    $attrs['sizes'] = '100vw';

    // From https://core.trac.wordpress.org/browser/tags/4.0/src/wp-includes/media.php#L305
    $class = 'align' . esc_attr( $align ) . ' size-' . esc_attr( $size ) . ' wp-image-' . $id;
    $class = apply_filters( 'get_image_tag_class', $class, $id, $align, $size );
    $attrs['class'] = $class;

    // Add alt and title attributes, if they're set
    if( !empty( $alt ) ) {
      $attrs['alt'] = $alt;
    }
    if( !empty( $title ) ) {
      $attrs['title'] = $title;
    }

    // Build the <img> tag
    $attr_strings = array();
    foreach( $attrs as $attr => $value ) {
      $attr_strings[ $attr ] = $attr . '="' . esc_attr($value) . '"';
    }
    $tag = '<img ' . implode( ' ', $attr_strings ) . ' />';

    return $tag;
  }

  /**
   * Returns all of the available image sizes
   *
   * @return array with all available sizes and width, height and crop settings
   *   for each
   *
   * @link http://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
   */
  function get_img_sizes() {

    global $_wp_additional_image_sizes;

    $sizes = array();
    $get_intermediate_image_sizes = get_intermediate_image_sizes();

    // Create the full array with sizes and crop info
    foreach( $get_intermediate_image_sizes as $_size ) {

      if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {

        $sizes[ $_size ] = get_option( $_size . '_size_w' );

      } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {

        $sizes[ $_size ] = $_wp_additional_image_sizes[ $_size ]['width'];

      }

    }

    return $sizes;
  }

  /**
  * Make Wordpress's HTML validator/purifier a bit more lenient on
  * <img> attributes. Inspired by:
  * https://github.com/mattheu/WordPress-srcset/blob/f76c389789a7b9bab4faff3134717c0c0c6c5fee/plugin.php#L155-L175
  *
  * @param $init array
  * @return $init array
  *
  * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/tiny_mce_before_init
  */
  function whitelist_srcset( $init ) {

    $ext = 'img[*]';

    // Add to extended_valid_elements if it alreay exists
    if ( isset( $init['extended_valid_elements'] ) ) {
      $init['extended_valid_elements'] .= ',' . $ext;
    } else {
      $init['extended_valid_elements'] = $ext;
    }

    return $init;
  }

}

$wpsrcset = new WPsrcset();

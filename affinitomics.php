<?php
/*
Plugin Name: aimojo
Plugin URI: http://prefrent.com
Description: Apply Affinitomic Descriptors, Draws, and Distance to Posts and Pages.  Shortcode to display Affinitomic relationships. Google CSE with Affinitomics.
Version: 1.4.1
Author: Prefrent
Author URI: http://prefrent.com
*/

/*
aimojo (Wordpress Plugin)
Copyright (C) 2015 Prefrent
*/

// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

define( 'AI_MOJO__VERSION', '1.4.1' );
define( 'AI_MOJO__TYPE', 'aimojo_wp' );
define( 'AI_MOJO__MINIMUM_WP_VERSION', '3.5' );
define( 'AI_MOJO__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AI_MOJO__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'plugin_activation' );
register_deactivation_hook( __FILE__, 'plugin_deactivation' );

wp_enqueue_style( 'afpost-style', plugins_url('affinitomics.css', __FILE__) );
//wp_enqueue_style( 'purecss', esc_url_raw( 'http://yui.yahooapis.com/pure/0.6.0/pure.css' ), array(), null );


// This is so we can check if the affinitomics taxonomy converter plugin is installed
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

//additional includes as necessary
include( AI_MOJO__PLUGIN_DIR . 'extensions/shortcode_generator/sc_generator_panel.php');

/* Save Action */
add_action( 'save_post', 'afpost_save_postdata' );
global $afview_count;
$afview_count = 0;
add_action( 'init', 'my_script_enqueuer' );


add_action( 'admin_enqueue_scripts', 'aimojo_admin_scripts');

// add an admin notice if aimojo isn't setup
add_action( is_network_admin() ? 'network_admin_notices' : 'admin_notices',  'display_notice'  );



/**
 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
 */
function plugin_activation()
{
    $message = '';
    if ( version_compare( $GLOBALS['wp_version'], AI_MOJO__MINIMUM_WP_VERSION, '<' ) )
    {
      load_plugin_textdomain( 'aimojo' );

      $message = sprintf(esc_html__( 'aimojo %s requires WordPress %s or higher.' , 'aimojo'), AI_MOJO__VERSION, AI_MOJO__MINIMUM_WP_VERSION ).sprintf(__('Please upgrade WordPress to a current version.', 'aimojo'), 'https://codex.wordpress.org/Upgrading_WordPress', 'http://wordpress.org/extend/plugins/aimojo/download/');

   }
   else
   {
      af_check_for_errors();

      $af_errors = get_option('af_errors', '');
      $af_error_code = get_option('af_error_code', '');

      if(strlen($af_errors) > 0)
      {
        $message = sprintf(esc_html__( 'aimojo: %s ' , 'aimojo'), $af_errors);
      }

      af_update_url();

      update_option("af_post_type_posts", "true");    //setting default to turn on affinitomics for pages
      update_option("af_post_type_pages", "true");    //setting default to turn on affinitomics for posts
      update_option("af_post_type_products", "true");    //setting default to turn on affinitomics for posts

   }


    if (strlen($message) > 0)
    {
      bail_on_activation( $message );
    }
}

function plugin_deactivation( )
{
  //TODO:
}

function days_since_activation()
{
      if( !get_option( "af_install_date" ) )
      { //setup the install date option
        update_option("af_install_date", current_time( 'timestamp' ));
      }
      $install_time = get_option("af_install_date");
      $days = current_time( 'timestamp' ) - $install_time;
      $days = floor($days / 86400);                //how many days since first installed
      return $days;
}

function check_registration_remaining()
{
  $elapsed = days_since_activation();
  if ($elapsed >= 30)
  {
    $af_key = af_verify_key();
    $af_cloud_url = af_verify_provider();
    $status_request = curl_request($af_cloud_url . "/api/account_status?user_key=" . $af_key);
    $status_response = json_decode($status_request, true);
    $account_status_type = $status_response['data']['account_type'];

    if($account_status_type == 'Anonymous')
    {
      return false;
    }
  }

  return true;

}


function bail_on_activation( $message, $deactivate = true ) {
?>
<!doctype html>
<html>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<style>
* {
  text-align: center;
  margin: 0;
  padding: 0;
  font-family: "Lucida Grande",Verdana,Arial,"Bitstream Vera Sans",sans-serif;
}
p {
  margin-top: 1em;
  font-size: 18px;
}
</style>
<body>
<p><?php echo esc_html( $message ); ?></p>
</body>
</html>
<?php
    if ( $deactivate ) {
      $plugins = get_option( 'active_plugins' );
      $aimojo = plugin_basename( AI_MOJO__PLUGIN_DIR . 'affinitomics.php' );
      $update  = false;
      foreach ( $plugins as $i => $plugin ) {
        if ( $plugin === $akismet ) {
          $plugins[$i] = false;
          $update = true;
        }
      }

      if ( $update ) {
        update_option( 'active_plugins', array_filter( $plugins ) );
      }
    }
    exit;
  }


function my_script_enqueuer()
{
   wp_enqueue_script( 'jquery' );
   $plugins_ajax_script_url = plugins_url( 'affinitomics_ajax_script.js', __FILE__ );
   wp_register_script( "affinitomics_ajax_script", $plugins_ajax_script_url, array('jquery') );
   wp_localize_script( 'affinitomics_ajax_script', 'myAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));

   wp_enqueue_script( 'affinitomics_ajax_script' );



}

function custom_restore_function($post_ID) {
  $the_key = af_verify_key();
  $af_cloud_url = af_verify_provider();
  $url = get_permalink($post_ID);
  $request = curl_request($af_cloud_url . "/api/restore_resource?user_key=" . $the_key . '&uid=' . $post_ID . '&url=' . $url);
}

add_action('untrash_post', 'custom_restore_function');

/* Ensure pages have category IDs for server compatability. We'll want to document in the help file that we have this effect on a person's wordpress install.  */
function add_categories_to_pages() {
 register_taxonomy_for_object_type( 'category', 'page' );
 }
add_action( 'init', 'add_categories_to_pages' );

/* Page Types to Apply Affinitomics */
$screens = array();
// if (get_option('af_post_type_affinitomics','true') == 'true') $screens[] = 'archetype';
if (get_option('af_post_type_posts','false') == 'true') $screens[] = 'post';
if (get_option('af_post_type_pages','false') == 'true') $screens[] = 'page';
if (get_option('af_post_type_products','false') == 'true') $screens[] = 'product';
if (get_option('af_post_type_projects','false') == 'true') $screens[] = 'project';
if (get_option('af_post_type_listings','false') == 'true') $screens[] = 'listing';

add_action('admin_menu', 'remove_extra_submenu_items');

function remove_extra_submenu_items() {
  global $submenu;
  unset($submenu["edit.php?post_type=archetype"][10]);
  unset($submenu["edit.php?post_type=archetype"][5]);
}

function af_verify_key()
{
  $af_key = get_option('af_key');
    if (!isset($af_key) || $af_key == "")
    {
      $af_cloud_url = af_verify_provider();
      $request = curl_request($af_cloud_url . "/api/anon_key");
      $response = json_decode($request, true);
      $af_key = $response['data']['anon_key'];
      update_option( 'af_key' , $af_key );

  }
  return $af_key;
}

function af_check_for_errors(){
  $the_key = af_verify_key();
  $af_cloud_url = af_verify_provider();

  $request = curl_request($af_cloud_url . "/check_for_errors?user_key=" . $the_key);
  $response = json_decode($request, true);
  update_option( 'af_errors' , $response['data']['af_errors'] );
  update_option( 'af_error_code' , $response['data']['af_error_code'] );
}

function af_verify_provider()
{
  $af_cloud_url = get_option('af_cloud_url', '');
  if (!isset($af_cloud_url) || $af_cloud_url == "")
  {
    $af_cloud_url = 'www.affinitomics.com';
//    $af_cloud_url = 'localhost:3000';         //TODO: this is just for testing
    update_option( 'af_cloud_url' , $af_cloud_url );
  }
  return $af_cloud_url;
}

function af_update_url()
{
     $affinitomics = array(
      'url' =>  get_site_url(),
      'title' => '',
      'descriptors' => '',
      'draws' => '',
      'distances' => '',
      'key' => af_verify_key(),
      'uid' => '',
      'category' => '',
      'status' => ''
    );
    if ($afid) $affinitomics['afid'] = $afid;
    $af_cloudify_url = get_option('af_cloud_url') . '/api/affinitomics/cloudify/' . af_verify_key() . '/';
    $request = curl_request($af_cloudify_url, $affinitomics);
}

/* Save Custom DATA */
function afpost_save_postdata() {
  af_verify_key();
  af_verify_provider();
  $post_ID = get_the_id();
  $post_status = get_post_status($post_ID);

  // Collect descriptor terms from the post
  $these_descriptors = wp_get_post_terms( $post_ID, "descriptor" );
  $descriptor_terms = array();
  foreach ($these_descriptors as $descriptor) {
    array_push($descriptor_terms, $descriptor->name);
  }

  // Collect draw terms from the post
  $these_draws = wp_get_post_terms( $post_ID, "draw" );
  $draw_terms = array();
  foreach ($these_draws as $draw) {
    array_push($draw_terms, $draw->name);
  }

  // Collect distance terms from the post
  $these_distances = wp_get_post_terms( $post_ID, "distance" );
  $distance_terms = array();
  foreach ($these_distances as $distance) {
    array_push($distance_terms, $distance->name);
  }

  // implode the data
  $afpost_descriptors =  implode(",", $descriptor_terms);
  $afpost_draw = implode(",", $draw_terms);
  $afpost_distance = implode(",", $distance_terms);

  // Save Meta DATA
  add_post_meta($post_ID, '_afpost_descriptors', $afpost_descriptors, true) or update_post_meta($post_ID, '_afpost_descriptors', $afpost_descriptors);
  add_post_meta($post_ID, '_afpost_draw', $afpost_draw, true) or update_post_meta($post_ID, '_afpost_draw', $afpost_draw);
  add_post_meta($post_ID, '_afpost_distance', $afpost_distance, true) or update_post_meta($post_ID, '_afpost_distance', $afpost_distance);

  // Affinitomic ID
  $afid = get_post_meta($post_ID, 'afid', true);

  // Categories String
  $cat_string = '';

  // Save Data To Prefrent Cloud
  global $af_flag;
  if ($af_flag == 0) {
    $cat_string = '';
    $categories = get_the_category($id);
    if ($categories) {
      $cats = array();
      foreach($categories as $cat) {
        $cats[] = $cat->term_id;
      }
      $cat_string = implode(",", $cats);
    }
    $affinitomics = array(
      'url' =>  get_permalink($post_ID),
      'title' => get_the_title($post_ID),
      'descriptors' => $afpost_descriptors,
      'draws' => $afpost_draw,
      'distances' => $afpost_distance,
      'key' => af_verify_key(),
      'uid' => $post_ID,
      'category' => $cat_string,
      'status' => $post_status
    );
    if ($afid) $affinitomics['afid'] = $afid;
    $af_cloudify_url = get_option('af_cloud_url') . '/api/affinitomics/cloudify/' . af_verify_key() . '/';
    $request = curl_request($af_cloudify_url, $affinitomics);

    $af = json_decode($request, true);
    if (isset($af['data']['objectId'])) {
      update_post_meta($post_ID, 'afid', $af['data']['objectId']);
    }
  }
  $af_flag = 1;
}

/*
----------------------------------------------------------------------
CUSTOM TAXONOMY
----------------------------------------------------------------------
*/

// Register Custom Taxonomy Descriptor
function descriptor_taxonomy()  {
    $labels = array(
        'name'                       => _x( 'Descriptors', 'Taxonomy General Name', 'text_domain' ),
        'singular_name'              => _x( 'Descriptor', 'Taxonomy Singular Name', 'text_domain' ),
        'menu_name'                  => __( 'Descriptor', 'text_domain' ),
        'all_items'                  => __( 'All Descriptors', 'text_domain' ),
        'parent_item'                => __( 'Parent Descriptor', 'text_domain' ),
        'parent_item_colon'          => __( 'Parent Descriptor:', 'text_domain' ),
        'new_item_name'              => __( 'New Descriptor', 'text_domain' ),
        'add_new_item'               => __( 'Add New Descriptor', 'text_domain' ),
        'edit_item'                  => __( 'Edit Descriptor', 'text_domain' ),
        'update_item'                => __( 'Update Descriptor', 'text_domain' ),
        'separate_items_with_commas' => __( '<strong>Descriptors</strong> are similar to Categories in Wordpress. Separate
                       each Descriptor with commas. <strong>e.g.</strong> Summer Activities, Hobbies',
                       'text_domain' ),
        'search_items'               => __( 'Search descriptors', 'affinitomics' ),
        'add_or_remove_items'        => __( 'Add or remove descriptors', 'text_domain' ),
        'choose_from_most_used'      => __( 'Choose from the most used Descriptors', 'text_domain' ),
    );

    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
        'show_in_menu'               => true,
    );

    global $screens;
    register_taxonomy( 'descriptor', $screens, $args );
}

// Hook into the 'init' action
add_action( 'init', 'descriptor_taxonomy', 0 );
register_taxonomy_for_object_type( 'item', 'product' );

// Register Custom Taxonomy Draw
function draw_taxonomy()  {
    $labels = array(
        'name'                       => _x( 'Positive Relationships (Draws)', 'Taxonomy General Name', 'text_domain' ),
        'singular_name'              => _x( 'Draw', 'Taxonomy Singular Name', 'text_domain' ),
        'menu_name'                  => __( 'Draw', 'text_domain' ),
        'all_items'                  => __( 'All Draws', 'text_domain' ),
        'parent_item'                => __( 'Parent Draw', 'text_domain' ),
        'parent_item_colon'          => __( 'Parent Draw:', 'text_domain' ),
        'new_item_name'              => __( 'New Draw', 'text_domain' ),
        'add_new_item'               => __( 'Add New Draw', 'text_domain' ),
        'edit_item'                  => __( 'Edit Draw', 'text_domain' ),
        'update_item'                => __( 'Update Draw', 'text_domain' ),
        'separate_items_with_commas' => __( '<strong>Syntax:</strong> Draws can have a magnitude from 1 to 5 written
                       as a suffix, with each draw separated by a comma. If a magnitude is not present,
                       a magnitude of one will be assumed. <strong>e.g.</strong> Cats5, Laser Pointer2',
                       'text_domain' ),
        'search_items'               => __( 'Search draws', 'affinitomics' ),
        'add_or_remove_items'        => __( 'Add or remove draws', 'text_domain' ),
        'choose_from_most_used'      => __( 'Choose from the most used Draws', 'text_domain' ),
    );

    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
    );

    global $screens;
    register_taxonomy( 'draw', $screens, $args );
}

// Hook into the 'init' action
add_action( 'init', 'draw_taxonomy', 0 );
register_taxonomy_for_object_type( 'item', 'product' );

// Register Custom Taxonomy Distance
function distance_taxonomy()  {
    $labels = array(
        'name'                       => _x( 'Negative Relationships (Distances)', 'Taxonomy General Name', 'text_domain' ),
        'singular_name'              => _x( 'Distance', 'Taxonomy Singular Name', 'text_domain' ),
        'menu_name'                  => __( 'Distance', 'text_domain' ),
        'all_items'                  => __( 'All Distances', 'text_domain' ),
        'parent_item'                => __( 'Parent Distance', 'text_domain' ),
        'parent_item_colon'          => __( 'Parent Distance:', 'text_domain' ),
        'new_item_name'              => __( 'New Distance', 'text_domain' ),
        'add_new_item'               => __( 'Add New Distance', 'text_domain' ),
        'edit_item'                  => __( 'Edit Distance', 'text_domain' ),
        'update_item'                => __( 'Update Distance', 'text_domain' ),
        'separate_items_with_commas' => __( '<strong>Syntax:</strong> Distances can have a magnitude of 1 to 5, written
                       as a suffix, with each distance separated by a comma. If a magnitude is not present,
                       a magnitude of one will be assumed. <strong>e.g.</strong> Nickelback5, Canada2',
                       'text_domain' ),
        'search_items'               => __( 'Search distances', 'affinitomics' ),
        'add_or_remove_items'        => __( 'Add or remove Distance', 'text_domain' ),
        'choose_from_most_used'      => __( 'Choose from the most used Distances', 'text_domain' ),
    );

    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
    );

    global $screens;
    register_taxonomy( 'distance', $screens, $args );
}

// Hook into the 'init' action
add_action( 'init', 'distance_taxonomy', 0 );
register_taxonomy_for_object_type( 'item', 'product' );

/*
----------------------------------------------------------------------
Register "Archetype" post type
----------------------------------------------------------------------
*/
// Register Custom Post Type
function arche_type() {

  $labels = array(
    'name'                => __( 'Archetypes', 'Post Type General Name', 'text_domain' ),
    'singular_name'       => __( 'Archetype', 'Post Type Singular Name', 'text_domain' ),
    'menu_name'           => __( 'Affinitomics&trade;', 'text_domain' ),
    'parent_item_colon'   => __( 'Parent Archetype:', 'text_domain' ),
    'all_items'           => __( 'All Archetypes', 'text_domain' ),
    'view_item'           => __( 'View Archetype', 'text_domain' ),
    'add_new_item'        => __( 'Add New Archetype', 'text_domain' ),
    'add_new'             => __( 'New Archetype', 'text_domain' ),
    'edit_item'           => __( 'Edit Archetype', 'text_domain' ),
    'update_item'         => __( 'Update Archetype', 'text_domain' ),
    'search_items'        => __( 'Search Archetypes', 'text_domain' ),
    'not_found'           => __( 'No archetypes found', 'text_domain' ),
    'not_found_in_trash'  => __( 'No archetypes found in Trash', 'text_domain' ),
  );
  $args = array(
    'label'               => __( 'archetype', 'text_domain' ),
    'description'         => __( 'Archetype information pages', 'text_domain' ),
    'labels'              => $labels,
    'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields', 'post-formats', ),
    'taxonomies'          => array( 'descriptor', 'draw', 'distance' ),
    'hierarchical'        => false,
    'public'              => true,
    'show_ui'             => true,
    'show_in_menu'        => false,
    'show_in_nav_menus'   => true,
    'show_in_admin_bar'   => true,
    'menu_position'       => 5,
    'menu_icon'           => plugins_url( 'affinitomics-favicon.svg', __FILE__ ),
    'can_export'          => true,
    'has_archive'         => true,
    'exclude_from_search' => false,
    'publicly_queryable'  => true,
    'capability_type'     => 'post',
  );
  register_post_type( 'archetype', $args );

}

// // Hook into the 'init' action
add_action( 'init', 'arche_type', 0 );

/*
----------------------------------------------------------------------
RELATED POSTS SHORTCODE
Examples: [afview], [afview limit="4"], [afview category_filter="50"]
          [afview limit=1 display_title="false"]
----------------------------------------------------------------------
*/

//register shortcode
add_shortcode("afview", "afview_handler");

//handle shortcode
function afview_handler( $atts, $content = null ) {
    $afview_output = afview_function($atts);
    return $afview_output;
}

//process shortcode
function afview_function($atts) {
  af_verify_key();
  af_verify_provider();

  global $screens;
  extract( shortcode_atts( array(
      'affinitomics'    => null,
      'display_title'   => 'true',
      'limit'           => 10,
      'category_filter' => '',
      'title'           => ''
  ), $atts ) );

  // Start output
  // $afview_output = '<div class="afview">';
  $afview_output = '';

  $post_id = get_the_ID();
  $afid = get_post_meta($post_id, 'afid', true);
  $af_domain = get_option('af_domain');
  $af_key = af_verify_key();

  // Find Related Elements
  if ($afid) {

    if (!empty($category_filter))
    {
      if (!is_numeric($category_filter))
      { //we were given a slug instead of an id, so find the id of the slug
       $foundCategory = get_category_by_slug($category_filter);
        if ($foundCategory)
        { //get the id from the found category and make that the category filter
          $category_filter = $foundCategory->term_id;
        }
      }
    }

    $af_cloud = get_option('af_cloud_url') . '/api/affinitomics/related/' . $af_key . '?afid=' . $afid . '&ctype=' . AI_MOJO__TYPE . '&cversion=' . AI_MOJO__VERSION . '&limit=' . $limit . '&category_filter=' . $category_filter;
    if ($affinitomics) {
      $af_cloud = $af_cloud . '&af=' . rawurlencode($affinitomics);
    }

    global $afview_count;
    $afview_count ++;

    if ($display_title == 'true')
    {
      if (!empty($title))
      {
        $afview_output .= '<h2 class="aftitle">' . $title . '</h2>';
      }
      else
      {
        $afview_output .= '<h2 class="aftitle">Affinitomic Relationships: ';

        // These are the custom affinitomics
        if ($affinitomics)
        {
          $afview_output .= $affinitomics;
        }

        $afview_output .= ' <i class="afsubtitle"></i></h2>';
      }
    }
    $run = 1;
    if (check_registration_remaining() == false)
    {
      $run = 0;
    }
    $afview_output .= '<input type="hidden" name="af_view_placeholder" value="' . $af_cloud . '" id="af_view_' . $afview_count . '" alt="' . $run . '">';
  }

  // HTML Output
  /*
  <div class="afview">
    <h2 class="aftitle">
      Related Items: +foo, -bar <i class="afsubtitle">(sorted by Affinitomic concordance)</i>
    </h2>
    <ul class="aflist">
      <li class="afelement">
        <a href="http://localhost/WordPress/?p=2" class="afelementurl">
          <span class="afelementtitle">Foo!</span>
        </a>
        <span class="afelementscore">(1)</span>
      </li>
      <li class="afelement">
        <a href="http://localhost/WordPress/?p=3" class="afelementurl">
          <span class="afelementtitle">Foo Bar!</span>
        </a>
        <span class="afelementscore">(0)</span>
      </li>
    </ul>
  </div>
  */

  return $afview_output;
}
/*
End Affinitomics Commercial Code
*/
/*
----------------------------------------------------------------------
Administration and Settings Menu
----------------------------------------------------------------------
*/

add_action( 'admin_menu', 'aimojo_admin_menu' );


/*  //TODO: deprecated functionality
add_action( 'admin_menu', 'af_plugin_menu' );

function af_plugin_menu() {
  // Add Custom Sub Menus
  add_submenu_page( 'edit.php?post_type=archetype', 'Settings', 'Settings', 'manage_options', 'affinitomics', 'af_plugin_options');
  add_action( 'admin_init', 'af_register_settings' );
  add_submenu_page( 'edit.php?post_type=archetype', 'Cloud Export', 'Cloud Export', 'manage_options', 'afcloudify', 'af_plugin_export');
}
*/

/*
Affinitomics Commercial Code
*/

function af_plugin_export()
{
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }


  if ( isset( $_GET['quietUpdate'] ) )
  {
    //update the post's meta data if it has been updated by the server
    if (isset( $_GET['postID'] ))
    {
      $postID = $_GET['postID'];

      if (isset( $_GET['afid'] ))
      {
       update_post_meta($postID, 'afid', $_GET['afid']);
      }
    }

    return;
  }


	echo'</div>';
  af_verify_key();
  af_verify_provider();

  // Export to Cloud
    echo ' 	<div class="pure-g">
    			<div class="aimojo-tab-header pure-u-1"><h2>Export and "Cloudify"</h2>
    			</div>
    		</div>';
  	echo '	<div class="pure-g">
  				<div class="pure-u-1 pure-alert">
  				<p>Your Affinitomics are saved in the cloud each time you publish a page or post so that our servers can do the heavy computation lift, leaving yours free to serve web pages as fast as possible. You only need to export your Affinitomics&#0153; to the cloud if:</p>
  					<ol class="plain">
  					<li>You have edited multiple pages, posts or custom post types via the QuickEdit admin feature of Wordpress, or;</li>
  					<li>You have recently imported pages, posts, or custom post types that contain Affinitomics&#0153;.</li>
					</ol>
  					<p><strong>Again, pages, posts and custom post types</strong> ( those specified in the "settings" tab ) <strong>added or edited individually after ai&#8226;mojo&#0153; was installed automatically save their Affinitomic elements to the cloud when published.</strong></p>
    			</div>
    		</div>';
    	if (isset($_POST['af_cloudify']) && $_POST['af_cloudify'] == 'true')  {
    echo '<input type="hidden" id="af_cloud_sync_go" value="yes">';
  }

  global $screens;
  $args = array(
    'post_type'      => $screens,
    'category'       => $category_id,
    'posts_per_page' => -1
  );
  $posts_array = get_posts($args);
  echo '<input type="hidden" value="' . sizeof($posts_array) . '" id="total_items_to_sync">';
  echo '<ol class="cloud_sync_ol">';
  foreach($posts_array as $post) {
    place_jquery_tag($post);
  }
  echo '</ol>';

  // Default View
  echo '<div class="wrap">';
  echo '<div id="aimojo-progress-div"  style="display:none;">';
  //echo '<p class="aimojo-progress-text" style="width:65%" data-value="65">Exporting...</p>';
  echo '<div id="aimojo-export-update-div">';
  echo '<div class="aimojo-progress-text" style="width:65%" data-value="65">Exporting...</div>';
  echo '<div id="aimojo-export-status" > ... </div>';
  echo '</div>';
  echo '<progress  name="aimojo_export_progress"  max="100" value="0" class="aimojo-progress" >';
  //    <!-- Browsers that support HTML5 progress element will ignore the html inside `progress` element. Whereas older browsers will ignore the `progress` element and instead render the html inside it. -->';
  echo '<div class="progress-bar">';
  echo '<span style="width: 65%">100%</span>';
  echo '</div>';
  echo '</progress>';
  echo '</div>';


  echo '<form id="aimojo-export-form" method="post" action="">';
  settings_fields( 'af-cloud-settings-group' );
  do_settings_sections( 'af-cloud-settings-group' );
  $af_cloudify = get_option( 'af_cloudify', '' );
  if ($af_cloudify == 'true') $cloud_checked = 'checked="checked"';
  echo '<h4>Migrate Affinitomics&#0153; to the Cloud?</h4>';
  echo '<input type="checkbox" name="af_cloudify" value="true" '.$cloud_checked.'/> Make it So!';
  submit_button('Export');
  echo '</form>';
  echo '</div>';

  if (is_plugin_active('affinitomics-taxonomy-converter/affinitomics-taxonomy-converter.php')) {
    echo '<a href="admin.php?import=wptaxconvertaffinitomics">Convert Taxonomy</a>';
  } else {
  echo 'Hey, did you know we have a handy importing tool? Check out the ';
  echo '<a href="https://wordpress.org/plugins/affinitomics-taxonomy-converter/" target="_blank">Affinitomics&#0153; Taxonomy Converter</a>';
  }
}

function place_jquery_tag($post){
  $id = $post->ID;
  $afid = get_post_meta($id, 'afid', true);
  $cat_string = '';
  $categories = get_the_terms( $id, 'category' );

  if ($categories) {
    $cats = array();
    foreach($categories as $cat) {
      $cats[] = $cat->term_id;
    }
    $cat_string = implode(",", $cats);
  }

  // Collect draw terms from the post
  $these_draws = wp_get_post_terms( $id, "draw" );
  $draw_terms = array();
  foreach ($these_draws as $draw) {
  array_push($draw_terms, $draw->name);
  }

  // Collect distance terms from the post
  $these_distances = wp_get_post_terms( $id, "distance" );
  $distance_terms = array();
  foreach ($these_distances as $distance) {
  array_push($distance_terms, $distance->name);
  }

  // Collect descriptor terms from the post
  $these_descriptors = wp_get_post_terms( $id, "descriptor" );
  $descriptor_terms = array();
  foreach ($these_descriptors as $descriptor) {
  array_push($descriptor_terms, $descriptor->name);
  }

  $post_status = get_post_status($id);

  $affinitomics = array(
    'url' =>  get_permalink($id),
    'title' => get_the_title($id),
    'descriptors' => implode(',', $descriptor_terms),
    'draws' => implode(',', $draw_terms),
    'distances' => implode(',', $distance_terms),
    'uid' => $id,
    'category' => $cat_string,
    'status' => $post_status
  );

  if ($affinitomics['descriptors'] || $affinitomics['draws'] || $affinitomics['distances']) {
    $af_cloud_url = get_option('af_cloud_url') . '/api/affinitomics/cloudify/' . af_verify_key() . '/?';

    $af_cloud_url .= '&url=' . get_permalink($id);
    $af_cloud_url .= '&title=' . get_the_title($id);
    $af_cloud_url .= '&descriptors=' . implode(',', $descriptor_terms);
    $af_cloud_url .= '&draws=' . implode(',', $draw_terms);
    $af_cloud_url .= '&distances=' . implode(',', $distance_terms);
    $af_cloud_url .= '&uid=' . $id;
    $af_cloud_url .= '&category=' . $cat_string;
    $af_cloud_url .= '&status=' . $post_status;
    $af_cloud_url .= '&ctype=' . AI_MOJO__TYPE;
    $af_cloud_url .= '&cversion=' . AI_MOJO__VERSION;


    echo '<input type="hidden" name="af_cloud_sync_placeholder" value="' . $af_cloud_url . '">';
  }
}

function af_plugin_options() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

      if ( isset( $_GET['dismissNotice'] ) )
      {
        update_option( 'af_banner_notice_dismissed' , 'true' );
      }


  $af_key = af_verify_key();
  echo '<div class="wrap">';
  echo '<div class="pure-g"><div class="aimojo-tab-header pure-u-1">';
  echo '<h2>Affinitomics&#0153; Plugin Settings</h2></div></div>';
  echo '<div class="pure-g">
  		<div class="pure-u-1 pure-alert" style="padding-top: 16px;">
  		<p>This is where you tell your Wordpress install how to talk to our system. Once set, we can do the heavy lifting when it comes to measuring relationships within your site – meaning that you can maintain a lightweight, fast site with the power of a much more robust intelligent system. Once these settings are made, archetypes will be created and saved in the Affinitomics&#0153; I.Q. cloud whenever you save information containing Affinitomics&#0153; on your site.</p>

  		<p><strong>Legacy Data and the Export Function</strong><br/>
  		Legacy data (stuff you already had saved before you installed ai•mojo) will not automatically become Affinitomic Archetypes. You will have to tell ai•mojo to do that for you. Once you’ve  turned your legacy data into Affinitomic archetypes, you will need to export the data to the cloud (see export tab).</p>

  		<h4>You should register the plugin as well (below). Even if you continue using the free version – It gives it 5x more power!</h4>

  		</div>
  		</div>';
  echo '<form method="post" action="options.php">';
  settings_fields( 'af-settings-group' );
  do_settings_sections( 'af-settings-group' );

  $af_cloud_url = af_verify_provider();
  af_check_for_errors();
  $af_errors = get_option('af_errors', '');
  $af_error_code = get_option('af_error_code', '');

  if(strlen($af_errors) > 0) {
    echo '<h3 style="color:red;font-weight:bold;">----- Warning -----</h3>';
    echo '<p>Error Message: ' . $af_errors . '</p>';
    echo '<p>Error Code: ' . $af_error_code . '</p>';
    echo '<h3 style="color:red;font-weight:bold;">----- Warning -----</h3>';
  }
/*
Affinitomics Commercial Code
*/

  $status_request = curl_request($af_cloud_url . "/api/account_status?user_key=" . $af_key);
  $status_response = json_decode($status_request, true);
  $signin_url = $status_response['data']['signin_url'];
  $account_status_type = $status_response['data']['account_type'];

  echo '<h4>Affinitomics&trade; API Key</h4>';
  echo '<input type="text" name="af_key" value="'.$af_key.'" />';
  echo '<p>';

  if($account_status_type == 'Anonymous')
  {
    echo 'Anonymous Account - Register for free and see in-depth reporting!<br>';
    echo '<a href="https://' . $af_cloud_url . $signin_url . '" target="_blank">Register for Free</a>';
    $elapsed = days_since_activation();
    $days_remaining = 30 - $elapsed;
    if ($days_remaining <= 0)
    {
      echo  '<br> Registration is now required to continue using Affinitomics for free!<br>';
    }
    else
    {
      echo  '<br>' . $days_remaining .  ' days remaining until registration is required.<br>';
    }
  } elseif ($account_status_type == 'Legacy') {
    echo 'Legacy User Account - Create your user account for free and see in-depth reporting!<br>';
    echo '<a href="https://' . $af_cloud_url . $signin_url . '" target="_blank">Register for Free</a>';
  } elseif ($account_status_type == 'Registered') {
    echo 'Registered User<br>';
    echo '<a href="https://' . $af_cloud_url . $signin_url . '" target="_blank">View Usage Statistics</a>';
  } else {
    echo 'API Key Unrecognized...';
  }
  echo '</p>';

  $af_post_type_affinitomics = get_option('af_post_type_affinitomics');
  $af_post_type_posts = get_option('af_post_type_posts');
  $af_post_type_pages = get_option('af_post_type_pages');
  $af_post_type_products = get_option('af_post_type_products');
  $af_post_type_projects = get_option('af_post_type_projects');
  $af_post_type_listings = get_option('af_post_type_listings');
  $af_post_type_affinitomics_checked = '';
  $af_post_type_posts_checked = '';
  $af_post_type_pages_checked = '';
  $af_post_type_products_checked = '';
  $af_post_type_projects_checked = '';
  $af_post_type_listings_checked = '';
  if ($af_post_type_affinitomics == 'true') $af_post_type_affinitomics_checked = 'checked="checked"';
  if ($af_post_type_pages == 'true') $af_post_type_pages_checked = 'checked="checked"';
  if ($af_post_type_posts == 'true') $af_post_type_posts_checked = 'checked="checked"';
  if ($af_post_type_products == 'true') $af_post_type_products_checked = 'checked="checked"';
  if ($af_post_type_projects == 'true') $af_post_type_projects_checked = 'checked="checked"';
  if ($af_post_type_listings == 'true') $af_post_type_listings_checked = 'checked="checked"';
  echo '<h3>To which Post-types would you like to apply your Affinitomics&trade;?</h3>';
  echo '<input type="checkbox" name="af_post_type_posts" value="true" '.$af_post_type_posts_checked.'/> Posts<br />';
  echo '<input type="checkbox" name="af_post_type_pages" value="true" '.$af_post_type_pages_checked.'/> Pages<br />';
  echo '<input type="checkbox" name="af_post_type_products" value="true" '.$af_post_type_products_checked.'/> Products<br />';
  echo '<input type="checkbox" name="af_post_type_projects" value="true" '.$af_post_type_projects_checked.'/> Projects<br />';
  echo '<input type="checkbox" name="af_post_type_listings" value="true" '.$af_post_type_listings_checked.'/> Listings<br />';

  $af_tag_descriptors = get_option( 'af_tag_descriptors', 'true' );
  $true_checked = '';
  $false_checked = '';
  if ($af_tag_descriptors == 'true') $true_checked = 'checked="checked"';
  if ($af_tag_descriptors == 'false') $false_checked = 'checked="checked"';

/*
  $af_jumpsearch = get_option( 'af_jumpsearch', 'false' );
  $true_checked = '';
  $false_checked = '';
  if ($af_jumpsearch == 'true') $true_checked = 'checked="checked"';
  if ($af_jumpsearch == 'false') $false_checked = 'checked="checked"';
  echo '<h3>JumpSearch <span style="font-size:0.8em;font-weight:normal">( search using Affinitomics&trade; as context )</span></h3>';
  echo '<input type="radio" name="af_jumpsearch" value="true" '.$true_checked.'/> Yes<br />';
  echo '<input type="radio" name="af_jumpsearch" value="false" '.$false_checked.'/> No<br />';

  $af_google_cse_key = get_option('af_google_cse_key', '');
  echo '<h4>Google&trade; API Key</h4>';
  echo '<input type="text" name="af_google_cse_key" value="'.$af_google_cse_key.'" /> (<a href="https://cloud.google.com/console" target="_new">not sure what this is?</a>)';

  $af_google_cse_id = get_option('af_google_cse_id', '');
  echo '<h4>Google&trade; Custom Search Engine ID</h4>';
  echo '<input type="text" name="af_google_cse_id" value="'.$af_google_cse_id.'" /> (<a href="https://developers.google.com/custom-search/" target="_new">not sure what this is?</a>)';

  $af_jumpsearch_post_type_affinitomics = get_option('af_jumpsearch_post_type_affinitomics');
  $af_jumpsearch_post_type_posts = get_option('af_jumpsearch_post_type_posts');
  $af_jumpsearch_post_type_pages = get_option('af_jumpsearch_post_type_pages');
  $af_jumpsearch_post_type_products = get_option('af_jumpsearch_post_type_products');
  $af_jumpsearch_post_type_projects = get_option('af_jumpsearch_post_type_projects');
  $af_jumpsearch_post_type_listings = get_option('af_jumpsearch_post_type_listings');
  $af_jumpsearch_post_type_affinitomics_checked = '';
  $af_jumpsearch_post_type_posts_checked = '';
  $af_jumpsearch_post_type_pages_checked = '';
  $af_jumpsearch_post_type_products_checked = '';
  $af_jumpsearch_post_type_projects_checked = '';
  $af_jumpsearch_post_type_listings_checked = '';
  if ($af_jumpsearch_post_type_affinitomics == 'true') $af_jumpsearch_post_type_affinitomics_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_posts == 'true') $af_jumpsearch_post_type_posts_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_pages == 'true') $af_jumpsearch_post_type_pages_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_products == 'true') $af_jumpsearch_post_type_products_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_projects == 'true') $af_jumpsearch_post_type_projects_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_listings == 'true') $af_jumpsearch_post_type_listings_checked = 'checked="checked"';
  echo '<h4>Which Pages or Post-types should have a JumpSearch field?</h4>';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_posts" value="true" '.$af_jumpsearch_post_type_posts_checked.'/> Posts<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_pages" value="true" '.$af_jumpsearch_post_type_pages_checked.'/> Pages<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_products" value="true" '.$af_jumpsearch_post_type_products_checked.'/> Products<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_projects" value="true" '.$af_jumpsearch_post_type_projects_checked.'/> Projects<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_listings" value="true" '.$af_jumpsearch_post_type_Listings_checked.'/> Listings<br />';

  $af_jumpsearch_location = get_option( 'af_jumpsearch_location', 'bottom' );
  $top_checked = '';
  $bottom_checked = '';
  if ($af_jumpsearch_location == 'top') $top_checked = 'checked="checked"';
  if ($af_jumpsearch_location == 'bottom') $bottom_checked = 'checked="checked"';
  echo '<h4>Where on Pages or Post-types should the JumpSearch field appear?</h4>';
  echo '<input type="radio" name="af_jumpsearch_location" value="top" '.$top_checked.'/> Top of the Page or Post<br />';
  echo '<input type="radio" name="af_jumpsearch_location" value="bottom" '.$bottom_checked.'/> Bottom of the Page or Post<br />';
*/
  submit_button();
  echo '</form>';
  echo '</div>';

  echo '<hr/>';
  echo '<a href="http://plugins.prefrent.com/"><img src="http://prefrent.com/wp-content/assets/affinitomics-by.png" height="30" width="191"/></a>';
}

function af_jump_search()
{

  $af_key = af_verify_key();
  echo '<div class="wrap">';
  echo '<form method="post" action="options.php">';
  settings_fields( 'af-jumpsearch-settings-group' );
  do_settings_sections( 'af-jumpsearch-settings-group' );

  $af_cloud_url = af_verify_provider();
  af_check_for_errors();
  $af_errors = get_option('af_errors', '');
  $af_error_code = get_option('af_error_code', '');

  if(strlen($af_errors) > 0)
  {
    echo '<h3 style="color:red;font-weight:bold;">----- Warning -----</h3>';
    echo '<p>Error Message: ' . $af_errors . '</p>';
    echo '<p>Error Code: ' . $af_error_code . '</p>';
    echo '<h3 style="color:red;font-weight:bold;">----- Warning -----</h3>';
  }


  $af_jumpsearch = get_option( 'af_jumpsearch', 'false' );
  $true_checked = '';
  $false_checked = '';
  if ($af_jumpsearch == 'true') $true_checked = 'checked="checked"';
  if ($af_jumpsearch == 'false') $false_checked = 'checked="checked"';
  echo '<div class="pure-g">';
  echo '<div class="pure-u-1">';
  echo '<div class="aimojo-tab-header"><h2>Jump Search <span style="font-size:0.8em;font-weight:normal">( <a href="admin.php?page=aimojo-extensions">Back to Extensions Main Panel</a> )</span></h2></div>';
  echo '<div class="pure-u-1 pure-alert" style="padding-top: 16px;">
  <p>Jump Search allows you to combine the power of Google Custom Search Engine (CSE) and Affinitomics&#0153;. It allows CSE to use the Affinitomic elements (Descriptors, Draws, and Distances) of the Page or Post to modify the search results and make them more accurate, based on the context of the page.</p>
  </div>';
  echo '<input type="checkbox" name="af_jumpsearch" value="true" '.$true_checked.'/> Make Jump Search Active<br />';
  /* echo '<input type="radio" name="af_jumpsearch" value="false" '.$false_checked.'/> No<br />'; */

  $af_google_cse_key = get_option('af_google_cse_key', '');
  echo '<h4>Google&trade; API Key</h4>';
  echo '<input type="text" name="af_google_cse_key" value="'.$af_google_cse_key.'" /> (<a href="http://www.prefrent.com/wp-content/uploads/2015/09/cse-creation-walkthrough.pdf" target="_new">How to get this and set it up?</a>)';

  $af_google_cse_id = get_option('af_google_cse_id', '');
  echo '<h4>Google&trade; Custom Search Engine ID</h4>';
  echo '<input type="text" name="af_google_cse_id" value="'.$af_google_cse_id.'" /> (<a href="http://www.prefrent.com/wp-content/uploads/2015/09/cse-creation-walkthrough.pdf" target="_new">not sure what this is?</a>)';

  $af_jumpsearch_post_type_affinitomics = get_option('af_jumpsearch_post_type_affinitomics');
  $af_jumpsearch_post_type_posts = get_option('af_jumpsearch_post_type_posts');
  $af_jumpsearch_post_type_pages = get_option('af_jumpsearch_post_type_pages');
  $af_jumpsearch_post_type_products = get_option('af_jumpsearch_post_type_products');
  $af_jumpsearch_post_type_projects = get_option('af_jumpsearch_post_type_projects');
  $af_jumpsearch_post_type_listings = get_option('af_jumpsearch_post_type_listings');
  $af_jumpsearch_post_type_affinitomics_checked = '';
  $af_jumpsearch_post_type_posts_checked = '';
  $af_jumpsearch_post_type_pages_checked = '';
  $af_jumpsearch_post_type_products_checked = '';
  $af_jumpsearch_post_type_projects_checked = '';
  $af_jumpsearch_post_type_listings_checked = '';
  if ($af_jumpsearch_post_type_affinitomics == 'true') $af_jumpsearch_post_type_affinitomics_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_posts == 'true') $af_jumpsearch_post_type_posts_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_pages == 'true') $af_jumpsearch_post_type_pages_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_products == 'true') $af_jumpsearch_post_type_products_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_projects == 'true') $af_jumpsearch_post_type_projects_checked = 'checked="checked"';
  if ($af_jumpsearch_post_type_listings == 'true') $af_jumpsearch_post_type_listings_checked = 'checked="checked"';
  echo '<h4>Which Pages or Post-types should have a JumpSearch field?</h4>';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_posts" value="true" '.$af_jumpsearch_post_type_posts_checked.'/> Posts<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_pages" value="true" '.$af_jumpsearch_post_type_pages_checked.'/> Pages<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_products" value="true" '.$af_jumpsearch_post_type_products_checked.'/> Products<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_projects" value="true" '.$af_jumpsearch_post_type_projects_checked.'/> Projects<br />';
  echo '<input type="checkbox" name="af_jumpsearch_post_type_listings" value="true" '.$af_jumpsearch_post_type_Listings_checked.'/> Listings<br />';

  $af_jumpsearch_location = get_option( 'af_jumpsearch_location', 'bottom' );
  $top_checked = '';
  $bottom_checked = '';
  if ($af_jumpsearch_location == 'top') $top_checked = 'checked="checked"';
  if ($af_jumpsearch_location == 'bottom') $bottom_checked = 'checked="checked"';
  echo '<h4>Where on Pages or Post-types should the JumpSearch field appear?</h4>';
  echo '<input type="radio" name="af_jumpsearch_location" value="top" '.$top_checked.'/> Top of the Page or Post<br />';
  echo '<input type="radio" name="af_jumpsearch_location" value="bottom" '.$bottom_checked.'/> Bottom of the Page or Post<br />';

  submit_button();
  echo '</form>';
  echo '</div>';
    echo '</div>';
      echo '</div>';
  echo '<hr/>';
  echo '<a href="http://plugins.prefrent.com/"><img src="http://prefrent.com/wp-content/assets/affinitomics-by.png" height="30" width="191"/></a>';

}

function af_register_settings() {
  register_setting('af-settings-group', 'af_cloud_url');
  register_setting('af-settings-group', 'af_domain');
  register_setting('af-settings-group', 'af_key');
  register_setting('af-settings-group', 'af_post_type_affinitomics');
  register_setting('af-settings-group', 'af_post_type_posts');
  register_setting('af-settings-group', 'af_post_type_pages');
  register_setting('af-settings-group', 'af_post_type_products');
  register_setting('af-settings-group', 'af_post_type_projects');
  register_setting('af-settings-group', 'af_post_type_listings');
  register_setting('af-settings-group', 'af_tag_descriptors');

  //keep the jumpsearch settings in their own group (partially because they will appear on a different tab)
  register_setting('af-jumpsearch-settings-group', 'af_jumpsearch');
  register_setting('af-jumpsearch-settings-group', 'af_google_cse_key');
  register_setting('af-jumpsearch-settings-group', 'af_google_cse_id');
  register_setting('af-jumpsearch-settings-group', 'af_jumpsearch_post_type_affinitomics');
  register_setting('af-jumpsearch-settings-group', 'af_jumpsearch_post_type_posts');
  register_setting('af-jumpsearch-settings-group', 'af_jumpsearch_post_type_pages');
  register_setting('af-jumpsearch-settings-group', 'af_jumpsearch_location');

  register_setting('af-settings-group', 'af_errors');
  register_setting('af-settings-group', 'af_error_code');
  register_setting('af-cloud-settings-group', 'af_cloudify');
}


function extraView( $name, array $args = array() )
{
  $args = apply_filters( 'aimojo_view_arguments', $args, $name );

  foreach ( $args AS $key => $val )
  {
    $$key = $val;
  }

  load_plugin_textdomain( 'aimojo' );

  $file = AI_MOJO__PLUGIN_DIR . 'views/'. $name . '.php';

  include( $file );
}

function display_notice()
{
  return;  //ongoing development, this is disabled for now

  // only show notice if we're either a super admin on a network or an admin on a single site
  $show_notice = current_user_can( 'manage_network_plugins' ) || ( ! is_multisite() && current_user_can( 'install_plugins' ) );

  if ( !$show_notice )
    return;

  $af_key = af_verify_key();
  $af_cloud_url = af_verify_provider();

  $dismissed = get_option( 'af_banner_notice_dismissed', '' );
  if ($dismissed != 'true')
  {
    $registerLink = 'http://' . $af_cloud_url . '/users/sign_up?key=' . $af_key;
    $postOptionsUrl = 'admin.php?page=aimojo-basic-settings&dismissNotice=1';
    $bannerImage =  AI_MOJO__PLUGIN_URL . 'images/'. 'register-aimojo-mod.jpg';


    extraView( 'notice', array( 'bannerLink' => $registerLink, 'postOptionsUrl' => $postOptionsUrl, 'bannerImage' => $bannerImage ) );
  }
}




///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////// WORDPRESS TUTORIAL POINTERS /////////////////////////////////////////////////
  function aimojo_admin_scripts()
  {

      // WordPress Pointer Handling
      // find out which pointer ids this user has already seen
      $seen_it = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
      // at first assume we don't want to show pointers
      $show_pointers = false;

      // check for dismissal of aimojo settings menu pointer 'aimojoPointer1'
      if ( !in_array( 'aimojo_pointer1', $seen_it ))
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer1
        add_action( 'admin_print_footer_scripts', 'aimojoPointer1_footer_script' );
      }
      // check for dismissal of aimojo "setup a post..." pointer 'aimojoPointer2'... only want to show this one once pointer1 has been dismissed
      else if ( !in_array( 'aimojo_pointer2', $seen_it ) )
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer2
        add_action( 'admin_print_footer_scripts', 'aimojoPointer2_footer_script' );
      }

    // check for dismissal of aimojo [afview] tag pointer 'aimojoPointer2'
      if ( ! in_array( 'aimojo_pointer3', $seen_it ) )
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer3
        add_action( 'admin_print_footer_scripts', 'aimojoPointer3_footer_script' );
      }

   // check for dismissal of aimojo descriptor box pointer 'aimojoPointer4'
      if ( ! in_array( 'aimojo_pointer4', $seen_it ) )
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer3
        add_action( 'admin_print_footer_scripts', 'aimojoPointer4_footer_script' );
      }

   // check for dismissal of aimojo draws box pointer 'aimojoPointer4'
      if ( ! in_array( 'aimojo_pointer5', $seen_it ) )
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer3
        add_action( 'admin_print_footer_scripts', 'aimojoPointer5_footer_script' );
      }

   // check for dismissal of aimojo distances box pointer 'aimojoPointer4'
      if ( ! in_array( 'aimojo_pointer6', $seen_it ) )
      {
        // flip the flag enabling pointer scripts and styles to be added later
        $show_pointers = true;
        // hook to function that will output pointer script just for aimojoPointer3
        add_action( 'admin_print_footer_scripts', 'aimojoPointer6_footer_script' );
      }


     // enqueue scripts and styles if show_pointers == TRUE
      if ( $show_pointers )
      {
        // add JavaScript for WP Pointers
        wp_enqueue_script( 'wp-pointer' );
        // add CSS for WP Pointers
        wp_enqueue_style( 'wp-pointer' );
      }


    }


    //this pointer points to the aimojo settings menu
    function aimojoPointer1_footer_script()
    {

      $pointer_content = '<h3>Get started using aimojo!</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= '<p>Learn how you can find related posts, and display them to your users, by ';
      $pointer_content .= 'adding draws, distances, and descriptors. </p> ';

      ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {

        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#toplevel_page_aimojo-gstarted').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'left',
              align: 'center'
            },
            close: function()
            {
              result = $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer1',
                action: 'dismiss-wp-pointer'
              });
              console.log(result);
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()


    //this pointer points to the export tab
    function aimojoPointer2_footer_script()
    {
      $pointer_content = '<h3>Set up a post with aimojo!</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= '<p>Add descriptors, draws, and distances to setup relationships with other posts.</p>';
      $pointer_content .= '<p>Then add the [afview] shortcode to find and display related post links to your users. </p>';
      ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {
        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#menu-posts').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'top',
              align: 'left'
            },
            close: function()
            {
              $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer2',
                action: 'dismiss-wp-pointer'
              });
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()


    //this pointer points to the [afview]
    function aimojoPointer3_footer_script()
    {
      $pointer_content = '<h3>Add aimojo relationships!</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= '<p>Add the shortcode [afview] in your post to view other related posts.</p>';
      $pointer_content .= '<p>Be sure to scroll down on the right side of the page';
      $pointer_content .= ' to add draws, distances, and descriptors to your posts. </p>';
      ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {
        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#postdivrich').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'top',
              align: 'center'
            },
            close: function()
            {
              $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer3',
                action: 'dismiss-wp-pointer'
              });
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()


    //this pointer points to the descriptors box
    function aimojoPointer4_footer_script()
    {
      $pointer_content = '<h3>aimojo tip: add descriptors</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= '<p>Add terms that describe your post.</p>';
      $pointer_content .= '<p>A post about cars might include the descriptors: cars, automobiles.</p>';
      ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {
        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#tagsdiv-descriptor').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'right',
              align: 'center'
            },
            close: function()
            {
              $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer4',
                action: 'dismiss-wp-pointer'
              });
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()


    //this pointer points to the draws box
    function aimojoPointer5_footer_script()
    {
      $pointer_content = '<h3>aimojo tip: add draws</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= '<p>Add draws that are related to your descriptors.</p>';
      $pointer_content .= '<p>For example, a post about cars might include the draws: horsepower, nascar, racing, ferrari.</p>';
         ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {
        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#tagsdiv-draw').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'right',
              align: 'center'
            },
            close: function()
            {
              $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer5',
                action: 'dismiss-wp-pointer'
              });
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()


   //this pointer points to the distances box
    function aimojoPointer6_footer_script()
    {
      $pointer_content = '<h3>aimojo tip: add distances</h3>'; // Title should be <h3> for proper formatting.
      $pointer_content .= "<p>Add distances that are things opposite to the draws and descriptors.</p>";
      $pointer_content .= '<p>For example, a post  about cars might include the distances: bicycle, boat, rain.</p>';
      ?>
      <script type="text/javascript">// <![CDATA[
      jQuery(document).ready(function($)
      {
        /* make sure pointers will actually work and have content */
        if(typeof(jQuery().pointer) != 'undefined')
        {
          jQuery('#tagsdiv-distance').pointer(
          {
            content: '<?php echo $pointer_content; ?>',
            position:
            {
              edge: 'right',
              align: 'center'
            },
            close: function()
            {
              $.post( ajaxurl,
              {
                pointer: 'aimojo_pointer6',
                action: 'dismiss-wp-pointer'
              });
            }
          }).pointer('open');
        }
      });
      // ]]></script>
      <?php
    } // end aimojoPointer1_footer_script()







////////////////////// END END END WORDPRESS TUTORIAL POINTERS /////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





/*
----------------------------------------------------------------------
Google Search with Affinitomics
----------------------------------------------------------------------
----------------------------------------------------------------------
Search HTML Produced by Google CSE:
----------------------------------------------------------------------
<div id="af-search">
      <h2>Search Using Affinitomic Profile:</h2>
      <form action="" method="post" name="afsearch">
          <input type="hidden" name="a" id="a" value="%22nokia%22+%22microsoft%22+-%22apple%22+-%22google%22+-%22tim+cook%22">
          <input type="text" name="q" id="q" value="joe">
          <input type="submit">
      </form>
      <ul id="search-content">
        <li><a href="#">result 1</a></li>
        <li><a href="#">result 2</a></li>
        <li><a href="#">result 3</a></li>
        <li><a href="#">result 4</a></li>
        <li><a href="#">result 5</a></li>
        <li><a href="#">result 6</a></li>
        <li><a href="#">result 7</a></li>
      </ul>
  </div>

----------------------------------------------------------------------
  CSS Styling Examples:
----------------------------------------------------------------------
  #af-search h2 {background-color:magenta;}
  #search-content  {background-color:green;}
*/

if (get_option('af_jumpsearch') == 'true') {
  add_filter( 'the_content', 'af_search_content_filter', 20 );
}

// Compare this post type with the user options
function this_page_search_enabled(){
  $this_page_type = get_post_type( get_the_ID() );

  switch ($this_page_type) {
    case 'post':
        return get_option('af_jumpsearch_post_type_posts');
        break;
    case 'page':
        return get_option('af_jumpsearch_post_type_pages');
        break;
    case 'product':
      return get_option('af_jumpsearch_post_type_products');
      break;
    case 'project':
      return get_option('af_jumpsearch_post_type_projects');
      break;
    case 'listing':
      return get_option('af_jumpsearch_post_type_listings');
      break;
  }

}

function af_search_content_filter( $content ) {
  if(this_page_search_enabled()){
    if ( is_singular() ) {
      $cse = '';
      $cse .= '<script>';
      // Search Engine ID
      $cse .= "var cx = '" . get_option('af_google_cse_id') . "';";
      // API Key
      $cse .= "var key = '" . get_option('af_google_cse_key') . "';";
        $q = '';
        if (isset($_REQUEST['q'])) {
          $q = htmlspecialchars(strip_tags($_REQUEST['q']));
          $cse .= 'var q = "' . $q . '";';
        } else {
          $cse .= 'var q = "";';
        }
        $a = '';
        if (isset($_REQUEST['a'])) {
          $a = htmlspecialchars(strip_tags($_REQUEST['a']));
          $cse .= 'var a = "' . $a . '";';
        } else {
          $cse .= 'var a = "";';
        }
      $cse .= '</script>';

      $post_id = get_the_ID();

      // Collect descriptor terms from the post
      $these_descriptors = wp_get_post_terms( $post_id, "descriptor" );
      $descriptor_terms = array();

      foreach ($these_descriptors as $descriptor) {
        array_push($descriptor_terms, $descriptor->name);
      }

      // Collect draws, find the highest draw
      $best_draw = "";
      $best_draw_num = 0;

      $these_draws = wp_get_post_terms( $post_id, "draw" );
      $draw_terms = array();
      foreach ($these_draws as $draw) {
        $this_weight = substr($draw->name, -1);
        if (is_numeric($this_weight)){
          if ($this_weight > 1) {
            if ( $this_weight > $best_draw_num ) {
              $best_draw = preg_replace("/[0-9]/", "", $draw->name);
              $best_draw_num = $this_weight;
            }
          }
        }
        else {
          $draw->name = preg_replace("/[0-9]/", "", $draw->name);
          array_push($draw_terms, $draw->name);
        }
      }

      // Find the best distance or use the first one
      $best_distance = "";
      $best_distance_num = 0;

      $these_distances = wp_get_post_terms( $post_id, "distance" );
      $distance_terms = array();
      foreach ($these_distances as $distance) {
        $this_weight = substr($distance->name, -1);
        if (is_numeric($this_weight)){
          if ( $this_weight > $best_distance_num ) {
            $best_distance = preg_replace("/[0-9]/", "", $distance->name);
            $best_distance_num = $this_weight;
          }
        }
        else {
          $distance->name = preg_replace("/[0-9]/", "", $distance->name);
          array_push($distance_terms, $distance->name);
        }
      }

      if (count($descriptor_terms) > 0){
        $descriptors_meta = $descriptor_terms[0];
      } else {
        $descriptors_meta = "";
      }

      if($best_draw != ""){
        $draw_meta = $best_draw;
      } else if (count($draw_terms) > 0){
        $draw_meta = $draw_terms[0];
      } else {
        $draw_meta = "";
      }

      if($best_distance != ""){
        $distance_meta = '-' . $best_distance;
      } else if (count($distance_terms) > 0){
        $distance_meta = '-' . $distance_terms[0];
      } else {
        $distance_meta = "";
      }

      // Use Taxonomy Data to Build Affinitomic Search String
      $affinitomics = '';
      if ($descriptors_meta != '') {
        $affinitomics = $descriptors_meta;
      }
      if ($draw_meta != '') {
        if ($affinitomics == '') {
          $affinitomics = $draw_meta;
        } else {
          $affinitomics .= ', ' . $draw_meta;
        }
      }
      if ($distance_meta != '') {
        if ($affinitomics == '') {
          $affinitomics = $distance_meta;
        } else {
          $affinitomics .= ', ' . $distance_meta;
        }
      }

      if ($affinitomics != '') {
        $cse .= '<div>&nbsp;</div>';
        $cse .= '<div id="af-search">';
        $cse .= '<h2>Search Using Affinitomic Profile:</h2>';
        $cse .= '<form action="" method="post" name="afsearch">';
        $cse .= '<input type="hidden" name="a" id="a" value="' . $affinitomics .'" />';
        $cse .= '<input type="text" name="q" id="q" value="'. $q . '"/> ';
        $cse .= '<input type="submit"/>';
        $cse .= '</form><br />';
        $cse .= '<ul id="search-content"></ul>';
      }

      if (isset($_REQUEST['q'])) {
        /*
        <script>
            function gcs(response) {
              //console.log(JSON.stringify(response.searchInformation));
              if ((typeof response != 'undefined') && (response.searchInformation.totalResults > 0)){
                for (var i = 0; i < response.items.length; i++) {
                    var item = response.items[i];
                    document.getElementById("search-content").innerHTML += "<li><a href='" + item.link + "'>" + item.htmlTitle + "</a></li>";
                }
              } else {
                    document.getElementById("search-content").innerHTML += "<li>No results found.</li>";
              }
            }
            document.write("<script src='"+"https://www.googleapis.com/customsearch/v1?key="+key+"&cx="+cx+"&q="+q+" "+a+"&callback=gcs"+"'><\/script>");
        </script>
        */
        $cse .= "<script>\n";
        $cse .= "function gcs(response) {\n";
        $cse .= "//console.log(JSON.stringify(response.searchInformation));\n";
        $cse .= "if ((typeof response != 'undefined') && (response.searchInformation.totalResults > 0)){\n";
        $cse .= "for (var i = 0; i < response.items.length; i++) {\n";
        $cse .= "var item = response.items[i];\n";
        $cse .= "document.getElementById(\"search-content\").innerHTML += \"<li><a href='\" + item.link + \"'>\" + item.htmlTitle + \"</a></li>\";\n";
        $cse .= "}\n";
        $cse .= "} else {\n";
        $cse .= 'document.getElementById("search-content").innerHTML += "<li>No results found.</li>";';
        $cse .= "}\n";
        $cse .= "}\n";
        $cse .= "document.write(\"<script src='\"+\"https://www.googleapis.com/customsearch/v1?key=\"+key+\"&cx=\"+cx+\"&q=\"+q+\" \"+a+\"&callback=gcs\"+\"'><\/sc\"+\"ript>\");\n";
        $cse .= "</script>\n";
      }
      $cse .= '</div><!-- af-search -->';

      $modified_content = '';
      if (get_option('af_jumpsearch_location') == 'top') $modified_content .= $cse;
      $modified_content .= $content;
      if (get_option('af_jumpsearch_location') == 'bottom') $modified_content .= $cse;
      return $modified_content;
    }
  }
  return $content;
}
/*
End Affinitomics Commercial Code
*/

// CURL Request Function
function curl_request($url,$postdata=false) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLINFO_HEADER_OUT, false);
  curl_setopt($ch, CURLOPT_VERBOSE, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  if ($postdata) {
    //urlify the data for the POST
    $fields_string .= rawurlencode("ctype") .'='.rawurlencode(AI_MOJO__TYPE).'&' . rawurlencode("cversion") .'='.rawurlencode(AI_MOJO__VERSION).'&';
    foreach($postdata as $key=>$value) { $fields_string .= rawurlencode($key).'='.rawurlencode($value).'&'; }
    rtrim($fields_string, '&');
    curl_setopt($ch,CURLOPT_POST, count($postdata));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
  }
  $response = curl_exec($ch);
  curl_close($ch);
  return $response;
}

function aimojo_html_header()
{
  ?>

<link rel="stylesheet" href="http://yui.yahooapis.com/pure/0.6.0/pure.css">

  <?php
}

function aimojo_html_footer()
{

}

/***********************************************************************************
* Admin Menu Section for aimojo
*
*
***********************************************************************************/

function aimojo_admin_menu() {
add_menu_page(
  'aimojo',                     //$page_title
  'aimojo',                     //$menu_title
  'manage_options',             //$capability
  'aimojo-gstarted',               //$menu_slug
  'gstarted_content',              //$function
  plugins_url( 'affinitomics-favicon.svg', __FILE__ )  //OPTIONAL:  $icon_url
                                //OPTIONAL:  $position
  );

add_submenu_page(
  'aimojo-gstarted',
  'settings',
  'settings',
  'manage_options',
  'aimojo-basic-settings',
  'basic_settings_content'
  );
add_submenu_page(
  'aimojo-gstarted',
  'extensions',
  'extensions',
  'manage_options',
  'aimojo-extensions',
  'extensions_content'
  );
/*
add_submenu_page(
  'aimojo-gstarted',
  'css',
  'css',
  'manage_options',
  'aimojo-css',
  'css_content'
  );
*/
add_submenu_page(
  'aimojo-gstarted',               //$parent_slug
  'export',                     //$page_title
  'export',                     //$menu_title
  'manage_options',             //$capability
  'aimojo-export-tab',          //$menu_slug
  'export_tab'                  //$function
  );
add_submenu_page(
  'aimojo-gstarted',
  'documentation',
  'documentation',
  'manage_options',
  'aimojo-documentation',
  'documentation_content'
  );
add_submenu_page(
  'aimojo-gstarted',
  'credits',
  'credits',
  'manage_options',
  'aimojo-credits',
  'credits_content'
  );
add_submenu_page(
  'aimojo-gstarted',               //$parent_slug
  'smart search',               //$page_title
  'smart search',               //$menu_title
  'manage_options',             //$capability
  'aimojo-ext-smart-search',    //$menu_slug
  'ext_smart_search_content'    //$function
  );

  aimojo_sc_generator_menu();

  add_action( 'admin_init', 'af_register_settings' );

}
/**
Welcome page for new features and promotions
*/
function gstarted_content() {
  ?>
		<div style="padding: 20px; background-color: #C6E1EC;" id="gstartedpanel" class="wrap">
			<div style="width: 99%; height: 120px; display: inline-block;"><p class="af-hero-headline">Getting Started!</p></div>
			<div style="width: 45%; min-width: 400px; display: inline-block; vertical-align:top;">
			<p class="af-hero">In a few short minutes, your site will be able to Match, Rank, and Relate information in posts, pages, and custom post-types better than ever before. You'll experience "stickier" sites that convert better and faster, while providing rich and relevant links for SEO.</p>
			<!-- We realize that adding an AI layer to your Wordpress site
			sounds daunting, and that even if we break it down into three easy steps,
			there's a chance you might not follow along... so we made a movie. Watch it,
			and you'll never want to use tags again. -->
			</div>

			<div style="width: 45%; min-width: 400px; display: inline-block;">
				<iframe src="https://player.vimeo.com/video/139483521" width="500" height="313" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe> <p><a href="https://vimeo.com/139483521">aimojo-crash-course</a> from <a href="https://vimeo.com/user43935295">Prefrent</a> on <a href="https://vimeo.com">Vimeo</a>.</p>
			</div>
	</div>



    <h2 class="nav-tab-wrapper">
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" id="aimojo-export-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>

	<div class="pure-g">

<!-- http://tympanus.net/codrops/2012/02/21/accordion-with-css3/ -->

<section class="ac-container">
	<div class="pure-u-1">
		<input id="ac-1" name="accordion-1" type="radio" checked/>
		<label for="ac-1">Step One – Connect Affinitomics&#0153; to Your System</label>
		<article class="ac-large">
			<table class="pure-table" style="border: none!important;">
			<tr>
			<td width="65%">
			<p>Using ai&#8226;mojo&#0153; to create Affinitomics&#0153; within your system will give your pages, posts and
			custom post-types a "self awareness," instructing them of their context within your
			system. ai&#8226;mojo&#0153; needs to know where you want it to connect into your system – which things
			you want to make smart. In the ai&#8226;mojo&#0153; "settings" tab, start by selecting "post." You can
			select any other's you want later, but this is the best way to get started. Now when
			posts are saved in your system, the Affinitomic elements will be saved to the cloud,
			making your site smarter.
			</p>
			</td>
			<td width="25%">
			<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/select-posts.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/select-posts-small.jpg'?>"width="260px"></a>
			</td>
			</tr>
			</table>
		</article>
	</div>
	<div class="pure-u-1">
		<input id="ac-2" name="accordion-1" type="radio" />
		<label for="ac-2">Step Two – Creating Affinitomic Archetypes</label>
		<article class="ac-xlarge">
			<table class="pure-table" style="border: none!important;">
			<tr>
			<td width="65%">
			<p>Now you're ready to make your first "Archetype." An Archetype is any post-type that
			has Affinitomic elements so that it can interact, contextually, with other parts of your
			site. Go to your posts menu and choose a post to edit or create a new one. In the
			<strong>Descriptor</strong> Field put some terms describing your post. In the Draw
			field, put terms that somehow relate to your post. These are like tags (you can even use
			your tags if you want).

			Distances tell the system what the post or page should be disassociated with.

			At the bottom of your post content, put <strong>[afview]</strong> (you can use the
			shortcode in widgets now too). This shortcode creates an Affinitomic "Smart Menu." You'll
			need other pages and posts to have descriptors and draws for <strong>[afview]</strong> to
			work. Be sure to save your post.
			</p>
			</td>
			<td width="25%">
			<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/enter-descriptors.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/enter-descriptors-small.jpg'?>"width="260px"></a>
			</td>
			</tr>
			</table>
		</article>
	</div>
	<div class="pure-u-1">
		<input id="ac-3" name="accordion-1" type="radio" />
		<label for="ac-3">Step Three – See How “Smart Menus” Show Matching Items</label>
		<article class="ac-large">
			<table class="pure-table" style="border: none!important;">
			<tr>
			<td width="45%">
			<p>Make another Archetype, as in <strong>Step Two.</strong> In the <strong>Descriptor</strong> or draw field, be sure to repeat at least one of the Descriptors you created previously. Insert an <strong>[afview]</strong> shortcode, save the post, and preview it.

			Notice that an Affinitomics&#0153;’ "Smart Menu" was created where you inserted the <strong>[afview]</strong> shortcode, and that the previous article shows with a score of the matching terms. This is the most basic Affinitomic relationship, and it can already be used to replace both categories and tags.
			</p>
			</td>
			<td width="20%">
			<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/add-another.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/add-another-small.jpg'?>" width="200px"></a>
			</td>
			<td width="20%">
			<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/results.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/results-small.jpg'?>"width="200px"></a>
			</td>
			</tr>
			</table>

		</article>
	</div>
	</section>

    </div>
   </div>

  </div>
  <?php
}

/**
Basic settings - API key, registration/ dashboard link and detected / attached poste types for archetypes
*/
function basic_settings_content() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Settings</h2>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>
  </div>

  <?php
   af_plugin_options();   //the af_plugin_options function is responsible for displaying the majority of the basic settings that will be shown on this tab
 }
/**
Both internal and third party extensions will be accessed here - while each will get a submenu page, these will be hidden
*/
function extensions_content() {
  aimojo_html_header();
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Extensions</h2>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>

<!-- Grid for extensions uses PURECSS -->
<div class="pure-g">
	<div class="aimojo-tab-header pure-u-24-24"><h2>A growing library of ai&#8226;mojo&#0153; extensions!</h2></div>
</div>
<div class="pure-g">
<div class="pure-u-1 pure-alert">The extensions panel lets you use and configure optional
features and tools that make ai&#8226;mojo&#0153; even more powerful and easy to use. It
also serves as a roadmap for future development. To learn more about individual extensions, click the
"Extension Settings" link in each one.</div>
</div>
<!-- Here comes an extension! -->

<!-- Jump Search Extension -->

	<div class="pure-u-5-24">
	<table class="pure-table">
	<tr>
		<td colspan="3">
		<div class="ext-aim-top">
			<img class="ext-image pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/jump-search.png'?>"><!-- aspect ratio 200px x 130px -->
		</div>
		</td>
	</tr>
	<tr>
		<td colspan="3">
		<div class="ext-aim-mid">
			<span class="ext-panel-name">Jump Search</span>
			<p class="ext-panel-description">Jump Search combines Google CSE and Affinitomics&#0153; for incredibly contextual searches. Requires a free Google API key, <strong>can be configured in less than five minutes.</strong></p>
		</div>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<div class="ext-aim-foot">
			<a href="<?php echo admin_url() ?>admin.php?page=aimojo-ext-smart-search">Extension Settings</a>
			</div>
		</td>
		<!-- <td><button class="button-ontoggle pure-button">ON</button>
		</td> -->
	</tr>
	</table>
	</div>

	<?php // Extensions added via the extensions directory
      /** Shortcode Generator */
      include( plugin_dir_path( __FILE__ ) . 'extensions/shortcode_generator/sc_generator.php');

			/** Style Smart Menu Extension */
			include( plugin_dir_path( __FILE__ ) . 'extensions/style_smart_menus/style_smart_menu_summary.php');

			/** Automagical Extension */
			include( plugin_dir_path( __FILE__ ) . 'extensions/automagical/automagical.php');

	?>

</div>
  <?php
}
/**
aimojo documentation
*/
function documentation_content() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Documentation</h2>

    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
     <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>

		<div class="pure-g">
			<div class="aimojo-tab-header pure-u-24-24"><h2>Quick Documentation</h2></div>
		</div>
		<div class="pure-g">
		<div class="pure-u-1 pure-alert">
		The documentation found here is a basic guide. For more in-depth documentation please
		go to <a href="http://prefrent.com/support">Support at Prefrent.com</a>. <strong>
		For a printable "Cheat Sheet" <a href="http://www.prefrent.com/wp-content/uploads/2015/10/aimojo-cheat-sheet.pdf" target="blank">
		go here</strong>.</a>
		</div>

			<!-- http://tympanus.net/codrops/2012/02/21/accordion-with-css3/ -->

			<section class="ac-container">
				<div class="pure-u-1">
					<input id="ac-5" name="accordion-1" type="radio" checked/>
					<label for="ac-5">Glossary</label>
					<article class="ac-large">
						<table class="pure-table">
						<tr class="pure-table-odd">
							<td><strong>Archetype</strong></td>
							<td>Any page or post-type (any piece of information really) that contains one or more descriptors, draws, or distances such that the information can be intelligently compared to other archetypes.</td>
						</tr>
						<tr>
							<td><strong>Descriptor</strong></td>
							<td>A descriptor is similar to a tag, but is used to describe whatever it is attached to.
							It's not uncommon for a page to have a descriptor of "web page." A page about cars might include the descriptors "page, cars, automobiles."</td>
						</tr>
						<tr class="pure-table-odd">
							<td><strong>Draw</strong></td>
							<td>A draw is also similar to a tag, but is used to record what the descriptors are related to, or co-occur with.
							A page about cars might include the draws "horsepower, nascar, racing, gear-head."</td>
						</tr>
						<tr>
							<td><strong>Distance</strong></td>
							<td>These are the opposites of draws and indicate things that oppose the descriptors or draws attached to an archetype.
							A page about cars might include the distances "bicycle, boat, rain."</td>
						</tr>
							<tr>
							<td><strong>Smart List</strong></td>
							<td>A list created with <code>[afview]</code> shortcode, displaying Affinitomic relationships sorted by contextual match.</td>
						</tr>
						</table>
					</article>
				</div>
				<div class="pure-u-1">
					<input id="ac-6" name="accordion-1" type="radio" />
					<label for="ac-6">Important Wordpress Settings</label>
					<article class="ac-medium">
						<table class="pure-table" style="border: none!important;">
						<tr>
						<td width="65%">The first thing you'll want to do, is make sure your
						<a href="<?php echo get_site_url() . '/wp-admin/options-permalink.php' ?>" target="blank">"permalinks"</a>
						are set how you want them. aimojo exports to the cloud with a URI based
						on your permalinks. If they change, your Affinitomic™ links will be broken.
						Resetting them currently requires registering a new account.
						</td>
						<td width="25%">
						<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/permalinks-big.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/permalinks-small.jpg'?>"></a>
						</td>
						</tr>
						</table>
					</article>
				</div>

				<div class="pure-u-1">
					<input id="ac-7" name="accordion-1" type="radio" />
					<label for="ac-7">Shortcode Reference</label>
					<article class="ac-large">
						<ul style="padding: 20px;">
						<li><strong>[afview]</strong> This tells Affinitomics&#0153; to build a dynamic menu list. Without other parameters, it uses the Affinitomics&#0153; of the page it resides on to create a menu list of the top related items in the cloud.</li>
						<li><strong>[afview display_title="false"]</strong> This was a result of a request to be able to hide the hard coded title.</li>
						<li><strong>[afview title="title"]</strong> Replaces the default title with whatever you want.</li>
						<li><strong>[afview category_filter="50"]</strong> or <strong>[afview category_filter="name"]</strong> This short code tells Affinitomics&#0153; to build a menu list based on the Affinitomics&#0153; of the page, but to restrict the list to a particular category.</li>
						<li><strong>[afview limi="7"]</strong> This short code tells Affinitomics&#0153; to build a menu with a limit of 7 links. Default is ten.</li>
						</ul>
					</article>
				</div>
				<div class="pure-u-1">
					<input id="ac-8" name="accordion-1" type="radio" />
					<label for="ac-8">Quick Integration Tips - Cloud Export</label>
					<article class="ac-large">
						<table class="pure-table" style="border: none!important;">
						<tr>
						<td width="65%">
						You may want to use the posts list to create numerous Archetypes at once,
						or you may have imported the sample post Archetypes. In either case, you'll
						want to upload All your Affinitomics&#0153; to the cloud. Simply go to the "Export"
						tab above, check "make it so" and click the export button. Bam! After a
						couple of seconds, all your Archetypes will be registered and ready to use.
						</td>
						<td width="25%">
						<a href="<?php echo AI_MOJO__PLUGIN_URL . 'images/export.jpg'?>" target="blank"><img class="pure-img" src=" <?php echo AI_MOJO__PLUGIN_URL . 'images/export-small.jpg'?>"></a>
						</td>
						</tr>
						</table>
					</article>
				</div>
				<div class="pure-u-1">
					<input id="ac-9" name="accordion-1" type="radio" />
					<label for="ac-9">Styling Affinitomics</label>
					<article class="ac-large">
					<div style="padding: 20px">The smart lists available via Affinitomics&#0153; shortcodes can be styled to suit your site. Each element of <code>[afview]</code> has an available selector:
						<ul style="padding: 20px;">
						<li><b>afview: </b> this element is attached to the div that the list appears in</li>
						<li><b>aftitle: </b>this class targets the title</li>
						<li><b>afelement: </b> this class allows you to style the list items</li>
						<li><b>afelementurl: </b>this selector targets the link</li>
						<li><b>afelementscore: </b>this selector targets the number that appears after any given element - the score</li>
						</ul>
					</div>
					</article>
				</div>
				<div class="pure-u-1">
					<input id="ac-10" name="accordion-1" type="radio" />
					<label for="ac-10">Troubleshooting</label>
					<article class="ac-medium">
						<div style="padding: 20px;">
							<ul>
							<li><b>The shortcode is on the page, but it doesn't show a smart list.</b> Make sure that you have exported all your pages and posts to the Affinitomics&#0153; cloud.</li>
							<li><b>A warning at the top of the page indicates that you're over your limit.</b> Register (it's free) and your limit will increase, if that's not enough you can always upgrade your account.</li>
							</ul>
						</div>
					</article>
				</div>
				<div class="pure-u-1">
					<input id="ac-11" name="accordion-1" type="radio" />
					<label for="ac-11">Sample Code</label>
					<article class="ac-small">
						<div style="padding: 20px;">
							<ul>
							<li><b>This spot will be a repository for featuered sample code from our friends and customers. Keep your eyes peeled.</li>
							<li><b>You can find other sample code <a href="http://prefrent.com/Support">here</a>.</li>
							</ul>
						</div>
					</article>
				</div></div>
				</section>
			</div>
  </div>
  <?php
}
/**
CSS options will be shown here unless they are a more complex layout extension
*/
function css_content() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; CSS</h2>

    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-css">CSS</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
     <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>
    <p>This page will have a form that allows the user to control certain CSS and layout elements</p>
  </div>
  <?php
}
/** Export
*/
function export_tab() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Export</h2>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>

  <?php
    af_plugin_export();
}
/** Credits & mentions page
*/
function credits_content() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Credits</h2>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>
    <div class="pure-g">
		<div class="pure-u-1">
			<ul style="padding: 20px; font-size: 130%">
			<li>We'd like to thank our infrastructure partners and the people we rely on to make this business work. This is not a paid plug.</li>
			<li>We love the people at <a href="http://pressable.com" target="blank">Pressable.com</a>. If you have a blog that needs hosting - start there.</li>
			<li>For bigger projects that require business-class hosting or Wordpress Multisite, <a href="http://wpengine.com" target="blank">WPEngine.com</a> can't be beat. </li>
			<li>We rely on <a href="http://stripe.com" target="blank">Stripe</a> for payment processing and subscriptions - we couldn't SaaS without them.</li>
			<li>We test the heck out of things at <a href="http://heroku.com" target="blank">Heroku</a>. Their solid support let's us get our work done without a huge IT spend.</li>
			<li>--------</li>
			<li>This plugin is the tireless work of Erik Hutchinson, Rob Hust, and the rest of the crew at Prefrent. All you developers out there, be sure to check out the Affinitomics API for creating your own tools to harness the power of Affinitomics!
			</li>
			</ul>
		</div>
  </div>
  <?php
}
/**
Smart Search extensions will be accessed here - function to hide submeny page from menu below...
*/
function ext_smart_search_content() {
  ?>
  <div class="wrap">
    <h2>ai&#8226;mojo&#0153; Jump Search Extension</h2>
    <h2 class="nav-tab-wrapper">
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-gstarted">Getting Started</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-basic-settings">Settings</a>
      <a class="nav-tab nav-tab-active" href="<?php echo admin_url() ?>admin.php?page=aimojo-extensions">Extensions</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-export-tab">Export</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-documentation">Documentation</a>
      <a class="nav-tab" href="<?php echo admin_url() ?>admin.php?page=aimojo-credits">Credits</a>
    </h2>
</div>
  <?php
  af_jump_search();
}
function ext_short_code_generation() {

  ?>
  <?php

  ext_sc_generator_content();
}

/**
Will add the functions to remove the extension menu pages here... But need them while we solve the permissions bug.
*/
?>
<?php
	// Incredibly complex bit of code to allow shortcodes to work in widget areas...

	add_filter( 'widget_text', 'shortcode_unautop' );
	add_filter( 'widget_text', 'do_shortcode' );
?>

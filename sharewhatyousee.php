<?php
/*
Plugin Name: Share What You See
Plugin URI: https://github.com/mialondon/Share-what-you-see
Description: WordPress plugin to add results from Europeana search for interesting things seen in museums etc to a blogpost
Version: 0.3
Author: Mia Ridge, Owen Stephens
License: GPL2

*/

/*

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

/////////// set up activation and deactivation stuff
register_activation_hook(__FILE__,'sharewhatyousee_install');

function sharewhatyousee_install() {
  // do stuff when installed
  global $wp_version;
  if (version_compare($wp_version, "3", "<")) {
    deactivate_plugins(basename(__FILE__)); // deactivate plugin
    wp_die("This plugin requires WordPress Version 3 or higher.");
    // also requires mmg for the tables, or should test for existence of mmg main plugin
    // also relies on curl so check for it 
  } else {
    // create the tables if mmg main plugin hasn't already been installed
    // ### to do
 }
}


register_deactivation_hook(__FILE__,'sharewhatyousee_uninstall');

function sharewhatyousee_uninstall() {
  // do stuff
}


/////////// set up option storing stuff
// create array of options
$sharewhatyousee_options_arr=array(
  "sharewhatyousee_Europeana_API_key"=>'',
  "sharewhatyousee_Europeana_API_URL"=>'',
  );

// store them
update_option('sharewhatyousee_plugin_options',$sharewhatyousee_options_arr); 

// get them
$sharewhatyousee_options_arr = get_option('sharewhatyousee_plugin_options');

// use them. 
$sharewhatyousee_Europeana_API_key = $sharewhatyousee_options_arr["sharewhatyousee_Europeana_API_key"];
$sharewhatyousee_Europeana_API_URL = $sharewhatyousee_options_arr["sharewhatyousee_Europeana_API_URL"];
// end option array setup

// required in WP 3 but not earlier?
add_action('admin_menu', 'sharewhatyousee_plugin_menu');

/////////// set up stuff for admin options pages
// add submenu item to existing WP menu
function sharewhatyousee_plugin_menu() {
add_options_page('Share what you see settings page', 'Share what you see settings', 'manage_options', __FILE__, 'sharewhatyousee_settings_page');
}

// call register settings function before admin pages rendered
add_action('admin_init', 'sharewhatyousee_register_settings');

function sharewhatyousee_register_settings() {
  // register settings - array, not individual
  register_setting('sharewhatyousee-settings-group', 'sharewhatyousee_settings_values');
}

// write out the plugin options form. Form field name must match option name.
// add other options here as necessary e.g. new API URLs or updated defaults
function sharewhatyousee_settings_page() {
  
  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  ?>
  <div>
  <h2><?php _e('share what you see plugin options', 'sharewhatyousee-plugin') ?></h2>
  <form method="post" action="options.php">
  <?php settings_fields('sharewhatyousee-settings-group'); ?>

  <?php _e('Europeana API key','sharewhatyousee-plugin') ?> 
  
  <?php sharewhatyousee_Europeana_API_key(); ?><br />

  <?php _e('Europeana API search URL','sharewhatyousee-plugin') ?> 
  
  <?php sharewhatyousee_Europeana_API_URL(); ?><br />

  <p class="submit"><input type="submit" class="button-primary" value=<?php _e('Save changes', 'sharewhatyousee-plugin') ?> /></p>
  </form>
  </div>
  <?php
}

// get options from array and display as fields

function sharewhatyousee_Europeana_API_URL() {
  // load options array
  $sharewhatyousee_options = get_option('sharewhatyousee_settings_values');
  
  $sharewhatyousee_Europeana_API_URL = $sharewhatyousee_options['sharewhatyousee_Europeana_API_URL'];
  
  // display form field
  echo '<input type="text" name="sharewhatyousee_settings_values[sharewhatyousee_Europeana_API_URL]" 
  value="'.esc_attr($sharewhatyousee_Europeana_API_URL).'" />';
}

function sharewhatyousee_Europeana_API_key() {
  // load options array
  $sharewhatyousee_options = get_option('sharewhatyousee_settings_values');
  
  $sharewhatyousee_Europeana_API_key = $sharewhatyousee_options['sharewhatyousee_Europeana_API_key'];
  
  // display form field
  echo '<input type="text" name="sharewhatyousee_settings_values[sharewhatyousee_Europeana_API_key]" 
  value="'.esc_attr($sharewhatyousee_Europeana_API_key).'" />';
}


/*
 * set up shortcode Sample: [sharewhatyousee]
 *
 * Initial code only at this stage to get stuff onto github, will need to tweak form
 * to use the right fields for SWYS
 * 
 */
function ShareWhatYouSeeShortCode($atts, $content=null) {
  
  if(@is_file(ABSPATH.'/wp-content/plugins/sharewhatyousee/sharewhatyousee_functions.php')) {
      include_once(ABSPATH.'/wp-content/plugins/sharewhatyousee/sharewhatyousee_functions.php'); 
  }
    
  $search_terms = stripslashes($_POST['search_term']); // the free-text search field
  $search_title = stripslashes($_POST['search_title']); // the free-text search field
  $search_venue = stripslashes($_POST['search_venue']); // the free-text search field
  $search_sources = 'Europeana'; // which target APIs are being searched? Can be set in form but for now fake it as Europeana; default should really be all (if it's blank)
  
  if(!empty($search_terms) || !empty($search_venue) || !empty($search_title)) {
    // process - deal with search, display results and import into db
    echo '<p>Searching now...</p>';  
    SWYSGetEuropeanaSearchResults($search_terms,$search_title,$search_venue,'import',$search_sources);
    } else {
    // display search box and instructions
    SWYSPrintSearchForm();
  }

}

// Add the shortcode
add_shortcode('sharewhatyousee', 'ShareWhatYouSeeShortCode');

?>
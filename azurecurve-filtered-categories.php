<?php
/*
Plugin Name: azurecurve Filtered Categories
Plugin URI: http://development.azurecurve.co.uk/plugins/tag-cloud/

Description: Creates a new Categories sidebar widget which allows categories to be included/excluded. A link to a categories page listing all categories can be configured to be displayed; a shortcode [fc] can be used on this page to display categories list.
Version: 2.0.2

Author: azurecurve
Author URI: http://development.azurecurve.co.uk/

Text Domain: azc_fc
Domain Path: /languages

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

The full copy of the GNU General Public License is available here: http://www.gnu.org/licenses/gpl.txt
 */

//include menu
require_once( dirname(  __FILE__ ) . '/includes/menu.php');

function azc_fc_load_plugin_textdomain(){
	$loaded = load_plugin_textdomain( 'azc_fc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	//if ($loaded){ echo 'true'; }else{ echo 'false'; }
}
add_action('plugins_loaded', 'azc_fc_load_plugin_textdomain');

function azc_fc_load_css(){
	wp_enqueue_style( 'azc_fc', plugins_url( 'style.css', __FILE__ ) );
}
add_action('admin_enqueue_scripts', 'azc_fc_load_css');

function azc_fc_set_default_options($networkwide) {
	
	$new_options = array(
				'use_network_settings' => 1,
				'taxonomy' => 'category',
				'category_page' => '',
				'category_link' => '',
				'category_page_count' => 0,
				'category_page_feed_image' => '',
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()) {
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide) {
			global $wpdb;

			$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
			$original_blog_id = get_current_blog_id();

			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );

				if ( get_option( 'azc_fc' ) === false ) {
					add_option( 'azc_fc', $new_options );
				}
			}

			switch_to_blog( $original_blog_id );
		}else{
			if ( get_option( 'azc_fc' ) === false ) {
				add_option( 'azc_fc', $new_options );
			}
		}
		if ( get_site_option( 'azc_fc' ) === false ) {
			add_site_option( 'azc_fc', $new_options );
		}
	}
	//set defaults for single site
	else{
		if ( get_option( 'azc_fc' ) === false ) {
			add_option( 'azc_fc', $new_options );
		}
	}
}
register_activation_hook( __FILE__, 'azc_fc_set_default_options' );

function azc_fc_plugin_action_links($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=azc-fc">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}
add_filter('plugin_action_links', 'azc_fc_plugin_action_links', 10, 2);

function azc_create_fc_plugin_menu() {
	global $admin_page_hooks;
	
	add_submenu_page( "azc-plugin-menus"
						,"Filtered Categories"
						,"Filtered Categories"
						,'manage_options'
						,"azc-fc"
						,"azc_fc_settings" );
}
add_action("admin_menu", "azc_create_fc_plugin_menu");

/*
function azc_fc_settings_menu() {
	add_options_page( 'azurecurve Filtered Categories Settings',
	'azurecurve Filtered Categories', 'manage_options',
	'azc_fc', 'azc_fc_config_page' );
}
add_action( 'admin_menu', 'azc_fc_settings_menu' );
*/

function azc_fc_settings() {
	if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'azc_fc'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option( 'azc_fc' );
	?>
	<div id="azc-tc-general" class="wrap">
		<fieldset>
			<h2><?php ECHO 'azurecurve Filtered Categories '.__('Settings', 'azc_fc'); ?></h2>
			<?php if(isset($_GET['settings-updated'])) { ?>
				<div id="message" class="updated">
					<p><strong><?php _e('Site settings have been saved.') ?></strong></p>
				</div>
			<?php } ?>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="save_azc_fc" />
				<input name="page_options" type="hidden" value="taxonomy" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field( 'azc_fc_nonce', 'azc_fc_nonce' ); ?>
				<table class="form-table">
				<tr><th scope="row"><label for="include_exclude"><?php _e('Include/Exclude Categories?', 'azc_fc'); ?></label></th><td>
					<select name="include_exclude">
						<option value="include" <?php if($options['include_exclude'] == 'include'){ echo ' selected="selected"'; } ?>>Include</option>
						<option value="exclude" <?php if($options['include_exclude'] == 'exclude'){ echo ' selected="selected"'; } ?>>Exclude</option>
					</select>
					<p class="description"><?php _e('Flag whether marked categories should be included or excluded', 'azc_fc'); ?></p>
				</td></tr>
				
				<tr><th scope="row">Categories to Include/Exclude</th><td>
					<div class='azc_fc_scrollbox'>
						<?php
							global $wpdb;
							$query = "SELECT t.term_id AS `term_id`, t.name AS `name` FROM $wpdb->term_taxonomy tt INNER JOIN $wpdb->terms t On t.term_id = tt.term_id WHERE tt.taxonomy = 'category' ORDER BY t.name";
							$_query_result = $wpdb->get_results( $query );
							foreach( $_query_result as $data ) {
								?>
								<label for="<?php echo $data->term_id; ?>"><input name="category[<?php echo $data->term_id; ?>]" type="checkbox" id="category" value="1" <?php checked( '1', $options['category'][$data->term_id] ); ?> /><?php echo $data->name; ?></label><br />
								<?php
							}
							unset( $_query_result );
						?>
					</div>
					<p class="description"><?php _e('Mark the tags you want to include/exclude from the categories', 'azc_fc'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="category_page"><?php _e('Category Page', 'azc_fc'); ?></label></th><td>
					<input type="text" name="category_page" value="<?php echo esc_html( stripslashes($options['category_page']) ); ?>" class="medium-text" />
					<p class="description"><?php _e('Set default category page', 'azc_fc'); ?></p>
				</td></tr>
				<tr><th scope="row"><label for="category_link"><?php _e('Category Link Text', 'azc_fc'); ?></label></th><td>
					<input type="text" name="category_link" value="<?php echo esc_html( stripslashes($options['category_link']) ); ?>" class="medium-text" />
					<p class="description"><?php _e('Set default category link text', 'azc_fc'); ?></p>
				</td></tr>
				<tr><th scope="row"><?php _e('Category page show count?', 'azc_fc'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Category page show count?', 'azc_fc'); ?></span></legend>
					<label for="category_page_show_count"><input name="category_page_show_count" type="checkbox" id="category_page_show_count" value="1" <?php checked('1', $options['category_page_show_count']); ?> /></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><?php _e('Enable category page feed image?', 'azc_fc'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php _e('Enable category page feed image?', 'azc_fc'); ?></span></legend>
					<label for="category_page_feed_enabled"><input name="category_page_feed_enabled" type="checkbox" id="category_page_feed_enabled" value="1" <?php checked('1', $options['category_page_feed_enabled']); ?> /></label>
					</fieldset>
				</td></tr>
				<tr><th scope="row"><label for="category_page_feed_image"><?php _e('Category Page Feed Image', 'azc_fc'); ?></label></th><td>
					<input type="text" name="category_page_feed_image" value="<?php echo esc_html( stripslashes($options['category_page_feed_image']) ); ?>" class="regular-text" />
					<p class="description"><?php _e('Set category page feed image; leave blank for a default of "/wp-includes/images/rss.png"', 'azc_fc'); ?></p>
				</td></tr>
				</table>
				
				<input type="submit" value="Submit" class="button-primary"/>
			</form>
		</fieldset>
	</div>
<?php }


function azc_fc_admin_init() {
	add_action( 'admin_post_save_azc_fc', 'process_azc_fc' );
}
add_action( 'admin_init', 'azc_fc_admin_init' );

function process_azc_fc() {
	// Check that user has proper security level
	if ( !current_user_can( 'manage_options' ) ){
		wp_die( __('You do not have permissions to perform this action', 'azc_fc') );
	}
	// Check that nonce field created in configuration form is present
	if ( ! empty( $_POST ) && check_admin_referer( 'azc_fc_nonce', 'azc_fc_nonce' ) ) {
		// Retrieve original plugin options array
		$options = get_option( 'azc_fc' );
		
		$option_name = 'include_exclude';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'category';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'category_page';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'category_link';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'category_page_feed_image';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = ($_POST[$option_name]);
		}
		
		$option_name = 'category_page_feed_enabled';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		$option_name = 'category_page_show_count';
		if ( isset( $_POST[$option_name] ) ) {
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		// Store updated options array to database
		update_option( 'azc_fc', $options );
		
		// Redirect the page to the configuration form that was processed
		wp_redirect( add_query_arg( 'page', 'azc-fc&settings-updated', admin_url( 'admin.php' ) ) );
		exit;
	}
}



// Register function to be called when widget initialization occurs
add_action( 'widgets_init', 'azc_fc_create_widget' );

// Create new widget
function azc_fc_create_widget() {
	register_widget( 'azc_fc_register_widget' );
}

// Widget implementation class
class azc_fc_register_widget extends WP_Widget {
	// Constructor function
	function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		
		// Widget creation function
		parent::__construct( 'azc_fc',
							 'azurecurve Filtered Categories',
							 array( 'description' =>
									__('A filtered list of categories.', 'azc_fc') ) );
	}

	/**
	 * enqueue function.
	 *
	 * @access public
	 * @return void
	 */
	public function enqueue() {
		// Enqueue Styles
		wp_enqueue_style( 'azurecurve-filtered-categories', plugins_url( 'style.css', __FILE__ ), '', '1.0.0' );
	}

	// Code to render options form
	function form( $instance ) {
		// Retrieve previous values from instance
		// or set default values if not present
		$widget_title = ( !empty( $instance['azc_fc_title'] ) ? 
							esc_attr( $instance['azc_fc_title'] ) :
							'Categories' );
		?>

		<!-- Display field to specify title  -->
		<p>
			<label for="<?php echo 
						$this->get_field_id( 'azc_fc_title' ); ?>">
			<?php echo 'Widget Title:'; ?>			
			<input type="text" 
					id="<?php echo $this->get_field_id( 'azc_fc_title' ); ?>"
					name="<?php echo $this->get_field_name( 'azc_fc_title' ); ?>"
					value="<?php echo $widget_title; ?>" />			
			</label>
		</p> 

		<?php
	}

	// Function to perform user input validation
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['azc_fc_title'] = strip_tags( $new_instance['azc_fc_title'] );

		return $instance;
	}
	
	// Function to display widget contents
	function widget ( $args, $instance ) {
		// Extract members of args array as individual variables
		extract( $args );

		// Display widget title
		echo $before_widget;
		echo $before_title;
		$widget_title = ( !empty( $instance['azc_fc_title'] ) ? 
					esc_attr( $instance['azc_fc_title'] ) :
					'Categories' );
		echo apply_filters( 'widget_title', $widget_title );
		echo $after_title; 
		
		$options = get_site_option( 'azc_fc' );
		$siteoptions = $options;
		
		$first = true;
		$categories = '';
		$args = array('title_li' => '' );
		foreach ($siteoptions['category'] as $key => $value){
			if ($first){ $first = false; }else{ $categories.= ','; }
			$categories .= $key;
		}
		
		if ($siteoptions['include_exclude'] == 'include'){
			$args['include'] = $categories;
		}else{
			$args['exclude'] = $categories;
		}
		
		echo '<ul>';
		wp_list_categories( $args );
		echo '</ul>';
		
		if (strlen($siteoptions['category_page']) > 0){
			$page = get_page_by_path($siteoptions['category_page']);
			echo '<p class="azc_fc"><a href="'.esc_url(get_permalink($page->ID)).'">'.$options['category_link'].'</p>';
		}
		//echo esc_url($siteoptions['category_page']);
		//echo esc_url(get_page_by_path($siteoptions['category_page']));
		echo $after_widget;
	}
}

function azc_fc_custom_widget_css( $params ) {
	global $widget_num;
	$this_id = $params[0]['id'];
	$arr_registered_widgets = wp_get_sidebars_widgets();
	
	if ( !$widget_num ) { $widget_num = array(); }
	
    if ( !isset( $arr_registered_widgets[$this_id] ) || !is_array( $arr_registered_widgets[$this_id] ) ) {
        return $params;
    }
	
    $class = 'widget_categories widget_azc_fc';
  
    $params[0]['before_widget'] = str_replace( 'widget_azc_fc', $class, $params[0]['before_widget'] );
 
    return $params;
 
}
add_filter( 'dynamic_sidebar_params', 'azc_fc_custom_widget_css' );

function azc_fc_shortcode($atts, $content = null) {
	$options = get_site_option('azc_fc');
	if ($options['category_page_feed_enabled'] == 1){
		if (strlen($options['category_page_feed_image']) > 0){
			$feed_image = '&feed_image='.$options['category_page_feed_image'];
		}else{
			$feed_image = '&feed_image=/wp-includes/images/rss.png';
		}
	}else{
		$feed_image = '';
	}
	echo "<div class='azc_fc'><ul>";
	echo wp_list_categories('title_li=&style=list&show_count='.$options['category_page_show_count'].$feed_image);
	echo "</ul></div>";
}
add_shortcode( 'fc', 'azc_fc_shortcode' );
add_shortcode( 'Fc', 'azc_fc_shortcode' );
add_shortcode( 'FC', 'azc_fc_shortcode' );

?>
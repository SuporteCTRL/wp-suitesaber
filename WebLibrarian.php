<?php 
/**
 * Plugin Name: WP-Suíte Saber
 * Plugin URI: http://suitesaber.org
 * Description: A plugin that implements a web-based library catalog and circulation System
 * Version: 3.2.10.3
 * Author: Control Informação e Documentação
 * Author URI: http://www.control.com.br
 *
 *  Web Librarian WP Plugin
 *  Copyright (C) 2011  Robert Heller D/B/A Deepwoods Software
 *			51 Locke Hill Road
 *			Wendell, MA 01379-9728
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *
 */

define('WEBLIB_FILE', basename(__FILE__));
define('WEBLIB_DIR' , dirname(__FILE__));
define('WEBLIB_INCLUDES', WEBLIB_DIR . '/includes');
define('WEBLIB_CONTEXTUALHELP', WEBLIB_DIR . '/contextual_help');


/* Load Database code */
require_once(WEBLIB_INCLUDES . '/database_code.php');
/* Load Admin Code */
require_once(WEBLIB_INCLUDES . '/admin_page_classes.php');
/* Load Front End code */
require_once(WEBLIB_INCLUDES . '/short_codes.php');
class WebLibrarian {
  
    static private $pluginFile = __FILE__;
    private $version;
    private $admin_page;
    private $short_code_class;
    static function _getVersion() {
      $version = '';
      $fp = fopen(self::$pluginFile,'r');
      if ($fp) {
        while ($line = fgets($fp)) {
          if (preg_match("/^\s*\*\s*$/",$line) > 0) {break;}
          if (preg_match('/^\s*\*\s*Version:\s*(.*)$/',$line,$matches) > 0) {
            $version = $matches[1];
            break;
          }
        }
        fclose($fp);
      }
      return $version;
    }
    

    /* Constructor: register our activation and deactivation hooks and then
     * add in our actions.
     */
    function __construct() {
        $this->version = self::_getVersion();
	// Add the installation and uninstallation hooks
	register_activation_hook(WEBLIB_DIR . '/' . WEBLIB_FILE, 
				array($this,'install'));
	register_deactivation_hook(WEBLIB_DIR . '/' . WEBLIB_FILE,
				array($this,'deinstall'));
        add_action('init', array($this, 'init_action'));
	add_action('admin_print_scripts', array($this, 'add_admin_scripts'));
	add_action('wp_print_scripts', array($this, 'add_front_scripts'));
	add_action('wp_head', array($this, 'wp_head'));
	add_action('admin_head', array($this, 'admin_head'));
	add_action('widgets_init', array($this, 'widgets_init'));
	add_action('admin_menu', array($this, 'admin_menu'));
	add_filter('set-screen-option',array($this, 'set_screen_options'), 10, 3);
	add_filter('body_class', array($this, 'body_class'));
	add_action('weblib_admin_daily_event',array($this,'every_day'));
	add_option('weblib_aws_public_key','');
	add_option('weblib_aws_private_key','');
	add_option('weblib_aws_regiondom','com');
	add_option('weblib_associate_tag','');
	add_option('weblib_debugdb','off');

	load_plugin_textdomain('web-librarian',WEBLIB_BASEURL.'/languages/',
                                          basename(WEBLIB_DIR).'/languages/');
	
        $this->short_code_class = new WEBLIB_ShortCodes();

	//if (is_admin()) {
	//  wp_enqueue_script('jquery-ui-sortable');
	//}
    }
    function init_action() {
      define('WEBLIB_BASEURL',plugins_url( '', __FILE__));
      define('WEBLIB_CSSURL', plugins_url('/css',__FILE__));
      define('WEBLIB_JSURL', plugins_url('/js',__FILE__));
      define('WEBLIB_IMAGEURL', plugins_url('/images',__FILE__));
      //define('WEBLIB_DOCURL', plugins_url('/user_manual',__FILE__));
      wp_enqueue_style('weblib-front-style',WEBLIB_CSSURL . '/front.css',
                       null,$this->version);
      if (is_admin()) {
        wp_enqueue_style('jquery-ui-lightness',WEBLIB_CSSURL . '/jquery-ui-lightness/jquery-ui-lightness.css',null,$this->version);
        wp_enqueue_style('jquery-ui-resizable',WEBLIB_CSSURL . '/jquery-ui-lightness/jquery.ui.resizable.css',null,$this->version);
        wp_enqueue_style('weblib-admin-style',WEBLIB_CSSURL . '/admin.css',
                         array('weblib-front-style','jquery-ui-resizable'),$this->version);
      }
    }
    function MyVersion() {return $this->version;}
    function install() {
	$this->add_roles_and_caps();
	WEBLIB_make_tables();
	wp_schedule_event(mktime(2,0,0), 'daily', 'weblib_admin_daily_event');
    }
    function deinstall() {
	$this->remove_roles_and_caps();
	wp_clear_scheduled_hook('weblib_admin_daily_event');
    }
    function add_roles_and_caps() {
	global $wp_roles;
	$librarian = get_role('librarian');
	if ($librarian == null) {
	    add_role('librarian', __('Librarian','web-librarian'), array('read' => true,
						     'edit_users' => true,
						     'manage_patrons' => true,
						     'manage_collection' => true,
						     'manage_circulation' => true));
	} else {
	    $librarian->add_cap('edit_users');
	    $librarian->add_cap('manage_patrons');
	    $librarian->add_cap('manage_collection');
	    $librarian->add_cap('manage_circulation');
	}
	$senioraid = get_role('senioraid');
	if ($senioraid == null) {
	    add_role('senioraid', __('Senior Aid','web-librarian'), array('read' => true,
						      'manage_collection' => true,
						      'manage_circulation' => true));
	} else {
	    $senioraid->add_cap('manage_collection');
	    $senioraid->add_cap('manage_circulation');
	}
	$volunteer = get_role('volunteer');
	if ($volunteer == null) {
	    add_role('volunteer', __('Volunteer','web-librarian'), array('read' => true,
						     'manage_circulation' => true));
	} else {
	    $volunteer->add_cap('manage_circulation');
	}
    }
    function remove_roles_and_caps() {
	global $wp_roles;
	$librarian = get_role('librarian');
	if ($librarian  != null) {
	    $librarian->remove_cap ( 'manage_patrons' );
	    $librarian->remove_cap ( 'manage_collection' );
	    $librarian->remove_cap ( 'manage_circulation' );
	    $librarian->remove_cap ( 'edit_users' );
        }
        remove_role('librarian');   
	$senioraid = get_role('senioraid');
	if ($senioraid  != null) {
	    $senioraid->remove_cap ( 'manage_collection' );
	    $senioraid->remove_cap ( 'manage_circulation' );
        }
        remove_role('senioraid');
	$volunteer = get_role('volunteer');
	if ($volunteer  != null) {
	    $volunteer->remove_cap ( 'manage_circulation' );
        }
        remove_role('volunteer');  
    }
    static function localize_vars_front() {
	return array(
		'WEBLIB_BASEURL' => WEBLIB_BASEURL,
		'hold' => __('Hold','web-librarian'),
		'holds' => __('Holds','web-librarian'),
		'nodata' => __('Ajax error:  No Data Received','web-librarian'),
		'ajaxerr' => __('Ajax error: ','web-librarian')
	);
    }
    static function localize_vars_admin() {
	return array(
		'WEBLIB_BASEURL' => WEBLIB_BASEURL,
		'hold' => __('Hold','web-librarian'),
		'holds' => __('Holds','web-librarian'),
		'nodata' => __('Ajax error:  No Data Received','web-librarian'),
		'ajaxerr' => __('Ajax error: ','web-librarian'),
		'totalResultsFount' => __('%d total results found','web-librarian'),
		'loading' => __('Loading','web-librarian'),
		'lookupItem' => __('Lookup Item','web-librarian'),
		'insertItem' => __('Insert Item','web-librarian'),
		'lookupComplete' => __('Lookup Complete.','web-librarian'),
		'formInsertionComplete' => __('Form insertion complete.','web-librarian'),
		'lookingUpPatron' => __('Looking up Patron','web-librarian'),
		'noMatchingPatrons' => __('No matching patrons found.','web-librarian'),
		'selectPatron' => __('Select Patron','web-librarian'),
                'insertTitle' => __('Insert Title','web-librarian'),
                'insertISBN' => __('Insert ISBN','web-librarian'),
                'insertThumbnail' => __('Insert Thumbnail','web-librarian'),
                'addToAuthor' => __('Add to Author','web-librarian'),
                'insertAsDate' => __('Insert as date','web-librarian'),
                'insertAsPublisher' => __('Insert As Publisher','web-librarian'),
                'insertEdition' => __('Insert Edition','web-librarian'),
                'addToMedia' => __('Add to Media','web-librarian'),
                'addToDescription' => __('Add to description','web-librarian'),
                'addToKeywords' => __('Add to keywords','web-librarian')
	);
    }
    function add_admin_scripts() {
      //$this->add_front_scripts();
      wp_enqueue_script('jquery-ui-resizable');
      wp_enqueue_script('admin_js',WEBLIB_JSURL . '/admin.js', array('front_js','jquery-ui-resizable'), $this->version);
      wp_localize_script( 'admin_js','admin_js',self::localize_vars_admin() );
    }
    function add_front_scripts() {
      wp_enqueue_script('front_js',WEBLIB_JSURL . '/front.js', array(), $this->version);
      wp_localize_script( 'front_js','front_js',self::localize_vars_front() );
    }
    function wp_head() {
    }
    function admin_head() {
    }
    function widgets_init() {
	register_widget('WEBLIB_StrippedMeta');
    }
    function body_class($classes='') {
	$classes[] = "no-js";
	return $classes;
    }
    function every_day() {
	$cleared = WEBLIB_HoldItem::ClearExpiredHolds();
    }
    function admin_menu() {
	$this->admin_page = new WEBLIB_AdminPages();
	if (current_user_can('manage_circulation')) {
	   add_action('wp_dashboard_setup',array($this->admin_page,
						 'wp_dashboard_setup'));
	}
	add_action('wp_dashboard_setup',array($this->admin_page,
						'user_wp_dashboard_setup'));
    }
    function set_screen_options($status,$option,$value) {
      //file_put_contents("php://stderr","*** WebLibrarian::set_screen_options($status,$option,$value)\n");
      if (WEBLIB_AdminPages::set_screen_options($status,$option,$value)) return $value;
    }
}

/* Define widget code */
class WEBLIB_StrippedMeta extends WP_Widget {
    function __construct() {
	$widget_ops = array('classname' => 'widget_strippedmeta', 'description' => __( "Log in/out, admin",'web-librarian') );
	$this->WP_Widget('strippedmeta', __('Stripped Meta','web-librarian'), $widget_ops);
    }

    function widget( $args, $instance ) {
	extract($args);
	echo $before_widget;
?>
	    <ul>
	    <?php
		if (is_user_logged_in()){ ?>
		    <li><a href="<?php echo bloginfo('wpurl');
		    ?>/wp-admin" alt="admin">Dashboard</a></li><?php
		} else { ?>
		    <li><a href="<?php
		    echo site_url('wp-login.php?action=register', 'login');
		    ?>">Register</a></li><?php
		} ?>
	    <li><?php wp_loginout(); ?></li>
	    </ul>
<?php
	echo $after_widget;
    }

    function update( $new_instance, $old_instance ) {
	$instance = $old_instance;
	return $instance;
    }

    function form( $instance ) {
	$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
    }
}

/* Create an instanance of the plugin */
global $weblibrarian;
$weblibrarian = new WebLibrarian();


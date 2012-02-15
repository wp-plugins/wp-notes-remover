<?php

/*
  Plugin Name: WP Notes Remover
  Plugin URI: http://webweb.ca/site/products/wp-notes-remover/
  Description: WP Notes Remover removes "You May Use These HTML tags and attributes" below the comments. No necessary theme hacks needed.
  Version: 1.0.1
  Author: Svetoslav Marinov (Slavi)
  Author URI: http://WebWeb.ca
  License: GPL v2
 */

/*
  Copyright 2011-2020 Svetoslav Marinov (slavi@slavi.biz)

  This program ais free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// we can be called from the test script
if (empty($_ENV['WebWeb_WP_NotesRemover_TEST'])) {
    // Make sure we don't expose any info if called directly
    if (!function_exists('add_action')) {
        echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
        exit;
    }
    
	$WebWeb_WP_NotesRemover_obj = WebWeb_WP_NotesRemover::get_instance();
	
    add_action('init', array($WebWeb_WP_NotesRemover_obj, 'init'));

    register_activation_hook(__FILE__, array($WebWeb_WP_NotesRemover_obj, 'on_activate'));
    register_deactivation_hook(__FILE__, array($WebWeb_WP_NotesRemover_obj, 'on_deactivate'));
    register_uninstall_hook(__FILE__, array($WebWeb_WP_NotesRemover_obj, 'on_uninstall'));
}

class WebWeb_WP_NotesRemover {
    private $log_enabled = 0;
    private $log_file = null;
    private $permalinks = 0;
    private static $instance = null; // singleton
    private $site_url = null; // filled in later
    private $plugin_url = null; // filled in later
    private $plugin_settings_key = null; // filled in later
    private $plugin_dir_name = null; // filled in later
    private $plugin_data_dir = null; // plugin data directory. for reports and data storing. filled in later
    private $plugin_name = 'WP NotesRemover'; //
    private $plugin_id_str = 'wp_notes_remover'; //
    private $plugin_business_sandbox = false; // sandbox or live ???
    private $plugin_business_email_sandbox = 'seller_1264288169_biz@slavi.biz'; // used for paypal payments
    private $plugin_business_email = 'billing@WebWeb.ca'; // used for paypal payments
    private $plugin_business_ipn = 'http://webweb.ca/wp/hosted/payment/ipn.php'; // used for paypal IPN payments
    //private $plugin_business_status_url = 'http://localhost/wp/hosted/payment/status.php'; // used after paypal TXN to to avoid warning of non-ssl return urls
    private $plugin_business_status_url = 'https://secure.webweb.ca/webweb.ca/wp/hosted/payment/status.php'; // used after paypal TXN to to avoid warning of non-ssl return urls
    private $plugin_support_email = 'help@WebWeb.ca'; //
    private $plugin_support_link = 'http://miniads.ca/widgets/contact/profile/wp-notes-remover/?height=200&width=500&description=Please enter your enquiry below.'; //
    private $plugin_admin_url_prefix = null; // filled in later
    private $plugin_home_page = 'http://webweb.ca/site/products/wp-notes-remover/';
    private $plugin_tinymce_name = 'wwwpdigishop'; // if you change it update the tinymce/editor_plugin.js and reminify the .min.js file.
    private $plugin_cron_hook = __CLASS__;
    private $paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
    private $paypal_submit_image_src = 'https://www.paypal.com/en_GB/i/btn/btn_buynow_LG.gif';
    private $db_version = '1.0';
    private $plugin_cron_freq = 'daily';
    private $plugin_default_opts = array(
        'status' => 0,
    );

	private $app_title = 'Removes unuseful notes from WordPress!';
	private $plugin_description = '';

    private $plugin_uploads_path = null; // E.g. /wp-content/uploads/PLUGIN_ID_STR/
    private $plugin_uploads_url = null; // E.g. http://yourdomain/wp-content/uploads/PLUGIN_ID_STR/
    private $plugin_uploads_dir = null; // E.g. DOC_ROOT/wp-content/uploads/PLUGIN_ID_STR/

    private $download_key = null; // the param that will hold the download hash
    private $web_trigger_key = null; // the param will trigger something to happen. (e.g. PayPal IPN, test check etc.)

    // can't be instantiated; just using get_instance
    private function __construct() {
        
    }

    /**
     * handles the singleton
     */
    function get_instance() {
		if (is_null(self::$instance)) {
            global $wpdb;
            
			$cls = __CLASS__;	
			$inst = new $cls;
			
			$site_url = get_settings('siteurl');
			$site_url = rtrim($site_url, '/') . '/'; // e.g. http://domain.com/blog/

			$inst->site_url = $site_url;
			$inst->plugin_dir_name = basename(dirname(__FILE__)); // e.g. wp-command-center; this can change e.g. a 123 can be appended if such folder exist
			$inst->plugin_data_dir = dirname(__FILE__) . '/data';
			$inst->plugin_url = $site_url . 'wp-content/plugins/' . $inst->plugin_dir_name . '/';
			$inst->plugin_settings_key = $inst->plugin_id_str . '_settings';			
            $inst->plugin_support_link .= '&css_file=' . urlencode(get_bloginfo('stylesheet_url'));
            $inst->plugin_admin_url_prefix = $site_url . 'wp-admin/admin.php?page=' . $inst->plugin_dir_name;
		
            $opts = $inst->get_options();

            if (!$inst->log_enabled && !empty($opts['logging_enabled'])) {
                $inst->log_enabled = $opts['logging_enabled'];
            }

            // the log file be: log.1dd9091e045b9374dfb6b042990d65cc.2012-01-05.log
			if ($inst->log_enabled) {
				$inst->log_file = $inst->plugin_data_dir . '/log.'
                        . md5($site_url . $inst->plugin_dir_name)
                        . '.' . date('Y-m-d') . '.log';
			}

			add_action('plugins_loaded', array($inst, 'init'), 100);
            
			define('WebWeb_WP_NotesRemover_BASE_DIR', dirname(__FILE__)); // e.g. // htdocs/wordpress/wp-content/plugins/wp-command-center
			define('WebWeb_WP_NotesRemover_DIR_NAME', $inst->plugin_dir_name);

            self::$instance = $inst;
        }
		
		return self::$instance;
	}

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Unserializing is not allowed.', E_USER_ERROR);
    }

    /**
     * Logs whatever is passed IF logs are enabled.
     */
    function log($msg = '') {
        if ($this->log_enabled) {
            $msg = '[' . date('r') . '] ' . '[' . $_SERVER['REMOTE_ADDR'] . '] ' . $msg . "\n";
            error_log($msg, 3, $this->log_file);
        }
    }
    
    /**
     * handles the init
     */
    function init() {
        global $wpdb;

        if (is_admin()) {
            // Administration menus
            add_action('admin_menu', array($this, 'administration_menu'));
            add_action('admin_init', array($this, 'register_settings'));
			
			wp_register_style($this->plugin_dir_name, $this->plugin_url . 'css/main.css', false, 0.1);
            wp_enqueue_style($this->plugin_dir_name);
        } else {
            if (!is_feed()) {                
                add_action('wp_head', array($this, 'add_plugin_credits'), 1); // be the first in the header
                add_action('wp_footer', array($this, 'add_plugin_credits'), 1000); // be the last in the footer                
            }
        }
    }

    /**
     * Handles the plugin activation. creates db tables and uploads dir with an htaccess file
     */
    function on_activate() {
    }

    /**
     * Handles the plugin deactivation.
     */
    function on_deactivate() {
        $opts['status'] = 0;
        $this->set_options($opts);
    }

    /**
     * Handles the plugin uninstallation.
     */
    function on_uninstall() {
        delete_option($this->plugin_settings_key);
    }

    /**
     * Allows access to some private vars
     * @param str $var
     */
    public function get($var) {
        if (isset($this->$var) /* && (strpos($var, 'plugin') !== false) */) {
            return $this->$var;
        }
    }

    /**
     * gets current options and return the default ones if not exist
     * @param void
     * @return array
     */
    function get_options() {
        $opts = get_option($this->plugin_settings_key);
        $opts = empty($opts) ? array() : (array) $opts;

        // if we've introduced a new default key/value it'll show up.
        $opts = array_merge($this->plugin_default_opts, $opts);

        if (empty($opts['purchase_thanks'])) {
            $opts['purchase_thanks'] = $this->plugin_default_opts['purchase_thanks'];
        }
        
        if (empty($opts['purchase_error'])) {
            $opts['purchase_error'] = $this->plugin_default_opts['purchase_error'];
        }

        if (empty($opts['purchase_subject'])) {
            $opts['purchase_subject'] = $this->plugin_default_opts['purchase_subject'];
        }

        if (empty($opts['purchase_content'])) {
            $opts['purchase_content'] = $this->plugin_default_opts['purchase_content'];
        }

        return $opts;
    }

    /**
     * Updates options but it merges them unless $override is set to 1
     * that way we could just update one variable of the settings.
     */
    function set_options($opts = array(), $override = 0) {
        if (!$override) {
            $old_opts = $this->get_options();
            $opts = array_merge($old_opts, $opts);
        }

        update_option($this->plugin_settings_key, $opts);

        return $opts;
    }

    /**
     * This is what the plugin admins will see when they click on the main menu.
     * @var string
     */
    private $plugin_landing_tab = '/menu.dashboard.php';

    /**
     * Adds the settings in the admin menu
     */
    public function administration_menu() {
        // Settings > DigiShop
        add_options_page(__($this->plugin_name, "WebWeb_WP_NotesRemover"), __($this->plugin_name, "WebWeb_WP_NotesRemover"),
                'manage_options', $this->plugin_dir_name . '/menu.settings.php');

        // when plugins are show add a settings link near my plugin for a quick access to the settings page.
        add_filter('plugin_action_links', array($this, 'add_plugin_settings_link'), 10, 2);
    }
	
    /**
     * Allows access to some private vars
     * @param str $var
     */
    public function generate_newsletter_box($params = array()) {
        $file = WebWeb_WP_NotesRemover_BASE_DIR . '/zzz_newsletter_box.html';

        $buffer = WebWeb_WP_NotesRemoverUtil::read($file);

        wp_get_current_user();
        global $current_user;
        $user_email = $current_user->user_email;

        $replace_vars = array(
            '%%PLUGIN_URL%%' => $this->get('plugin_url'),
            '%%USER_EMAIL%%' => $user_email,
            '%%PLUGIN_ID_STR%%' => $this->get('plugin_id_str'),
            '%%admin_sidebar%%' => $this->get('plugin_id_str'),
        );

        if (!empty($params['form_only'])) {
            $replace_vars['NEWSLETTER_QR_EXTRA_CLASS'] = "app_hide";
        } else {
            $replace_vars['NEWSLETTER_QR_EXTRA_CLASS'] = "";
        }

        if (!empty($params['src2'])) {
            $replace_vars['SRC2'] = $params['src2'];
        } elseif (!empty($params['SRC2'])) {
            $replace_vars['SRC2'] = $params['SRC2'];
        }

        $buffer = WebWeb_WP_NotesRemoverUtil::replace_vars($buffer, $replace_vars);

        return $buffer;
    }

    /**
     * Allows access to some private vars
     * @param str $var
     */
    public function generate_donate_box() {
        $msg = '';
        $file = WebWeb_WP_NotesRemover_BASE_DIR . '/zzz_donate_box.html';

        if (!empty($_REQUEST['error'])) {
            $msg = $this->message('There was a problem with the payment.');
        }
        
        if (!empty($_REQUEST['ok'])) {
            $msg = $this->message('Thank you so much!', 1);
        }

        $return_url = WebWeb_WP_NotesRemoverUtil::add_url_params($this->get('plugin_business_status_url'), array(
            'r' => $this->get('plugin_admin_url_prefix') . '/menu.dashboard.php&ok=1', // paypal de/escapes
            'status' => 1,
        ));

        $cancel_url = WebWeb_WP_NotesRemoverUtil::add_url_params($this->get('plugin_business_status_url'), array(
            'r' => $this->get('plugin_admin_url_prefix') . '/menu.dashboard.php&error=1', // 
            'status' => 0,
        ));

        $replace_vars = array(
            '%%MSG%%' => $msg,
            '%%AMOUNT%%' => '2.99',
            '%%BUSINESS_EMAIL%%' => $this->plugin_business_email,
            '%%ITEM_NAME%%' => $this->plugin_name . ' Donation',
            '%%ITEM_NAME_REGULARLY%%' => $this->plugin_name . ' Donation (regularly)',
            '%%PLUGIN_URL%%' => $this->get('plugin_url'),
            '%%CUSTOM%%' => http_build_query(array('site_url' => $this->site_url, 'product_name' => $this->plugin_id_str)),
            '%%NOTIFY_URL%%' => $this->get('plugin_business_ipn'),
            '%%RETURN_URL%%' => $return_url,
            '%%CANCEL_URL%%' => $cancel_url,
        );

        // Let's switch the Sandbox settings.
        if ($this->plugin_business_sandbox) {
            $replace_vars['paypal.com'] = 'sandbox.paypal.com';
            $replace_vars['%%BUSINESS_EMAIL%%'] = $this->plugin_business_email_sandbox;
        }

        $buffer = WebWeb_WP_NotesRemoverUtil::read($file);
        $buffer = str_replace(array_keys($replace_vars), array_values($replace_vars), $buffer);

        return $buffer;
    }	

    /**
     * Outputs some options info. No save for now.
     */
    function options() {
		$WebWeb_WP_NotesRemover_obj = WebWeb_WP_NotesRemover::get_instance();
        $opts = get_option('settings');

        include_once(WebWeb_WP_NotesRemover_BASE_DIR . '/menu.settings.php');
    }

    /**
     * Sets the setting variables
     */
    function register_settings() { // whitelist options
        register_setting($this->plugin_dir_name, $this->plugin_settings_key);
    }

    // Add the ? settings link in Plugins page very good
    function add_plugin_settings_link($links, $file) {
        if ($file == plugin_basename(__FILE__)) {
            //$prefix = 'options-general.php?page=' . dirname(plugin_basename(__FILE__)) . '/';
            $prefix = $this->plugin_admin_url_prefix . '/';

            $settings_link = "<a href=\"{$prefix}menu.settings.php\">" . __("Settings", $this->plugin_dir_name) . '</a>';

            array_unshift($links, $settings_link);
        }

        return $links;
    }

    /**
     * adds some HTML comments in the page so people would know that this plugin powers their site.
     */
    function add_plugin_credits() {
        echo PHP_EOL . "<style>.form-allowed-tags { display: none; } </style>" . PHP_EOL;

        //printf("\n" . '<meta name="generator" content="Powered by ' . $this->plugin_name . ' (' . $this->plugin_home_page . ') " />' . PHP_EOL);
        printf(PHP_EOL . '<!-- ' . PHP_EOL . 'Powered by ' . $this->plugin_name
                . ': ' . $this->app_title . PHP_EOL
                . 'URL: ' . $this->plugin_home_page . PHP_EOL
                . '-->' . PHP_EOL . PHP_EOL);
    }

    /**
     * Outputs a message (adds some paragraphs)
     */
    function message($msg, $status = 0) {
        $id = $this->plugin_id_str;
        $cls = empty($status) ? 'error fade' : 'success';

        $str = <<<MSG_EOF
<div id='$id-notice' class='$cls'><p><strong>$msg</strong></p></div>
MSG_EOF;
        return $str;
    }

    /**
     * a simple status message, no formatting except color
     */
    function msg($msg, $status = 0, $use_inline_css = 0) {
        $inline_css = '';
        $id = $this->plugin_id_str;
        $cls = empty($status) ? 'app_error' : 'app_success';

        if ($use_inline_css) {
            $inline_css = empty($status) ? 'background-color:red;' : 'background-color:green;';
            $inline_css .= 'text-align:center;margin-left: auto; margin-right:auto; padding-bottom:10px;color:white;';
        }

        $str = <<<MSG_EOF
<div id='$id-notice' class='$cls' style="$inline_css"><strong>$msg</strong></div>
MSG_EOF;
        return $str;
    }
	
    /**
     * a simple status message, no formatting except color, simpler than its brothers
     */
    function m($msg, $status = 0, $use_inline_css = 0) {        
        $cls = empty($status) ? 'app_error' : 'app_success';
        $inline_css = '';

        if ($use_inline_css) {
            $inline_css = empty($status) ? 'color:red;' : 'color:green;';
            $inline_css .= 'text-align:center;margin-left: auto; margin-right: auto;';
        }

        $str = <<<MSG_EOF
<span class='$cls' style="$inline_css">$msg</span>
MSG_EOF;
        return $str;
    }

    private $errors = array();

    /**
     * accumulates error messages
     * @param array $err
     * @return void
     */
    function add_error($err) {
        return $this->errors[] = $err;
    }

    /**
     * @return array
     */
    function get_errors() {
        return $this->errors;
    }
    
    function get_errors_str() {
        $str  = join("<br/>", $this->get_errors());
        return $str;
    }

    /**
     *
     * @return bool
     */
    function has_errors() {
        return !empty($this->errors) ? 1 : 0;
    }
}

class WebWeb_WP_NotesRemoverUtil {
    // options for read/write methods.
    const FILE_APPEND = 1;
    const UNSERIALIZE_DATA = 2;
    const SERIALIZE_DATA = 3;

    /**
     * Replaces the template variables
     * @param string buffer to operate on
     * @param array the keys are uppercased and surrounded by %%KEY_NAME%%
     * @return string modified data
     */
    public static function replace_vars($buffer, $params = array()) {
        foreach ($params as $key => $value) {
            $key = trim($key, '%');
            $key = strtoupper($key);
            $key = '%%' . $key . '%%';

            $buffer = str_ireplace($key, $value, $buffer);
        }
//        var_dump($params);
        // Let's check if there are unreplaced variables
        if (preg_match('#(%%[\w-]+%%)#si', $buffer, $matches)) {
//            trigger_error("Not all template variables were replaced. Please check the missing and add them to the input params." . join(",", $matches[1]), E_USER_WARNING);
            trigger_error("Not all template variables were replaced. Please check the missing and add them to the input params." . var_export($matches, 1), E_USER_WARNING);
        }

        return $buffer;
    }

    /**
     * Checks if the url is valid
     * @param string $url
     */
    public static function validate_url($url = '') {
        $status = preg_match("@^(?:ht|f)tps?://@si", $url);

        return $status;
    }

    /**
     *
     * @param string $buffer
     */
    public static function sanitizeFile($str = '') {

        return $str;
    }

    /**
     * Serves the file for download. Forces the browser to show Save as and not open the file in the browser.
     * Makes the script run for 12h just in case and after the file is sent the script stops.
     *
     * Credits:
	 * http://php.net/manual/en/function.readfile.php
     * http://stackoverflow.com/questions/2222955/idiot-proof-cross-browser-force-download-in-php
     *
     * @param string $file
     * @param bool $do_exit - exit after the file has been downloaded.
     */
    public static function download_file($file, $do_exit = 1) {
        set_time_limit(12 * 3600); // 12 hours

        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 0);

            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
        }

        if (!empty($_SERVER['HTTPS'])
                && ($_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)) {
            header("Cache-control: private");
            header('Pragma: private');

            // IE 6.0 fix for SSL
            // SRC http://ca3.php.net/header
            // Brandon K [ brandonkirsch uses gmail ] 25-Apr-2007 03:34
            header('Cache-Control: maxage=3600'); //Adjust maxage appropriately
        } else {
            header('Pragma: public');
        }

		header('Expires: 0');
 		header('Content-Description: File Transfer');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) (filesize($file)));
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');

		ob_clean();
		flush();

        readfile($file);

		if ($do_exit) {
			exit;
		}
    }

    /**
     * Gets the content from the body, removes the comments, scripts
     * Credits: http://php.net/manual/en/function.strip-tags.phpm /  http://networking.ringofsaturn.com/Web/removetags.php
     * @param string $buffer
     * @string string $buffer
     */

    public static function html2text($buffer = '') {
        // we care only about the body so it must be beautiful.
        $buffer = preg_replace('#.*<body[^>]*>(.*?)</body>.*#si', '\\1', $buffer);
        $buffer = preg_replace('#<script[^>]*>.*?</script>#si', '', $buffer);
        $buffer = preg_replace('#<style[^>]*>.*?</style>#siU', '', $buffer);
//        $buffer = preg_replace('@<style[^>]*>.*?</style>@siU', '', $buffer); // Strip style tags properly
        $buffer = preg_replace('#<[a-zA-Z\/][^>]*>#si', ' ', $buffer); // Strip out HTML tags  OR '@<[\/\!]*?[^<>]*\>@si',
        $buffer = preg_replace('@<![\s\S]*?--[ \t\n\r]*>@', '', $buffer); // Strip multi-line comments including CDATA
        $buffer = preg_replace('#[\t\ ]+#si', ' ', $buffer); // replace just one space
        $buffer = preg_replace('#[\n\r]+#si', "\n", $buffer); // replace just one space
        //$buffer = preg_replace('#(\s)+#si', '\\1', $buffer); // replace just one space
        $buffer = preg_replace('#^\s*|\s*$#si', '', $buffer);

        return $buffer;
    }

    /**
     * Gets the content from the body, removes the comments, scripts
     *
     * @param string $buffer
     * @param array $keywords
     * @return array - for now it returns hits; there could be some more complicated results in the future so it's better as an array
     */
    public static function match($buffer = '', $keywords = array()) {
        $status_arr['hits'] = 0;

        foreach ($keywords as $keyword) {
            $cnt = preg_match('#\b' . preg_quote($keyword) . '\b#si', $buffer);

            if ($cnt) {
                $status_arr['hits']++; // total hits
                $status_arr['matches'][$keyword] = array('keyword' => $keyword, 'hits' => $cnt,); // kwd hits
            }
        }

        return $status_arr;
    }

    /**
     * @desc write function using flock
     *
     * @param string $vars
     * @param string $buffer
     * @param int $append
     * @return bool
     */
    public static function write($file, $buffer = '', $option = null) {
        $buff = false;
        $tries = 0;
        $handle = '';

        $write_mod = 'wb';

        if ($option == self::SERIALIZE_DATA) {
            $buffer = serialize($buffer);
        } elseif ($option == self::FILE_APPEND) {
            $write_mod = 'ab';
        }

        if (($handle = @fopen($file, $write_mod))
                && flock($handle, LOCK_EX)) {
            // lock obtained
            if (fwrite($handle, $buffer) !== false) {
                @fclose($handle);
                return true;
            }
        }

        return false;
    }

    /**
     * @desc read function using flock
     *
     * @param string $vars
     * @param string $buffer
     * @param int $option whether to unserialize the data
     * @return mixed : string/data struct
     */
    public static function read($file, $option = null) {
        $buff = false;
        $read_mod = "rb";
        $tries = 0;
        $handle = false;

        if (($handle = @fopen($file, $read_mod))
                && (flock($handle, LOCK_EX))) { //  | LOCK_NB - let's block; we want everything saved
            $buff = @fread($handle, filesize($file));
            @fclose($handle);
        }

        if ($option == self::UNSERIALIZE_DATA) {
            $buff = unserialize($buff);
        }

        return $buff;
    }

    /**
     *
     * Appends a parameter to an url; uses '?' or '&'
     * It's the reverse of parse_str().
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    public static function add_url_params($url, $params = array()) {
        $str = '';

        $params = (array) $params;

        if (empty($params)) {
            return $url;
        }

        $query_start = (strpos($url, '?') === false) ? '?' : '&';

        foreach ($params as $key => $value) {
            $str .= ( strlen($str) < 1) ? $query_start : '&';
            $str .= rawurlencode($key) . '=' . rawurlencode($value);
        }

        $str = $url . $str;

        return $str;
    }

    // generates HTML select
    public static function html_select($name = '', $sel = null, $options = array(), $attr = '') {
        $html = "\n" . '<select name="' . $name . '" id="' . $name . '" ' . $attr . '>' . "\n";

        foreach ($options as $key => $label) {
            $selected = $sel == $key ? ' selected="selected"' : '';
            $html .= "\t<option value='$key' $selected>$label</option>\n";
        }

        $html .= '</select>';
        $html .= "\n";

        return $html;
    }

    // generates status msg
    public static function msg($msg = '', $status = 0) {
        $cls = empty($status) ? 'error' : 'success';
        $cls = $status == 2 ? 'notice' : $cls;

        $msg = "<p class='status_wrapper'><div class=\"status_msg $cls\">$msg</div></p>";

        return $msg;
    }

    /**
     * checks several variables and returns the lowest.
     * @see http://www.kavoir.com/2010/02/php-get-the-file-uploading-limit-max-file-size-allowed-to-upload.html
     * @return int
     */
    public static function get_max_upload_size() {
        $max_upload = (int)(ini_get('upload_max_filesize'));
        $max_post = (int)(ini_get('post_max_size'));
        $memory_limit = (int)(ini_get('memory_limit'));

        $upload_mb = min($max_upload, $max_post, $memory_limit);

        return $upload_mb;
    }

    /**
     * proto str formatFileSize( int $size )
     *
     * @param string
     * @return string 1 KB/ MB
     */
    public static function format_file_size($size) {
    	$size_suff = 'Bytes';

        if ($size > 1024 ) {
            $size /= 1024;
            $size_suff = 'KB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'MB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'GB';
        }

        if ( $size > 1024 ) {
            $size /= 1024;
            $size_suff = 'TB';
        }

        $size = number_format($size, 2);

        return $size . " $size_suff";
    }
}
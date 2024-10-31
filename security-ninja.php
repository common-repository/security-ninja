<?php

/*
Plugin Name: Security Ninja
Plugin URI: https://wpsecurityninja.com/
Description: Check your site for <strong>security vulnerabilities</strong> and get precise suggestions for corrective actions on passwords, user accounts, file permissions, database security, version hiding, plugins, themes, security headers and other security aspects.
Author: WP Security Ninja
Version: 5.219
Author URI: https://wpsecurityninja.com/
Text Domain: security-ninja
Domain Path: /languages

Copyright
2011-2019 Web Factory Ltd
2019-     Larsik Corp

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


This plugin uses the following 3rd party MIT licensed projects - Thank you for making other developer lives easier :-)

* Rename login module based on the WordPress Rename Login by Prathap Rathod.

* Country flags Copyright (c) 2017 Go Squared Ltd. http://www.gosquared.com/ - https://github.com/gosquared/flags. MIT license.

* PHP malware scanner - https://github.com/scr34m/php-malware-scanner
This plugin works on a modified version of the excellent PHP malware scanner.
*/
namespace WPSecurityNinja\Plugin;

use Error;
use wf_sn_cf;
use Wf_Sn_Cs;
use Wf_Sn_Wl;
use Utils;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( function_exists( '\\WPSecurityNinja\\Plugin\\secnin_fs' ) ) {
    secnin_fs()->set_basename( false, __FILE__ );
} elseif ( !function_exists( '\\WPSecurityNinja\\Plugin\\secnin_fs' ) ) {
    // Create a helper function for easy SDK access.
    function secnin_fs() {
        global $secnin_fs;
        if ( !isset( $secnin_fs ) ) {
            // Activate multisite network integration.
            if ( !defined( 'WP_FS__PRODUCT_3690_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_3690_MULTISITE', true );
            }
            // Include Freemius SDK.
            require_once __DIR__ . '/freemius/start.php';
            $secnin_fs = fs_dynamic_init( array(
                'id'              => '3690',
                'slug'            => 'security-ninja',
                'type'            => 'plugin',
                'public_key'      => 'pk_f990ec18700a90c02db544f1aa986',
                'is_premium'      => false,
                'has_addons'      => true,
                'has_paid_plans'  => true,
                'trial'           => array(
                    'days'               => 30,
                    'is_require_payment' => true,
                ),
                'has_affiliation' => 'selected',
                'menu'            => array(
                    'slug'       => 'wf-sn',
                    'first-path' => 'admin.php?page=wf-sn',
                    'support'    => false,
                    'network'    => true,
                ),
                'is_live'         => true,
            ) );
        }
        return $secnin_fs;
    }

    // Init Freemius.
    secnin_fs();
    // Signal that SDK was initiated.
    do_action( 'secnin_fs_loaded' );
    define( 'WF_SN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'WF_SN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    define( 'WF_SN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    define( 'WF_SN_BASE_FILE', __FILE__ );
    require_once WF_SN_PLUGIN_DIR . 'vendor/autoload.php';
    // Vulnerabilities
    include_once WF_SN_PLUGIN_DIR . 'modules/vulnerabilities/class-wf-sn-vu.php';
    // Core Scanner
    include_once WF_SN_PLUGIN_DIR . 'modules/core-scanner/core-scanner.php';
    // File viewer
    include_once WF_SN_PLUGIN_DIR . 'modules/file-viewer/class-secnin-file-viewer.php';
    include_once WF_SN_PLUGIN_DIR . 'includes/class-wf-sn-utils.php';
    class Wf_Sn {
        /**
         * Plugin version
         *
         * @var integer
         */
        public static $version = null;

        /**
         * Plugin name
         *
         * @var string
         */
        public static $name = 'Security Ninja';

        /**
         * List of tests to skip
         *
         * @var array
         */
        public static $skip_tests = array();

        public static $options;

        /**
         * Init the plugin
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, April 29th, 2021.
         * @access  public static
         * @return  void
         */
        public static function init() {
            // SN requires WP v4.7
            if ( !version_compare( get_bloginfo( 'version' ), '4.7', '>=' ) ) {
                add_action( 'admin_notices', array(__NAMESPACE__ . '\\wf_sn_af', 'min_version_error') );
                return;
            }
            self::$options = self::get_options();
            // loads persistent admin notices
            add_action( 'admin_init', array('PAnD', 'init') );
            // Load security tests
            include_once WF_SN_PLUGIN_DIR . 'class-wf-sn-tests.php';
            include_once WF_SN_PLUGIN_DIR . 'includes/class-wf-sn-utils.php';
            // MainWP integration - run here to make sure it's loaded
            add_filter(
                'mainwp_child_extra_execution',
                array(__NAMESPACE__ . '\\Utils', 'do_filter_mainwp_child_extra_execution'),
                10,
                2
            );
            add_filter(
                'mainwp_site_sync_others_data',
                array(__NAMESPACE__ . '\\Utils', 'do_filter_mainwp_site_sync_others_data'),
                10,
                2
            );
            add_action( 'secnin_run_tests_event', array(__NAMESPACE__ . '\\Wf_Sn', 'do_event_run_tests') );
            // does the user have enough privilages to use the plugin?
            if ( current_user_can( 'activate_plugins' ) ) {
                // Adds extra permission to Freemius
                if ( function_exists( '\\WPSecurityNinja\\Plugin\\secnin_fs' ) ) {
                    secnin_fs()->add_filter( 'permission_list', array(__NAMESPACE__ . '\\Utils', 'add_freemius_extra_permission') );
                    secnin_fs()->add_filter(
                        'show_admin_notice',
                        array(__NAMESPACE__ . '\\Utils', 'do_filter_show_admin_notice'),
                        10,
                        2
                    );
                    add_action( 'admin_init', array(__NAMESPACE__ . '\\Utils', 'secnin_fs_license_key_migration') );
                    secnin_fs()->add_filter( 'plugin_icon', array(__NAMESPACE__ . '\\Wf_Sn', 'secnin_fs_custom_icon') );
                }
                add_action( 'wp_ajax_wfsn_freemius_reset_activation', array(__NAMESPACE__ . '\\Wf_Sn', 'freemius_reset_activation') );
                add_action( 'wp_dashboard_setup', array(__NAMESPACE__ . '\\Wf_Sn', 'add_dashboard_widgets') );
                add_filter(
                    'sn_tabs',
                    array(__NAMESPACE__ . '\\Wf_Sn', 'return_tabs'),
                    PHP_INT_MAX,
                    1
                );
                add_action( 'admin_menu', array(__NAMESPACE__ . '\\Wf_Sn', 'admin_menu') );
                add_action(
                    'plugin_action_links_' . secnin_fs()->get_plugin_basename(),
                    array(__NAMESPACE__ . '\\Utils', 'plugin_action_links'),
                    PHP_INT_MAX,
                    4
                );
                add_action(
                    'activated_plugin',
                    array(__NAMESPACE__ . '\\Wf_Sn', 'do_action_activated_plugin'),
                    10,
                    2
                );
                add_filter(
                    'plugin_row_meta',
                    array(__NAMESPACE__ . '\\Wf_Sn', 'plugin_meta_links'),
                    10,
                    2
                );
                add_action( 'admin_enqueue_scripts', array(__NAMESPACE__ . '\\Wf_Sn', 'enqueue_scripts') );
                add_action( 'admin_init', array(__NAMESPACE__ . '\\Wf_Sn', 'register_settings') );
                add_action( 'admin_init', array(__NAMESPACE__ . '\\Wf_Sn', 'do_action_admin_init') );
                add_action( 'wp_ajax_sn_run_single_test', array(__NAMESPACE__ . '\\Wf_Sn', 'run_single_test') );
                add_action( 'wp_ajax_sn_get_single_test_details', array(__NAMESPACE__ . '\\Wf_Sn', 'get_single_test_details') );
                add_action( 'wp_ajax_sn_run_tests', array(__NAMESPACE__ . '\\Wf_Sn', 'run_tests') );
                add_action( 'admin_notices', array(__NAMESPACE__ . '\\Utils', 'do_admin_notices') );
                add_action( 'wp_ajax_wf_sn_dismiss_review', array(__NAMESPACE__ . '\\Wf_Sn', 'wf_sn_dismiss_review') );
                add_action( 'admin_footer', array(__NAMESPACE__ . '\\Utils', 'admin_footer') );
                add_action( 'admin_notices', array(__NAMESPACE__ . '\\Utils', 'signup_to_newsletter') );
                add_filter( 'manage_users_columns', array(__NAMESPACE__ . '\\Utils', 'add_user_last_login_column') );
                add_filter(
                    'manage_users_custom_column',
                    array(__NAMESPACE__ . '\\Utils', 'return_last_login_column'),
                    10,
                    3
                );
            }
        }

        /**
         * Shows the topbar, logo and version
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0    Monday, March 11th, 2024.
         * @access  public static
         * @global
         * @return  void
         */
        public static function show_topbar() {
            $topbar = '<img src="' . esc_url( WF_SN_PLUGIN_URL . 'images/sn-logo.svg' ) . '" height="28" alt="Visit wpsecurityninja.com" class="logoleft"><h1>Security Ninja <span>v.' . wf_sn::get_plugin_version() . '</span></h1>';
            echo wp_kses_post( $topbar );
        }

        /**
         * do_event_run_tests.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, December 12th, 2023.
         * @version v1.0.1  Thursday, December 14th, 2023.
         * @access  public static
         * @return  void
         */
        public static function do_event_run_tests() {
            $security_tests = wf_sn_tests::return_security_tests();
            $resultssofar = get_option( 'security_tests_results', array() );
            $set_time_limit = set_time_limit( 200 );
            $last_test_run = ( isset( $resultssofar['last_test_run'] ) ? $resultssofar['last_test_run'] : '' );
            $resultssofar['last_run'] = time();
            foreach ( $security_tests as $test_name => $test ) {
                $class_with_namespace = __NAMESPACE__ . '\\Wf_Sn_Tests';
                if ( !method_exists( $class_with_namespace, $test_name ) ) {
                    if ( secnin_fs()->is__premium_only() && secnin_fs()->can_use_premium_code() ) {
                        wf_sn_el_modules::log_event( 'security_ninja', 'security_tests', "Method {$test_name} does not exist in Wf_Sn" );
                    }
                    continue;
                }
                // Call the method dynamically
                $response = $class_with_namespace::$test_name();
                if ( !is_array( $response ) || empty( $response ) ) {
                    // Log error if the response is not valid
                    if ( secnin_fs()->is__premium_only() && secnin_fs()->can_use_premium_code() ) {
                        wf_sn_el_modules::log_event( 'security_ninja', 'security_tests', "Invalid response for test {$test_name}" );
                    }
                    continue;
                }
                // Setting appropriate message
                if ( 10 === intval( $response['status'] ) ) {
                    $return_message = sprintf( $test['msg_ok'], $response['msg'] ?? '' );
                } elseif ( 0 === intval( $response['status'] ) ) {
                    $return_message = sprintf( $test['msg_bad'], $response['msg'] ?? '' );
                } else {
                    $return_message = sprintf( $test['msg_warning'], $response['msg'] ?? '' );
                }
                // Updates the results
                $resultssofar['test'][$test_name] = array(
                    'title'  => $test['title'],
                    'status' => $response['status'],
                    'score'  => $test['score'],
                    'msg'    => $return_message,
                );
                $end_time = self::timerstart( 'run_test_' . esc_attr( $test_name ) );
                $testresult = array(
                    'testid'    => $test_name,
                    'timestamp' => current_time( 'mysql' ),
                    'title'     => $test['title'],
                    'status'    => $response['status'],
                    'score'     => $test['score'],
                    'runtime'   => $end_time,
                    'msg'       => $return_message,
                    'details'   => '',
                );
                self::update_test_score( $testresult );
                // Update the last test run
                $resultssofar['last_test_run'] = $test_name;
                update_option( 'security_tests_results', $resultssofar );
            }
            if ( secnin_fs()->is__premium_only() && secnin_fs()->can_use_premium_code() ) {
                wf_sn_el_modules::log_event(
                    'security_ninja',
                    'security_tests',
                    'Finished running tests.',
                    ''
                );
            }
        }

        /**
         * Redirects the user after plugin activation.
         *
         * @author  Unknown
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, February 22nd, 2022.
         * @version v1.0.1  Saturday, March 5th, 2022.
         * @access  public static
         * @global
         * @return  void
         */
        public static function do_action_admin_init() {
            $target_admin_url = 'admin.php?page=wf-sn';
            // Make sure it's the correct user
            if ( !wp_doing_ajax() && intval( get_option( 'secnin_activation_redirect', false ) ) === wp_get_current_user()->ID ) {
                // Make sure we don't redirect again after this one
                delete_option( 'secnin_activation_redirect' );
                wp_safe_redirect( admin_url( $target_admin_url ) );
                exit;
            }
        }

        /**
         * do_action_activated_plugin.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 6th, 2021.
         * @access  public static
         * @param   mixed $plugin
         * @param   mixed $network_wide
         * @return  void
         */
        public static function do_action_activated_plugin( $plugin, $network_wide ) {
            // Bail if activating from network or bulk sites.
            if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
                return;
            }
        }

        /**
         * Creates a toggle switch for admin page
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 6th, 2021.
         * @version v1.0.1  Saturday, April 27th, 2024.
         * @access  public static
         * @param   mixed   $name
         * @param   mixed   $options    [description]
         * @param   boolean $output     HTML output containing the checkbox
         * @return  void
         */
        public static function create_toggle_switch( $name, $options = array(), $output = true ) {
            $default_options = array(
                'value'       => 1,
                'saved_value' => 0,
                'option_key'  => '',
            );
            $options = wp_parse_args( $options, $default_options );
            $value = (int) $options['value'];
            $saved_value = ( isset( $options['saved_value'] ) ? (int) $options['saved_value'] : 0 );
            $checked = ( $value === $saved_value ? ' checked' : '' );
            $html = '<div class="toggle-wrapper">' . sprintf(
                '<input type="checkbox" id="%1$s" value="%2$s" name="%3$s"%4$s>',
                esc_attr( $name ),
                esc_attr( $options['value'] ),
                esc_attr( $options['option_key'] ),
                $checked
            ) . sprintf( '<label for="%1$s" class="toggle"><span class="toggle_handler"></span></label>', esc_attr( $name ) ) . '</div>';
            if ( $output ) {
                echo wp_kses( $html, array(
                    'div'   => array(
                        'class' => array(),
                    ),
                    'input' => array(
                        'type'    => array(),
                        'id'      => array(),
                        'value'   => array(),
                        'name'    => array(),
                        'checked' => array(),
                    ),
                    'label' => array(
                        'for'   => array(),
                        'class' => array(),
                    ),
                    'span'  => array(
                        'class' => array(),
                    ),
                ) );
            } else {
                return $html;
            }
        }

        /**
         * Custom logo URL for Freemius dialogue
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function secnin_fs_custom_icon() {
            return __DIR__ . '/images/plugin-icon.png';
        }

        /**
         * Add a widget to the dashboard.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  void
         */
        public static function add_dashboard_widgets() {
            wp_add_dashboard_widget( 
                'wpsn_dashboard_widget',
                'WP Security Ninja',
                // Is not whitelabelled, so nevermind
                array(__NAMESPACE__ . '\\wf_sn', 'wpsn_dashboard_widget_render')
             );
        }

        /**
         * Renders dashboard widget
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  void
         */
        public static function wpsn_dashboard_widget_render() {
            if ( class_exists( __NAMESPACE__ . '\\Wf_Sn' ) ) {
                $icon_url = self::get_icon_svg( true, '000000' );
                echo '<img src="' . esc_url( $icon_url ) . '" style="width:40px;float:right;margin-bottom:10px;">';
            }
            $vulns = \WPSecurityNinja\Plugin\Wf_Sn_Vu::return_vulnerabilities();
            if ( $vulns ) {
                $total = \WPSecurityNinja\Plugin\Wf_Sn_Vu::return_vuln_count();
                ?>
				<h3><span class="dashicons dashicons-warning"></span> <strong>
						<?php 
                // translators: Shown when one or multiple vulnerabilities found
                echo esc_html( sprintf( _n(
                    'You have %s known vulnerability on your website!',
                    'You have %s known vulnerabilities on your website!',
                    $total,
                    'security-ninja'
                ), number_format_i18n( $total ) ) );
                ?>
					</strong></h3>
				<p><a href="<?php 
                echo esc_url( admin_url( 'admin.php?page=wf-sn#sn_vuln' ) );
                ?>"><?php 
                esc_html_e( 'Details', 'security-ninja' );
                ?></a></p>

			<?php 
            }
            $test_scores = self::return_test_scores();
            if ( isset( $test_scores['score'] ) && '0' !== $test_scores['score'] ) {
                ?>
				<div id="testscores">
					<h3><span class="dashicons dashicons-warning"></span> <strong><?php 
                esc_html_e( 'Security Tests', 'security-ninja' );
                ?></strong></h3>
					<strong><?php 
                esc_html_e( 'Score', 'security-ninja' );
                ?></strong> <span class="result"><?php 
                echo intval( $test_scores['score'] );
                ?>%</span>
					<strong><?php 
                esc_html_e( 'Passed', 'security-ninja' );
                ?></strong> <span class="passed"><?php 
                echo intval( $test_scores['good'] );
                ?></span>
					<strong><?php 
                esc_html_e( 'Warning', 'security-ninja' );
                ?></strong> <span class="warning"><?php 
                echo intval( $test_scores['warning'] );
                ?></span>
					<strong><?php 
                esc_html_e( 'Failed', 'security-ninja' );
                ?></strong> <span class="bad"><?php 
                echo intval( $test_scores['bad'] );
                ?></span>
				</div><!-- .testresults -->

				<p><a href="<?php 
                echo esc_url( admin_url( 'admin.php?page=wf-sn' ) );
                ?>"><?php 
                esc_html_e( 'Details', 'security-ninja' );
                ?></a></p>
			<?php 
            } elseif ( '0' === $test_scores['score'] ) {
                ?>
				<h3><span class="dashicons dashicons-warning"></span> <strong>Test your website security - Run our tests</strong></h3>
				<p><a href="<?php 
                echo esc_url( admin_url( 'admin.php?page=wf-sn' ) );
                ?>"><?php 
                esc_html_e( 'Run Security Tests', 'security-ninja' );
                ?></a></p>
			<?php 
            } else {
                ?>
				<p><?php 
                esc_html_e( 'Test your website security - Run our tests', 'security-ninja' );
                ?></p>
				<?php 
            }
            ?>
			<div id="secnin-dashboard-feed"></div>
		<?php 
        }

        /**
         * Update dismissed notice
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Friday, September 8th, 2023.
         * @version v1.0.2  Thursday, November 30th, 2023.
         * @access  public static
         * @return  void
         */
        public static function wf_sn_dismiss_review() {
            check_ajax_referer( 'wf_sn_dismiss_pointer' );
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => 'Error saving dismiss pointer.',
                ) );
                wp_die();
            }
            $review = get_option( 'wf_sn_review_notice' );
            if ( !$review ) {
                $review = array();
            }
            $review['time'] = current_time( 'timestamp' ) + WEEK_IN_SECONDS * 4;
            $review['dismissed'] = true;
            if ( isset( $_POST['user_data'] ) ) {
                $review['signed_up'] = true;
            }
            update_option( 'wf_sn_review_notice', $review, false );
            die;
        }

        /**
         * Start timer for internal time measurements - saved in transient 1 hour.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @access  public static
         * @param   mixed $watchname
         * @return  void
         */
        public static function timerstart( $watchname ) {
            set_transient( 'security_ninja_' . esc_attr( $watchname ), microtime( true ), 60 * 60 * 1 );
        }

        /**
         * End timer
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @access  public static
         * @param   mixed   $watchname
         * @param   integer $digits    Default: 5
         * @return  mixed
         */
        public static function timerstop( $watchname, $digits = 5 ) {
            $return = round( microtime( true ) - get_transient( 'security_ninja_' . esc_attr( $watchname ) ), $digits );
            delete_transient( 'security_ninja_' . esc_attr( $watchname ) );
            return $return;
        }

        /**
         * Ajax callback to handle freemius opt in/out.
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, March 1st, 2022.
         * @access  public static
         * @return  void
         */
        public static function freemius_reset_activation() {
            check_ajax_referer( 'wf_sn_reset_activation' );
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => 'Error resetting Freemius activation.',
                ) );
            }
            // No further security checks needed in this case.
            if ( !secnin_fs()->is_anonymous() ) {
                return;
            }
            // Suggested by Leo to just delete the option
            delete_option( 'secnin_fs_migrated2fs' );
            secnin_fs()->connect_again();
            wp_send_json_success();
        }

        /**
         * Ajax callback to handle freemius opt in/out.
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Tuesday, February 22nd, 2022.
         * @access  public static
         * @return  void
         */
        public static function secnin_fs_opt_in() {
            $nonce = sanitize_text_field( $_POST['opt_nonce'] );
            $choice = sanitize_text_field( $_POST['choice'] );
            if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'wfsn-freemius-opt' ) ) {
                echo wp_json_encode( array(
                    'success' => false,
                    'message' => esc_html__( 'Nonce verification failed.', 'security-ninja' ),
                ) );
                exit;
            }
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'success' => false,
                    'message' => esc_html__( 'You do not have permission to do this.', 'security-ninja' ),
                ) );
            }
            // Check if choice is not empty.
            if ( !empty( $choice ) ) {
                if ( 'yes' === $choice ) {
                    if ( !is_multisite() ) {
                        secnin_fs()->opt_in();
                        // Opt in.
                    } else {
                        // Get sites.
                        $sites = \Freemius::get_sites();
                        $sites_data = array();
                        if ( !empty( $sites ) ) {
                            foreach ( $sites as $site ) {
                                $sites_data[] = secnin_fs()->get_site_info( $site );
                            }
                        }
                        secnin_fs()->opt_in(
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            false,
                            $sites_data
                        );
                    }
                    // Update freemius state.
                    update_site_option( 'wfsn_freemius_state', 'in' );
                } elseif ( 'no' === $choice ) {
                    if ( !is_multisite() ) {
                        secnin_fs()->skip_connection();
                        // Opt out.
                    } else {
                        secnin_fs()->skip_connection( null, true );
                        // Opt out for all websites.
                    }
                    // Update freemius state.
                    update_site_option( 'wfsn_freemius_state', 'skipped' );
                }
                echo wp_json_encode( array(
                    'success' => true,
                    'message' => esc_html__( 'Freemius opt choice selected.', 'security-ninja' ),
                ) );
            } else {
                echo wp_json_encode( array(
                    'success' => false,
                    'message' => esc_html__( 'Freemius opt choice not found.', 'security-ninja' ),
                ) );
            }
            exit;
        }

        /**
         * Fetch plugin version from plugin PHP header
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function get_plugin_version() {
            if ( !is_null( self::$version ) ) {
                return self::$version;
            }
            $plugin_data = get_file_data( __FILE__, array(
                'version' => 'Version',
            ), 'plugin' );
            self::$version = $plugin_data['version'];
            return self::$version;
        }

        /**
         * Fetch plugin version from plugin PHP header - Free / Pro
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function get_plugin_name() {
            $plugin_data = get_file_data( __FILE__, array(
                'name' => 'Plugin Name',
            ), 'plugin' );
            self::$name = $plugin_data['name'];
            return $plugin_data['name'];
        }

        /**
         * render_events_logger_page.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function render_events_logger_page() {
            echo '<div class="submit-test-container">';
            ?>
			<div class="fomcont">
				<h3>Events Logger</h3>

				<img src="<?php 
            echo esc_url( WF_SN_PLUGIN_URL . '/images/event-log.jpg' );
            ?>" alt="The event logger monitors changes to your website." class="tabimage">

				<p>The Events Logger monitors, tracks and reports every change on your WordPress site, both in the admin and on the
					frontend.</p>

				<p>Simple audit logging - Keep an activity log of what happens on your website and help troubleshoot bugs.</p>

				<p>Know what happened on the site at any time, in the admin and on the frontend.</p>

				<p>Easily filter trough events.</p>

				<p>Know exactly when and how an action happened, and who did it.</p>

				<p>Receive email alerts for selected groups of events.</p>

				<p>More than 50 events are instantly tracked with all details.</p>

				<p>Rotating system log - For security professionals who wants to integrate with Splunk or other SIEM - Security
					Information and Event Management systems.
				<p>

				<p class="fomlink"><a target="_blank" href="<?php 
            echo esc_url( \WPSecurityNinja\Plugin\Utils::generate_sn_web_link( 'tab_events_logger', '/events-logger/' ) );
            ?>" class="button button-primary" rel="noopener"><?php 
            esc_html_e( 'Learn more', 'security-ninja' );
            ?></a></p>

			</div>

			</div>
		<?php 
        }

        /**
         * Renders the output for the cloud firewall module
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function render_cloudfw_page() {
            echo '<div class="submit-test-container">';
            ?>
			<div class="fomcont">
				<h3><?php 
            esc_html_e( 'Firewall', 'security-ninja' );
            ?></h3>

				<img src="<?php 
            echo esc_url( WF_SN_PLUGIN_URL . '/images/firewall.jpg' );
            ?>" alt="<?php 
            esc_html_e( 'Scan Core files of WordPress', 'security-ninja' );
            ?>" class="tabimage">

				<p>The Cloud Firewall is a dynamic, continuously changing database of bad IP addresses updated every six hours. It
					contains roughly 600 million IPs that are known for distributing malware, performing brute force attacks on sites
					and doing other "bad" activities. The database is created by analyzing log files of millions of sites.</p>

				<p>By using the firewall, you will be one step ahead of the bad guys. They won't be able to login to your site.</p>

				<p>Block suspicious requests - Each pageview is checked and blocked if necessary.</p>

				<p>Login Protection - Block repeated failed login attempts, prevent brute force login attacks.</p>

				<p>Country Blocking - Prevent visits from any country from visiting.</p>

				<p>Show a message to blocked visitors or redirect them to any other URL.</p>

				<p class="fomlink"><a target="_blank" href="<?php 
            echo esc_url( \WPSecurityNinja\Plugin\Utils::generate_sn_web_link( 'tab_firewall', '/cloud-firewall/' ) );
            ?>" class="button button-primary" rel="noopener"><?php 
            esc_html_e( 'Learn more', 'security-ninja' );
            ?></a></p>

			</div>
		<?php 
            echo '</div>';
        }

        /**
         * render_malware_page.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function render_malware_page() {
            echo '<div class="submit-test-container">';
            ?>
			<div class="fomcont">
				<h3><?php 
            esc_html_e( 'Malware Scanner', 'security-ninja' );
            ?></h3>

				<img src="<?php 
            echo esc_url( WF_SN_PLUGIN_URL . '/images/malware-scanner.jpg' );
            ?>" alt="Find malicious files in your WordPress site" class="tabimage">

				<p>Protecting yourself from hacking attempts is always the best choice, but no matter, if you have a software
					firewall, enabled and use secure passwords your website can be hacked.</p>

				<h4>Security Ninja can help!</h4>

				<p>Using a powerful scanner the contents of your website is checked.</p>

				<p>Your website is scanned for code commenly found in malicious scripts and specifically known attacks.</p>

				<p>Each public plugin from wordpress.org will be checked against a master checklist to see if any plugin files has
					been modified.</p>

				<p class="fomlink"><a target="_blank" href="<?php 
            echo esc_url( \WPSecurityNinja\Plugin\Utils::generate_sn_web_link( 'tab_malware', '/malware-scanner/' ) );
            ?>" class="button button-primary" rel="noopener"><?php 
            esc_html_e( 'Learn more', 'security-ninja' );
            ?></a></p>
			</div>
			</div>
		<?php 
        }

        /**
         * render_scheduled_scanner_page.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function render_scheduled_scanner_page() {
            echo '<div class="submit-test-container">';
            ?>
			<div class="fomcont">
				<h3>Scheduled Scanner</h3>

				<img src="<?php 
            echo esc_url( WF_SN_PLUGIN_URL . '/images/scheduler.jpg' );
            ?>" alt="Scan the thousands of files that runs WordPress" class="tabimage">

				<p>The Scheduled Scanner gives you an additional peace of mind by automatically running Security Ninja and Core
					Scanner tests every day.</p>

				<p>If any changes occur or your site gets hacked you will immediately get notified via email.</p>

				<p class="fomlink"><a target="_blank" href="<?php 
            echo esc_url( \WPSecurityNinja\Plugin\Utils::generate_sn_web_link( 'tab_scheduled_scanner', '/scheduled-scanner/' ) );
            ?>" class="button button-primary" rel="noopener"><?php 
            esc_html_e( 'Learn more', 'security-ninja' );
            ?></a></p>
			</div>
			</div>
			<?php 
        }

        /**
         * Prepares the tabs for the plugin interface
         *
         * @author  Lars Koudal <me@larsik.com>
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @version v1.0.1  Friday, November 17th, 2023.
         * @access  public static
         * @param   mixed $intabs Array of tabs for plugin to be processed
         * @return  mixed
         */
        public static function return_tabs( $intabs ) {
            return $intabs;
        }

        /**
         * some things have to be loaded earlier
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @version v1.0.1  Tuesday, October 18th, 2022.
         * @version v1.0.2  Tuesday, August 1st, 2023.
         * @access  public static
         * @return  void
         */
        public static function plugins_loaded() {
            self::get_plugin_version();
            load_plugin_textdomain( 'security-ninja', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * add links to plugin's description in plugins table
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @param   mixed $links
         * @param   mixed $file
         * @return  mixed
         */
        public static function plugin_meta_links( $links, $file ) {
            return $links;
        }

        /**
         * Returns true if we are on one of the pages in this plugin
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  void
         */
        public static function is_plugin_page() {
            $current_screen = get_current_screen();
            if ( !$current_screen ) {
                return false;
            }
            if ( in_array( $current_screen->id, array('toplevel_page_wf-sn', 'security-ninja_page_wf-sn-visitor-log'), true ) || strpos( $current_screen->id, 'page_wf-sn-tools' ) !== false || strpos( $current_screen->id, 'page_wf-sn-fixes' ) !== false || strpos( $current_screen->id, 'admin_page_security-ninja-welcome' ) !== false || strpos( $current_screen->id, 'page_security-ninja-wizard' ) !== false || strpos( $current_screen->id, 'security-ninja-1_page_wf-sn-visitor-lo' ) !== false || strpos( $current_screen->id, 'security-ninja_page_wf-sn-visitor-log' ) !== false || strpos( $current_screen->id, 'security-ninja-2_page_wf-sn-visitor-log' ) !== false ) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * Define footer scripts - WP-Pointer tour
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, April 29th, 2021.
         * @access  public static
         * @return  void
         */
        public static function admin_print_footer_scripts() {
            $show_pointer = true;
            $pointer_content = '<h3>Security Ninja v.' . wf_sn::get_plugin_version() . '</h3>';
            $pointer_content .= '<p>' . __( 'Thank you for installing Security Ninja &hearts;', 'security-ninja' ) . '</p>';
            $link_to_url = admin_url( 'admin.php?page=wf-sn' );
            $pointer_content .= '<p><a href="' . esc_url( $link_to_url ) . '" class="startsecnin alignright button button-primary">Get started</a></p>';
            if ( $show_pointer ) {
                ?>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						var $menu_item = $('#toplevel_page_wf-sn');

						$menu_item.pointer({
							content: '<?php 
                echo wp_kses( $pointer_content, 'post' );
                ?>',
							position: {
								edge: 'left',
								align: 'center'
							},
							close: function() {
								$.post(ajaxurl, {
									pointer: 'secninja_tour_pointer',
									action: 'dismiss-wp-pointer'
								});
							}
						}).pointer('open');


						jQuery(document).on('click', '.startsecnin', function() {
							event.preventDefault(); // Prevent the default action of the <a> element

							// AJAX request to dismiss the pointer
							var dismissPointer = jQuery.post(ajaxurl, {
								pointer: 'secninja_tour_pointer',
								action: 'dismiss-wp-pointer'
							});

							// Wait for the AJAX request to complete
							dismissPointer.done(function(response) {
								// Close the pointer
								$menu_item.pointer('close');
								// Continue with any other actions, e.g., navigating to the link
								window.location.href = jQuery('.startsecnin').attr('href');
							});

							dismissPointer.fail(function(jqXHR, textStatus, errorThrown) {
								// Handle failure
							});
						});

					});
				</script>
			<?php 
            }
        }

        /**
         * This function is used to reload the admin page.
         * $page = the admin page we are passing (index.php or options-general.php)
         * $tab = the NEXT pointer array key we want to display
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, April 29th, 2021.
         * @access  public static
         * @param   mixed $page
         * @param   mixed $tab
         * @return  mixed
         */
        public static function get_admin_url( $page, $tab ) {
            $url = admin_url();
            $url .= $page;
            $url = add_query_arg( 'tab', $tab, $url );
            return $url;
        }

        /**
         * Enqueue CSS and JS scripts on plugin's pages
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @return  void
         */
        public static function enqueue_scripts( $hook ) {
            if ( 'wp-admin/update.php' === $GLOBALS['pagenow'] ) {
                return;
            }
            if ( 'index.php' === $hook ) {
                wp_enqueue_script(
                    'security-ninja-dashboard',
                    plugin_dir_url( __FILE__ ) . 'js/min/sn-wp_dashboard-min.js',
                    array('jquery'),
                    self::get_plugin_version(),
                    true
                );
                $utm_source = 'security_ninja_free';
                wp_localize_script( 'security-ninja-dashboard', 'dashboardData', array(
                    'headline'     => sprintf( __( 'Latest from %s', 'security-ninja' ), 'WPSecurityNinja.com' ),
                    'blog_link'    => \WPSecurityNinja\Plugin\Utils::generate_sn_web_link( 'dashboard', '/blog/' ),
                    'utm_source'   => esc_attr( $utm_source ),
                    'utm_medium'   => 'plugin',
                    'utm_content'  => 'dashboard_widget',
                    'utm_campaign' => esc_attr( 'security_ninja_v' . self::get_plugin_version() ),
                ) );
            }
            wp_enqueue_script(
                'sn-global',
                WF_SN_PLUGIN_URL . 'js/min/sn-global-min.js',
                array('jquery'),
                self::get_plugin_version(),
                true
            );
            // Test if we should show pointer - introduced in version 5.118
            if ( !self::is_plugin_page() && current_user_can( 'manage_options' ) ) {
                // Check to see if user has already dismissed the pointer tour
                $dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
                $do_tour = !in_array( 'secninja_tour_pointer', $dismissed, true );
                // If not, we are good to continue - We check if the plugin has been registered or user wants to be anon
                if ( $do_tour ) {
                    wp_enqueue_style( 'wp-pointer' );
                    wp_enqueue_script( 'wp-pointer' );
                    add_action( 'admin_print_footer_scripts', array(__NAMESPACE__ . '\\wf_sn', 'admin_print_footer_scripts') );
                }
            }
            if ( self::is_plugin_page() ) {
                wp_enqueue_script( 'jquery-ui-tabs' );
                wp_enqueue_script(
                    'sn-jquery-plugins',
                    WF_SN_PLUGIN_URL . 'js/min/sn-jquery-plugins-min.js',
                    array('jquery'),
                    self::get_plugin_version(),
                    true
                );
                wp_enqueue_style( 'wp-jquery-ui-dialog' );
                wp_enqueue_script( 'jquery-ui-dialog' );
                // Parsing data to sn-common.js via $cp_sn_data
                wp_register_script(
                    'sn-js',
                    WF_SN_PLUGIN_URL . 'js/min/sn-common-min.js',
                    array('jquery'),
                    self::get_plugin_version(),
                    true
                );
                wp_enqueue_script( 'sn-js' );
                $js_vars = array(
                    'sn_plugin_url'          => WF_SN_PLUGIN_URL,
                    'nonce_run_tests'        => wp_create_nonce( 'wf_sn_run_tests' ),
                    'nonce_refresh_update'   => wp_create_nonce( 'wf_sn_refresh_update' ),
                    'nonce_dismiss_pointer'  => wp_create_nonce( 'wf_sn_dismiss_pointer' ),
                    'nonce_reset_activation' => wp_create_nonce( 'wf_sn_reset_activation' ),
                    'nonce_latest_events'    => wp_create_nonce( 'wf_sn_latest_events' ),
                    'lc_version'             => self::get_plugin_version(),
                    'lc_site'                => get_home_url(),
                    'lc_ip'                  => $_SERVER['REMOTE_ADDR'],
                );
                wp_localize_script( 'sn-js', 'wf_sn', $js_vars );
                wp_enqueue_style(
                    'sn-css',
                    WF_SN_PLUGIN_URL . 'css/min/sn-style.css',
                    array(),
                    self::get_plugin_version()
                );
                // Removing scripts and styles from other plugins we know mess up the interface
                wp_dequeue_style( 'uiStyleSheet' );
                wp_dequeue_style( 'wpcufpnAdmin' );
                wp_dequeue_style( 'unifStyleSheet' );
                wp_dequeue_style( 'wpcufpn_codemirror' );
                wp_dequeue_style( 'wpcufpn_codemirrorTheme' );
                wp_dequeue_style( 'collapse-admin-css' );
                wp_dequeue_style( 'jquery-ui-css' );
                wp_dequeue_style( 'tribe-common-admin' );
                wp_dequeue_style( 'file-manager__jquery-ui-css' );
                wp_dequeue_style( 'file-manager__jquery-ui-css-theme' );
                wp_dequeue_style( 'wpmegmaps-jqueryui' );
                wp_dequeue_style( 'facebook-plugin-css' );
                wp_dequeue_style( 'facebook-tip-plugin-css' );
                wp_dequeue_style( 'facebook-member-plugin-css' );
                wp_dequeue_style( 'kc-testimonial-admin' );
                wp_dequeue_style( 'jquery-ui-style' );
            }
        }

        /**
         * add entry to admin menu
         *
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Friday, February 5th, 2021.
         * @access  public static
         * @return  void
         */
        public static function admin_menu() {
            $page_title = 'Security';
            $menu_title = 'Security Ninja';
            $capability = 'manage_options';
            $menu_slug = 'wf-sn';
            $icon_url = '';
            $position = null;
            $icon_url = self::get_icon_svg();
            $notification_count = false;
            if ( class_exists( __NAMESPACE__ . '\\Wf_Sn_Vu' ) ) {
                $vu_options = wf_sn_vu::get_options();
                if ( $vu_options['enable_admin_notification'] ) {
                    $notification_count = Wf_Sn_Vu::return_vuln_count();
                }
            }
            add_menu_page(
                $page_title,
                ( $notification_count ? sprintf( $menu_title . ' <span class="awaiting-mod">%d</span>', $notification_count ) : $menu_title ),
                $capability,
                $menu_slug,
                array(__NAMESPACE__ . '\\wf_sn', 'main_page'),
                $icon_url
            );
        }

        /**
         * do_filter_debug_information.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, January 13th, 2021.
         * @access  public static
         * @param   mixed $info
         * @return  mixed
         */
        public static function do_filter_debug_information( $info ) {
            $info['wp-paths-sizes']['label'] = 'Directories';
            unset($info['wp-paths-sizes']['fields']['wordpress_size']);
            unset($info['wp-paths-sizes']['fields']['uploads_size']);
            unset($info['wp-paths-sizes']['fields']['themes_size']);
            unset($info['wp-paths-sizes']['fields']['plugins_size']);
            unset($info['wp-paths-sizes']['fields']['database_size']);
            unset($info['wp-paths-sizes']['fields']['total_size']);
            unset($info['wp-plugins-active']['fields']['Security Ninja']);
            return $info;
        }

        /**
         * Add an error to the settings_error
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @param   mixed  $message
         * @param   string $type    Default: 'error'
         * @param   string $code    Default: 'wf_sn'
         * @return  void
         */
        public static function add_settings_error( $message, $type = 'error', $code = 'wf_sn' ) {
            global $wp_settings_errors;
            $new_wp_settings = $wp_settings_errors;
            $new_wp_settings[] = array(
                'setting' => 'wf_sn_options',
                'code'    => $code,
                'message' => $message,
                'type'    => $type,
            );
            set_transient( 'settings_errors', $new_wp_settings );
        }

        /**
         * Display warning if running in an too old WordPress version
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  void
         */
        public static function min_version_error() {
            echo '<div class="notice notice-error"><p>This plugin requires WordPress version 4.4 or higher to function properly. You\'re using WordPress version ' . esc_attr( get_bloginfo( 'version' ) ) . '. Please <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '" title="Update WP core">update</a>.</p></div>';
            // i8n
        }

        /**
         * return default options
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function default_options() {
            $defaults = array(
                'license_key'                => '',
                'license_active'             => false,
                'license_expires'            => '',
                'license_type'               => '',
                'license_hide'               => false,
                'first_version'              => '',
                'first_install'              => '',
                'remove_settings_deactivate' => false,
            );
            return $defaults;
        }

        /**
         * get plugin's options
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function get_options() {
            if ( isset( self::$options ) && 0 < count( self::$options ) ) {
                return self::$options;
            }
            $options = get_option( 'wf_sn_options', array() );
            if ( isset( $options[0] ) ) {
                unset($options[0]);
            }
            if ( !is_array( $options ) ) {
                $options = array();
            }
            $options = array_merge( self::default_options(), $options );
            self::$options = $options;
            // her sætter vi globale options.
            return $options;
        }

        /**
         * all settings are saved in one option
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  void
         */
        public static function register_settings() {
            register_setting( 'wf_sn_options', 'wf_sn_options', array(__NAMESPACE__ . '\\wf_sn', 'sanitize_settings') );
            // we do not want to redirect everyone
            $redirect_user = false;
            if ( isset( $_POST['foo'], $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wf-sn-install-routines' ) ) {
                if ( !current_user_can( 'manage_options' ) ) {
                    wp_send_json_error( array(
                        'success' => false,
                        'message' => esc_html__( 'You do not have permission to do this.', 'security-ninja' ),
                    ) );
                }
                $redirect_user = true;
            }
            if ( $redirect_user ) {
                // Set to false per default, so isset check not needed.
                if ( !isset( $_POST['_wp_http_referer'] ) ) {
                    $_POST['_wp_http_referer'] = wp_login_url();
                }
                $url = sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) );
                wp_safe_redirect( urldecode( $url ) );
                exit;
            }
        }

        /**
         * Returns icon in SVG format
         * Thanks Yoast for code.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @param   boolean $base64 Return SVG in base64 or not
         * @param   string  $color  Default: '82878c'
         * @return  mixed
         */
        public static function get_icon_svg( $base64 = true, $color = '82878c' ) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500">
																													<g fill="#' . $color . '">
																													<path d="M171.117 262.277c14.583-.142 25.832 20.664 25.921 35.25.094 15.265-11.418 37.682-26.678 37.227-14.687-.438-23.797-22.605-23.494-37.296.295-14.24 10.095-35.044 24.25-35.181zM322.387 263.03c14.584-.142 25.832 20.664 25.922 35.25.093 15.265-11.419 37.681-26.679 37.227-14.686-.438-23.797-22.606-23.493-37.296.294-14.24 10.094-35.044 24.25-35.182z"/>
																													<path d="M331.348 26.203c0-.107 98.038-7.914 98.038-7.914s-9.219 91.716-10.104 96.592c1.277-3.3 22.717-46.002 22.818-46.002.105 0 53.047 69.799 53.047 69.799l-46.63 42.993c26.6 30.762 41.632 67.951 41.724 107.653.239 103.748-110.253 191.827-245.68 191.091-130.352-.706-239.977-86.977-240.475-188.91-.5-102.38 105.089-191.741 239.663-192.095 38.677-.1 74.34 6.068 105.82 17.154-3.241-16.067-18.22-90.265-18.22-90.36zm-85.421 157.959c-74.098-1.337-161.3 41.627-161.054 105.87.247 63.88 87.825 103.981 160.683 104.125 78.85.154 164.156-41.58 163.722-106.614-.428-64.436-86.566-101.996-163.351-103.381z"/>
																													</g>
																													</svg>';
            if ( $base64 ) {
                return 'data:image/svg+xml;base64,' . base64_encode( $svg );
            }
            return $svg;
        }

        /**
         * Sanitize settings on save
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @access  public static
         * @param   mixed $new_values
         * @return  void
         */
        public static function sanitize_settings( $new_values ) {
            $old_options = self::get_options();
            $old_options['remove_settings_deactivate'] = 0;
            if ( !is_array( $new_values ) ) {
                $arr = array();
                $arr[] = $new_values;
                $new_values = $arr;
            }
            foreach ( $new_values as $key => $value ) {
                $new_values[$key] = sanitize_text_field( $value );
            }
            return array_merge( $old_options, $new_values );
        }

        /**
         * whole options page
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  void
         */
        public static function main_page() {
            global $secnin_fs;
            // Display setting errors
            settings_errors();
            $tabs = array();
            $tabs[] = array(
                'id'       => 'sn_tests',
                'class'    => '',
                'label'    => __( 'Tests', 'security-ninja' ),
                'callback' => array(__NAMESPACE__ . '\\wf_sn', 'tab_tests'),
            );
            $tabs = apply_filters( 'sn_tabs', $tabs );
            ?>
			<div class="wrap">
				<?php 
            wf_sn::show_topbar();
            ?>
				<div class="secnin_content_wrapper">
					<div class="secnin_content_cell" id="secnin_content_top">
						<div class="nav-tab-wrapper" id="wf-sn-tabs">
							<?php 
            foreach ( $tabs as $tab ) {
                $extra = '';
                $class = 'nav-tab ' . $tab['class'];
                if ( 'sn_tests' === $tab['id'] ) {
                    $class .= ' nav-tab-active';
                }
                if ( !empty( $tab['label'] ) ) {
                    if ( isset( $tab['count'] ) ) {
                        $extra = ' <span class="warn-count">' . intval( $tab['count'] ) . '</span>';
                    }
                    echo '<a href="#' . esc_attr( $tab['id'] ) . '" class="' . esc_attr( $class ) . '" id="' . esc_attr( $tab['id'] ) . '-tab">' . esc_html( $tab['label'] ) . wp_kses( $extra, array(
                        'span' => array(
                            'class' => array(),
                        ),
                    ) ) . '</a>';
                }
            }
            ?>
						</div>
						<div id="sn_tabscont">
							<?php 
            foreach ( $tabs as $tab ) {
                if ( !empty( $tab['callback'] ) ) {
                    $class = 'wf-sn-tab';
                    if ( 'sn_tests' === $tab['id'] ) {
                        $class .= ' active';
                    }
                    echo '<div id="' . esc_attr( $tab['id'] ) . '" class="' . esc_attr( $class ) . '">';
                    call_user_func( $tab['callback'] );
                    echo '</div>';
                }
            }
            ?>
						</div>
						<?php 
            include_once 'includes/sidebar.php';
            ?>
					</div>
				</div>
				<?php 
            if ( function_exists( '\\WPSecurityNinja\\Plugin\\secnin_fs' ) ) {
                $helpscoutbeacon = '';
                if ( secnin_fs()->is_registered() ) {
                    if ( secnin_fs()->is_tracking_allowed() ) {
                        $helpscoutbeacon = '<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script><script type="text/javascript">window.Beacon("init", "5d0c1eff-d44a-48c4-beff-d88f5964e59e")</script>';
                    }
                }
                $allowed_html = array(
                    'script' => array(
                        'type' => array(),
                        'src'  => array(),
                    ),
                );
                echo wp_kses( $helpscoutbeacon, $allowed_html );
            }
            echo '</div>';
        }

        /**
         * Compares two array values by for usort()
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @param   mixed $a
         * @param   mixed $b
         * @return  mixed
         */
        public static function cmp_status_score( $a, $b ) {
            if ( $a === $b ) {
                return 0;
            }
            return ( $a['status'] < $b['status'] ? -1 : 1 );
        }

        /**
         * returns the current score of the tests + output
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function return_test_scores() {
            global $wpdb;
            $testsresults = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wf_sn_tests LIMIT 100;", ARRAY_A );
            $bad = 0;
            $warning = 0;
            $good = 0;
            $score = 0;
            $total = 0;
            if ( $testsresults ) {
                $totaltests = Wf_Sn_Tests::return_security_tests();
                foreach ( $testsresults as $test_details ) {
                    $total += $test_details['score'];
                    if ( 10 === intval( $test_details['status'] ) ) {
                        ++$good;
                        $score += $test_details['score'];
                    } elseif ( 0 === intval( $test_details['status'] ) ) {
                        ++$bad;
                    } else {
                        ++$warning;
                    }
                }
            }
            if ( $total > 0 && $score > 0 ) {
                $score = round( $score / $total * 100 );
            } else {
                $score = 0;
            }
            $response = array();
            $response['good'] = $good;
            $response['bad'] = $bad;
            $response['warning'] = $warning;
            $response['score'] = $score;
            $all_tests = Wf_Sn_Tests::return_security_tests();
            // generate output
            $output = '';
            $output .= '<div id="counters">';
            $output .= '<span class="good">' . $good . '<br><i>' . __( 'Passed', 'security-ninja' ) . '</i></span>';
            $output .= '<span class="warning">' . $warning . '<br><i>' . __( 'Warnings', 'security-ninja' ) . '</i></span>';
            $output .= '<span class="bad">' . $bad . '<br><i>' . __( 'Failed', 'security-ninja' ) . '</i></span>';
            $output .= '<span class="score">' . $score . '%<br><i>' . __( 'Score', 'security-ninja' ) . '</i></span>';
            $output .= '</div>';
            $response['output'] = $output;
            return $response;
        }

        /**
         * Gets test results from database
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  mixed
         */
        public static function get_test_results() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wf_sn_tests';
            $testsresults = $wpdb->get_results( "SELECT * FROM {$table_name};", ARRAY_A );
            if ( !$testsresults ) {
                return false;
            }
            $response = array();
            foreach ( $testsresults as $tr ) {
                $response['test'][$tr['testid']] = $tr;
            }
            return $response;
        }

        /**
         * tab_tests.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Wednesday, February 3rd, 2021.
         * @access  public static
         * @return  void
         */
        public static function tab_tests() {
            $testsresults = self::get_test_results();
            ?>
				<div class="submit-test-container card">
					<h3><?php 
            esc_html_e( 'Test your website security', 'security-ninja' );
            ?></h3>
					<div class="testresults" id="testscores">
						<?php 
            $scores = self::return_test_scores();
            if ( isset( $scores['output'] ) ) {
                $allowed_html = array(
                    'div'  => array(
                        'id' => array(),
                    ),
                    'span' => array(
                        'class' => array(),
                    ),
                    'br'   => array(),
                    'i'    => array(),
                );
                echo wp_kses( $scores['output'], $allowed_html );
            }
            ?>
					</div>
					<?php 
            $tests = wf_sn_tests::return_security_tests();
            $out = '<input type="submit" value="' . __( 'Run Tests', 'security-ninja' ) . '" id="run-selected-tests" class="button button-primary button-hero" name="Submit" />';
            $out .= '<span class="runtestsbn spinner"></span>';
            $out .= '<div id="secninja-tests-quickselect">';
            $out .= '<span>' . __( 'Quick Filter', 'security-ninja' ) . ':</span><ul><li><a href="#" id="sn-quickselect-all">' . __( 'All', 'security-ninja' ) . '</a></li><li><a href="#" id="sn-quickselect-failed">' . __( 'Failed', 'security-ninja' ) . '</a></li><li><a href="#"  id="sn-quickselect-warning">' . __( 'Warning', 'security-ninja' ) . '</a></li><li><a href="#" id="sn-quickselect-okay">' . __( 'Passed', 'security-ninja' ) . '</a></li><li><a href="#" id="sn-quickselect-untested">' . __( 'Untested', 'security-ninja' ) . '</a></li></ul>';
            $out .= '</div>';
            $out .= '<table class="wp-list-table widefat striped" cellspacing="0" id="security-ninja">';
            $out .= '<thead><tr>';
            $out .= '<td id="cb" class="manage-column column-cb check-column">';
            $out .= '<label class="screen-reader-text" for="cb-select-all-1">' . __( 'Select All', 'security-ninja' ) . '</label>';
            $out .= '<input id="cb-select-all-1" type="checkbox"></td>';
            $out .= '<th class="column-primary">' . __( 'Security Test', 'security-ninja' ) . '</th>';
            $out .= '<th><span class="secnin_expand_all_details">' . __( 'Expand All', 'security-ninja' ) . '</span></th>';
            $out .= '</tr></thead>';
            $out .= '<tbody>';
            if ( is_array( $tests ) ) {
                $stepid = 0;
                // test Results
                foreach ( $tests as $test_name => $details ) {
                    if ( 'ad_' === substr( $test_name, 0, 3 ) || '_' === $test_name[0] ) {
                        continue;
                    }
                    ++$stepid;
                    $outlabel = '';
                    // hvis vi har kørt testen før
                    if ( isset( $testsresults['test'][$test_name]['status'] ) ) {
                        $out .= '<tr class="wf-sn-test-row-status-' . $testsresults['test'][$test_name]['status'] . ' test test_' . $test_name . '">';
                        if ( 0 === intval( $testsresults['test'][$test_name]['status'] ) ) {
                            $outlabel = '<span class="wf-sn-label sn-error">' . __( 'Fail', 'security-ninja' ) . '</span>';
                        } elseif ( 5 === intval( $testsresults['test'][$test_name]['status'] ) ) {
                            $outlabel = '<span class="wf-sn-label sn-warning">' . __( 'Warning', 'security-ninja' ) . '</span>';
                        } elseif ( 10 === intval( $testsresults['test'][$test_name]['status'] ) ) {
                            $outlabel = '<span class="wf-sn-label sn-success">' . __( 'OK', 'security-ninja' ) . '</span>';
                        }
                    } else {
                        // lars - kommenteret ud ellers kom der er et "d" med
                        $out .= '<tr class="wf-sn-test-row-status-null test test_' . $test_name . '">';
                        $outlabel = '<span class="wf-sn-label sn-untested">' . __( 'Untested', 'security-ninja' ) . '</span>';
                    }
                    $checkedoutput = checked( true, true, false );
                    if ( !isset( $options['run_tests'] ) ) {
                        $checkedoutput = checked( true, true, false );
                    } else {
                        $options = self::get_options();
                        if ( in_array( $test_name, $options['run_tests'], true ) ) {
                            $checkedoutput = checked( true, true, false );
                        } else {
                            $checkedoutput = checked( false, true, false );
                        }
                    }
                    $out .= '<th scope="row" class="check-column"><input id="cb-select-' . $stepid . '" type="checkbox" name="sntest[]" value="' . sanitize_key( $test_name ) . '" ' . $checkedoutput . '/></th>';
                    $out .= '<td class="column-primary" data-colname="Test">' . $outlabel . '<label for="cb-select-' . $stepid . '"><span class="wf-sn-test-title">' . $details['title'] . '</span></label>';
                    if ( isset( $testsresults['test'][$test_name]['msg'] ) ) {
                        // only add details if failed or warning
                        $outmessage = $testsresults['test'][$test_name]['msg'];
                        // Add the details if exists
                        if ( $testsresults['test'][$test_name]['details'] ) {
                            $outmessage .= ' ' . $testsresults['test'][$test_name]['details'];
                        }
                        $out .= '<span class="sn-result-details">' . $outmessage . '</span>';
                    } else {
                        // empty - can be filled via ajax response
                        $out .= '<span class="sn-result-details"></span>';
                    }
                    $out .= '<button type="button" class="toggle-row"> <span class="screen-reader-text">' . __( 'Show details', 'security-ninja' ) . '</span> </button>';
                    $testcssid = 'tdesc-test-id-' . $test_name;
                    $out .= '<div class="testdesc ' . esc_attr( $testcssid ) . '"></div>';
                    if ( class_exists( __NAMESPACE__ . '\\wf_sn_af_fix_' . $test_name ) && isset( $details['status'] ) && 10 !== $details['status'] ) {
                        $details_label = __( 'Details &amp; Fix', 'security-ninja' );
                    } else {
                        $details_label = __( 'Details', 'security-ninja' );
                    }
                    $out .= '<div class="testtimedetails ' . esc_attr( $test_name ) . '"><span class="spinner"></span><span class="lasttest"></span><span class="runtime"></span><span class="score"></span>';
                    $outstatus = '';
                    if ( isset( $testsresults['test'][$test_name]['status'] ) ) {
                        $outstatus = $testsresults['test'][$test_name]['status'];
                    }
                    $out .= '<div class="snautofix" data-test-id="' . esc_attr( $test_name ) . '" data-test-status="' . esc_attr( $outstatus ) . '"></div>';
                    $out .= '</div>';
                    $out .= '</td><td><div class="sn-details"><a data-test-id="' . esc_attr( $test_name ) . '" data-test-status="' . esc_attr( $outstatus ) . '" href="#' . esc_attr( $test_name ) . '" class="action">' . $details_label . '</a></div>';
                    $out .= '</td></tr>';
                }
            }
            $out .= '</tbody>';
            $out .= '<tfoot><tr>';
            $out .= '<td class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-2">' . __( 'Select All', 'security-ninja' ) . '</label><input id="cb-select-all-2" type="checkbox"></td>';
            $out .= '<th class="column-primary">' . __( 'Security Test', 'security-ninja' ) . '</th><th></th>';
            $out .= '</tr></tfoot>';
            $out .= '</table>';
            $allowed_html = array_merge( wp_kses_allowed_html( 'post' ), array(
                'input' => array(
                    'type'    => array(),
                    'name'    => array(),
                    'id'      => array(),
                    'value'   => array(),
                    'checked' => array(),
                    'class'   => array(),
                ),
            ) );
            $out = apply_filters( 'sn_tests_table', $out, $tests );
            echo wp_kses( $out, $allowed_html );
            ?>
					<p>
						<?php 
            esc_html_e( 'Although these tests cover years of best practices in security, getting all test green does not guarantee your site will not get hacked. Likewise, having them all red does not mean you will get hacked.', 'security-ninja' );
            ?>
					</p>
					<p>
						<?php 
            esc_html_e( "Please read each test's detailed information to see if it represents a real security issue for your site.", 'security-ninja' );
            ?>
					</p>
				</div>
	<?php 
        }

        /**
         * Returns all details about a test in JSON - used in AJAX
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function get_single_test_details() {
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                check_ajax_referer( 'wf_sn_run_tests' );
            }
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Failed.', 'security-ninja' ),
                ) );
            }
            if ( isset( $_POST['testid'] ) ) {
                $testid = sanitize_key( $_POST['testid'] );
                if ( $testid !== $_POST['testid'] ) {
                    wp_send_json_error();
                }
                global $wpdb;
                $table_name = $wpdb->prefix . 'wf_sn_tests';
                $testdata = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wf_sn_tests WHERE testid = %s", $testid ) );
                if ( $testdata ) {
                    wp_send_json_success( $testdata );
                } else {
                    wp_send_json_error();
                }
            } else {
                wp_send_json_error();
            }
            die;
        }

        /**
         * Runs single test via AJAX call
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @return  void
         */
        public static function run_single_test() {
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                check_ajax_referer( 'wf_sn_run_tests' );
            }
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Error. Please contact support', 'security-ninja' ),
                ) );
            }
            if ( isset( $_POST['stepid'] ) ) {
                $stepid = intval( $_POST['stepid'] );
                $testarr = $_POST['testarr'];
                if ( !isset( $testarr[$stepid] ) ) {
                    return false;
                }
                $response = false;
                $testid = sanitize_key( $testarr[$stepid] );
                if ( $testid ) {
                    self::timerstart( $testid );
                    $response = wf_sn_tests::$testid();
                }
                if ( $response ) {
                    $json_response = array();
                    // does the next element in the selected tests arr exist?
                    if ( isset( $testarr[$stepid + 1] ) ) {
                        $json_response['nexttest'] = $stepid + 1;
                    } else {
                        // there are no more tests to be made so this is the last
                        $json_response['nexttest'] = -1;
                    }
                    $security_tests = wf_sn_tests::return_security_tests();
                    // allow overwriting with function response
                    if ( isset( $response['msg_bad'] ) ) {
                        $test['msg_bad'] = $response['msg_bad'];
                    }
                    if ( isset( $response['msg_ok'] ) ) {
                        $test['msg_ok'] = $response['msg_ok'];
                    }
                    if ( isset( $response['msg_warning'] ) ) {
                        $test['msg_warning'] = $response['msg_warning'];
                    }
                    if ( !isset( $response['msg'] ) ) {
                        $response['msg'] = '';
                    }
                    $json_response['msg'] = $response['msg'];
                    if ( 10 === $response['status'] ) {
                        //$json_response['msg']   = sprintf( $security_tests[ $testid ]['msg_ok'], $response['msg'] );
                        // $json_response['msg']   = $response['msg'];
                        $json_response['label'] = '<span class="wf-sn-label sn-success">' . __( 'OK', 'security-ninja' ) . '</span>';
                    } elseif ( 0 === $response['status'] ) {
                        // $json_response['msg']   = $response['msg'];
                        $json_response['label'] = '<span class="wf-sn-label sn-error">' . __( 'Fail', 'security-ninja' ) . '</span>';
                    } else {
                        $json_response['label'] = '<span class="wf-sn-label sn-warning">' . __( 'Warning', 'security-ninja' ) . '</span>';
                        // $json_response['msg'] = sprintf( $security_tests[ $testid ]['msg_warning'], $response['msg'] );
                    }
                    // $details                 = $security_tests[ $testid ];
                    $json_response['status'] = $response['status'];
                    $testscorearr = array(
                        'testid'    => $testid,
                        'timestamp' => current_time( 'mysql' ),
                        'title'     => $security_tests[$testid]['title'],
                        'status'    => $response['status'],
                        'score'     => $security_tests[$testid]['score'],
                        'msg'       => $json_response['msg'],
                    );
                    // A way to add details
                    if ( isset( $response['details'] ) ) {
                        $testscorearr['details'] = $response['details'];
                        $json_response['details'] = $response['details'];
                    }
                    $endtime = self::timerstop( $testid );
                    if ( $endtime ) {
                        $testscorearr['runtime'] = $endtime;
                    }
                    self::update_test_score( $testscorearr );
                    $scores = self::return_test_scores();
                    if ( $scores ) {
                        $json_response['scores'] = $scores;
                    }
                    wp_send_json_success( $json_response );
                } else {
                    wp_send_json_error( $testid );
                }
            }
            wp_send_json_error( '$stepid not set' );
            die;
        }

        /**
         * saved test result
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @param   mixed $testresult
         * @return  void
         */
        public static function update_test_score( $testresult ) {
            if ( !$testresult ) {
                return false;
            }
            global $wpdb;
            $table_name = $wpdb->prefix . 'wf_sn_tests';
            if ( !isset( $testresult['details'] ) ) {
                $testresult['details'] = '';
            }
            $wpdb->replace( $table_name, $testresult, array(
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
                '%s'
            ) );
        }

        /**
         * Runs the tests
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, January 14th, 2021.
         * @access  public static
         * @param   boolean $return Default: false
         * @return  void
         */
        public static function run_tests( $return_response = false ) {
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                // Attempt to verify the first nonce
                $nonce_verified = wp_verify_nonce( $_REQUEST['_wpnonce'], 'wf_sn_run_tests' );
                // If the first nonce verification fails, try the second nonce
                if ( !$nonce_verified ) {
                    $nonce_verified = wp_verify_nonce( $_REQUEST['_wpnonce'], 'secnin_scheduled_scanner' );
                }
                // If both nonce verifications fail, terminate the AJAX call
                if ( !$nonce_verified ) {
                    wp_die( 'Nonce verification failed.', 'Nonce Verification', array(
                        'response' => 403,
                    ) );
                }
            }
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( array(
                    'success' => false,
                    'message' => esc_html__( 'You do not have permission to do this.', 'security-ninja' ),
                ) );
            }
            $step = ( isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1 );
            if ( 1 === $step ) {
                self::timerstart( 'wf_sn_run_tests' );
            }
            if ( !$step ) {
                $step = 0;
            }
            ++$step;
            $json_response = array();
            if ( $step ) {
                $json_response['step'] = $step;
            }
            $security_tests = wf_sn_tests::return_security_tests();
            if ( $security_tests ) {
                $totaltests = count( $security_tests );
                $json_response['totaltests'] = $totaltests;
            }
            $set_time_limit = set_time_limit( 200 );
            $loop_count = 1;
            $start_time = microtime( true );
            $test_description['last_run'] = time();
            if ( is_array( $security_tests ) ) {
                foreach ( $security_tests as $test_name => $test ) {
                    if ( '_' === $test_name[0] || in_array( $test_name, self::$skip_tests, true ) || 'ad_' === substr( $test_name, 0, 3 ) ) {
                        continue;
                    }
                    // If this is the one to be tested ...
                    if ( $step === $loop_count ) {
                        $response = wf_sn_tests::$test_name();
                        $json_response['last_test'] = $test['title'];
                        if ( isset( $response['status'] ) ) {
                            $json_response['last_status'] = $response['status'];
                        }
                        $json_response['last_score'] = $test['score'];
                        // allow overwriting with function response
                        if ( isset( $response['msg_bad'] ) ) {
                            $test['msg_bad'] = $response['msg_bad'];
                        }
                        if ( isset( $response['msg_ok'] ) ) {
                            $test['msg_ok'] = $response['msg_ok'];
                        }
                        if ( isset( $response['msg_warning'] ) ) {
                            $test['msg_warning'] = $response['msg_warning'];
                        }
                        if ( !isset( $response['msg'] ) ) {
                            $response['msg'] = '';
                        }
                        if ( 10 === intval( $response['status'] ) ) {
                            $json_response['last_msg'] = sprintf( $test['msg_ok'], $response['msg'] );
                        } elseif ( 0 === intval( $response['status'] ) ) {
                            $json_response['last_msg'] = sprintf( $test['msg_bad'], $response['msg'] );
                        } else {
                            $json_response['last_msg'] = sprintf( $test['msg_warning'], $response['msg'] );
                        }
                        // Updates the results
                        $resultssofar['test'][$test_name] = array(
                            'title'  => $test['title'],
                            'status' => $response['status'],
                            'score'  => $test['score'],
                            'msg'    => $json_response['last_msg'],
                        );
                        // A way to add details
                        if ( isset( $response['details'] ) ) {
                            $resultssofar['test'][$test_name]['details'] = $response['details'];
                        }
                        // No more tests - let us stop
                        if ( $step >= $totaltests ) {
                            $json_response['step'] = 'done';
                            $resultssofar['last_run'] = time();
                            $stoptime = self::timerstop( 'wf_sn_run_tests' );
                            if ( $stoptime ) {
                                $resultssofar['run_time'] = $stoptime;
                            }
                            do_action( 'security_ninja_done_testing', $test_description, $resultssofar['run_time'] );
                        }
                        update_option( 'wf_sn_results', $resultssofar, false );
                        wp_send_json_success( $json_response );
                    }
                    ++$loop_count;
                }
            }
            if ( $return_response ) {
                $resultssofar = get_option( 'wf_sn_results' );
                return $resultssofar;
            } else {
                wp_send_json_success( $json_response );
            }
        }

        /**
         * RUNS ALL TESTS, not just one
         * LARS - SKAL GEMMES INDTIL VIDERE - Bruges af scheduled scanner
         * Bruges kun af cron job, behøver ikke validere bruger rettigheder
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @param   boolean $return Default: false
         * @return  void
         */
        public static function run_all_tests( $return_data = false ) {
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                // Attempt to verify the first nonce
                $nonce_verified = wp_verify_nonce( $_REQUEST['nonce'], 'wf_sn_run_tests' );
                // If the first nonce verification fails, try the second nonce
                if ( !$nonce_verified ) {
                    $nonce_verified = wp_verify_nonce( $_REQUEST['nonce'], 'secnin_scheduled_scanner' );
                }
                // If both nonce verifications fail, terminate the AJAX call
                if ( !$nonce_verified ) {
                    wp_die( 'Nonce verification failed.', 'Nonce Verification', array(
                        'response' => 403,
                    ) );
                }
            }
            // if ( ! current_user_can( 'manage_options' ) ) {
            // 	wp_send_json_error(
            // 		array(
            // 			'success' => false,
            // 			'message' => esc_html__( 'You do not have permission to do this.', 'security-ninja' ),
            // 		)
            // 	);
            // }
            self::timerstart( 'wf_sn_run_all_tests' );
            $security_tests = wf_sn_tests::return_security_tests();
            $resultssofar = array();
            $set_time_limit = set_time_limit( 200 );
            $loop_count = 1;
            $resultssofar['last_run'] = time();
            if ( is_array( $security_tests ) ) {
                foreach ( $security_tests as $test_name => $test ) {
                    if ( '_' === $test_name[0] || in_array( $test_name, self::$skip_tests, true ) || 'ad_' === substr( $test_name, 0, 3 ) ) {
                        continue;
                    }
                    $response = wf_sn_tests::$test_name();
                    $json_response = array();
                    $json_response['last_test'] = $test['title'];
                    $json_response['last_status'] = $response['status'];
                    $json_response['last_score'] = $test['score'];
                    if ( !isset( $response['msg'] ) ) {
                        $response['msg'] = '';
                    }
                    // Setting appropriate message
                    if ( 10 === intval( $response['status'] ) ) {
                        //						$json_response['last_msg'] = sprintf( $test['msg_ok'], $response['msg'] );
                        // @todo
                        $json_response['last_msg'] = $response['msg'];
                    } elseif ( 0 === intval( $response['status'] ) ) {
                        $json_response['last_msg'] = $response['msg'];
                    } else {
                        $json_response['last_msg'] = $response['msg'];
                    }
                    // Updates the results
                    $resultssofar['test'][$test_name] = array(
                        'title'  => $test['title'],
                        'status' => $response['status'],
                        'score'  => $test['score'],
                        'msg'    => $json_response['last_msg'],
                    );
                    ++$loop_count;
                }
                // No more tests - let us stop
                $json_response['step'] = 'done';
                $resultssofar['last_run'] = time();
                $stoptime = self::timerstop( 'wf_sn_run_all_tests' );
                if ( $stoptime ) {
                    $resultssofar['run_time'] = $stoptime;
                }
                update_option( 'wf_sn_results', $resultssofar, false );
            }
            // her stopper det sjove?
            do_action( 'security_ninja_done_testing', __( 'Security Tests - Completed Scanning', 'security-ninja' ), $resultssofar['run_time'] );
            if ( $return_data ) {
                $resultssofar = get_option( 'wf_sn_results' );
                return $resultssofar;
            } else {
                wp_send_json_success( $json_response );
            }
        }

        /**
         * convert status integer to button
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, December 7th, 2021.
         * @access  public static
         * @param   mixed $int
         * @return  mixed
         */
        public static function status( $statuscode ) {
            if ( 0 === $statuscode ) {
                $string = '<span class="sn-error">' . __( 'Fail', 'security-ninja' ) . '</span>';
            } elseif ( 10 === $statuscode ) {
                $string = '<span class="sn-success">' . __( 'OK', 'security-ninja' ) . '</span>';
            } else {
                $string = '<span class="sn-warning">' . __( 'Warning', 'security-ninja' ) . '</span>';
            }
            return $string;
        }

        /**
         * reset pointers on activation and save some info
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, December 7th, 2021.
         * @access  public static
         * @return  void
         */
        public static function activate() {
            $options = self::get_options();
            // Runs on first activation
            if ( empty( $options['first_version'] ) || empty( $options['first_install'] ) ) {
                // Set first install and initial version installed
                $options['first_version'] = self::get_plugin_version();
                $options['first_install'] = time();
                update_option( 'wf_sn_options', $options, false );
                // First activation - set value to redirect later.
                // Don't do redirects when multiple plugins are bulk activated.
                if ( !(isset( $_REQUEST['action'] ) && 'activate-selected' === $_REQUEST['action']) && !(isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) && count( $_POST['checked'] ) > 1) ) {
                    $user_id = get_current_user_id();
                    if ( $user_id ) {
                        add_option( 'secnin_activation_redirect', $user_id );
                    }
                }
            }
            global $wpdb;
            // Maybe create table
            $table_name = $wpdb->prefix . 'wf_sn_tests';
            include_once ABSPATH . 'wp-admin/includes/upgrade.php';
            global $wpdb;
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table_name} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\ntestid varchar(30) NOT NULL,\ntimestamp datetime NOT NULL,\ntitle text,\nstatus tinyint(4) NOT NULL,\nscore tinyint(4) NOT NULL,\nruntime float DEFAULT NULL,\nmsg text,\ndetails text,\nPRIMARY KEY  (testid),\nKEY id (id)\n) {$charset};";
            dbDelta( $sql );
        }

        /**
         * clean-up when deactivated
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function deactivate() {
            $centraloptions = self::get_options();
            if ( !isset( $centraloptions['remove_settings_deactivate'] ) ) {
                return;
            }
            if ( $centraloptions['remove_settings_deactivate'] ) {
                // Nothing yet @todo - implement remove functionality here
            }
        }

        /**
         * clean-up when uninstalled
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Saturday, March 5th, 2022.
         * @access  public static
         * @return  void
         */
        public static function uninstall() {
            global $wpdb;
            // Security tests table
            $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'wf_sn_tests' ) );
            delete_option( 'wf_sn_results' );
            delete_option( 'wf_sn_options' );
            delete_option( 'wfsn_freemius_state' );
            delete_option( 'wf_sn_active_plugins' );
            delete_option( 'wf_sn_review_notice' );
            delete_option( 'wf_sn_tests' );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", 'sn_last_login' ) );
        }

    }

}
register_activation_hook( __FILE__, array(__NAMESPACE__ . '\\WF_SN', 'activate') );
register_deactivation_hook( __FILE__, array(__NAMESPACE__ . '\\WF_SN', 'deactivate') );
register_uninstall_hook( __FILE__, array(__NAMESPACE__ . '\\WF_SN', 'uninstall') );
add_action( 'init', array(__NAMESPACE__ . '\\WF_SN', 'init') );
add_action( 'plugins_loaded', array(__NAMESPACE__ . '\\WF_SN', 'plugins_loaded') );
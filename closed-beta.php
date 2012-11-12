<?php
/*
Plugin Name: Closed Beta
Plugin URI: http://codecanyon.net/item/closed-beta-wordpress-plugin/3395536?ref=gilbitron
Description: A plugin for controlling access to a "closed beta" site.
Version: 1.1
Author: Dev7studios
Author URI: http://dev7studios.com
*/

class Dev7ClosedBeta {

    private $plugin_path;
    private $plugin_url;
    private $l10n;
    private $wpsf;
    private $settings;

    function __construct() 
    {	
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugin_dir_url( __FILE__ );
        $this->l10n = 'dev7-closed-beta';
        register_activation_hook( __FILE__, array(&$this, 'activate') );
        load_plugin_textdomain( $this->l10n, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
        
        require_once( $this->plugin_path .'wp-settings-framework.php' );
        $this->wpsf = new WordPressSettingsFramework( $this->plugin_path .'settings/dev7cb-settings.php' );
        add_filter( $this->wpsf->get_option_group() .'_settings_validate', array(&$this, 'validate_settings') );
        $this->settings = wpsf_get_settings( $this->plugin_path .'settings/dev7cb-settings.php' );
        
        add_action( 'admin_init', array(&$this, 'admin_init') );
        add_action( 'admin_menu', array(&$this, 'admin_menu'), 99 );
        add_action( 'delete_user', array(&$this, 'delete_user') );
        if( isset($this->settings['dev7cbsettings_general_enabled']) && $this->settings['dev7cbsettings_general_enabled'] ) {
            add_action( 'user_register', array(&$this, 'user_register') );
            add_action( 'lostpassword_post', array(&$this, 'lost_password') );
            add_action( 'register_post', array( $this, 'create_user'), 10, 3 );
            add_action( 'plugins_loaded', array( $this, 'display_splash') );
            add_filter( 'login_message', array(&$this, 'login_message') );
            add_filter( 'registration_errors', array(&$this, 'registration_errors'), 10, 1 );
            add_filter( 'wp_authenticate_user', array(&$this, 'authenticate_user'), 10, 2 );
            
            // Override Registration settings
            update_option( 'users_can_register', true );
            add_action( "admin_print_scripts-options-general.php", array(&$this, 'admin_print_scripts_options_general') );
        }
        
        require_once( $this->plugin_path .'wp-updates-plugin.php' );
        new WPUpdatesPluginUpdater( 'http://wp-updates.com/api/1/plugin', 55, plugin_basename(__FILE__) );
    }
    
    function activate( $network_wide ) 
    {
        global $wp_version;
		$min_wp_version = '3.1';
		
		$exit_msg = sprintf( __('Closed Beta requires WordPress %s or newer.', $this->l10n), $min_wp_version );
		if( version_compare( $wp_version, $min_wp_version, '<=' ) ) exit($exit_msg);
    }
    
    function admin_print_scripts_options_general()
    {
        wp_enqueue_script( 'dev7cb' );
    }
    
    function admin_init()
    {
        if( isset($_GET['page']) && $_GET['page'] == 'closed-beta' ){
            if( isset($_GET['user']) && isset($_GET['status_action']) ){
                $this->update_user($_GET['user'], $_GET['status_action']);
            }
            if( !isset($this->settings['dev7cbsettings_general_enabled']) || !$this->settings['dev7cbsettings_general_enabled'] ) {
                add_action('admin_notices', array(&$this, 'admin_notice_disabled'));
            }
        }
        
        wp_register_style( 'dev7cb', $this->plugin_url .'closed-beta.css', array(), '1.0' );
        wp_register_script( 'dev7cb', $this->plugin_url .'closed-beta.js', array('jquery'), '1.0' );
    }
    
    function admin_menu()
    {
        // Pending count
        if( ($pending_users = get_transient( 'dev7cb_pending_users' )) === false ){
            $users = get_users( 'blog_id=1' );
    		$pending_users = 0;
    		foreach( $users as $user ){
    			$the_status = get_user_meta( $user->ID, 'dev7cb_user_status', true );
    			if( $the_status == 'pending' ) $pending_users++;
    		}
    		set_transient( 'dev7cb_pending_users', $pending_users, 60*60*12 );
		}
		$count = '';
		if( $pending_users > 0 ){
    		$count = ' <span class="update-plugins count-'. $pending_users .'" title="'. $pending_users .' '. __( 'Users Pending Approval', $this->l10n ) .'"><span class="update-count">'. $pending_users .'</span></span>';
		}
		
		$capability = 'manage_options';
		if( isset($this->settings['dev7cbsettings_advanced_access-settings']) ) $capability = $this->settings['dev7cbsettings_advanced_access-settings'];
            			
        $approval_hook = add_menu_page( __( 'Closed Beta', $this->l10n ), __( 'Closed Beta', $this->l10n ) . $count, $capability, 'closed-beta', array(&$this, 'approve_users'), $this->plugin_url .'images/favicon.png' );
        add_submenu_page( 'closed-beta', __( 'Closed Beta User Approval', $this->l10n ), __( 'User Approval', $this->l10n ) . $count, $capability, 'closed-beta', array(&$this, 'approve_users') );
        $settings_hook = add_submenu_page( 'closed-beta', __( 'Closed Beta Settings', $this->l10n ), __( 'Settings', $this->l10n ), $capability, 'closed-beta-settings', array(&$this, 'settings_page') );
        
        add_action( 'admin_print_styles-'. $approval_hook, array(&$this, 'admin_print_styles') );
        add_action( 'admin_print_styles-'. $settings_hook, array(&$this, 'admin_print_styles') );
    }
    
    function admin_print_styles()
    {
        wp_enqueue_style( 'dev7cb' );
    }
    
    function delete_user()
    {
        // Delete pending count cache
		delete_transient( 'dev7cb_pending_users' );
    }
    
    function settings_page()
	{
	    global $wpsf_settings;
	    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; 
	    ?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2><?php _e('Closed Beta Settings', $this->l10n) ?></h2>
			<h2 class="nav-tab-wrapper">
			    <?php foreach( $wpsf_settings as $tab ){ ?>
        		<a href="?page=<?php echo $_GET['page']; ?>&tab=<?php echo $tab['section_id']; ?>" class="nav-tab<?php echo $active_tab == $tab['section_id'] ? ' nav-tab-active' : ''; ?>"><?php echo $tab['section_title']; ?></a>
        		<?php } ?>
        	</h2>
			<form action="options.php" method="post">
                <?php settings_fields( $this->wpsf->get_option_group() ); ?>
        		<?php $this->do_settings_sections( $this->wpsf->get_option_group() ); ?>
        		<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', $this->l10n ); ?>" /></p>
			</form>
		</div>
		<?php
		//echo '<pre>'.print_r($wpsf_settings,true).'</pre>';
	}
	
	function do_settings_sections($page) {
        global $wp_settings_sections, $wp_settings_fields;
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general'; 

        if ( !isset($wp_settings_sections) || !isset($wp_settings_sections[$page]) )
            return;

        foreach ( (array) $wp_settings_sections[$page] as $section ) {
            echo '<div id="section-'. $section['id'] .'"class="dev7cb-section'. ($active_tab == $section['id'] ? ' dev7cb-section-active' : '') .'">';
            /*if ( $section['title'] )
                    echo "<h3>{$section['title']}</h3>\n";*/
            call_user_func($section['callback'], $section);
            if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']]) )
                    continue;
            echo '<table class="form-table">';
            do_settings_fields($page, $section['id']);
            echo '</table>
            </div>';
        }
    }
	
	function validate_settings( $input )
	{
    	return $input;
	}
	
	function user_register( $user_id ) 
	{
		$status = 'pending';
		if( isset($_REQUEST['action']) && $_REQUEST['action'] == 'createuser' ) $status = 'approved';
		update_user_meta( $user_id, 'dev7cb_user_status', $status );
	}
	
	function lost_password() 
	{
		$is_email = strpos($_POST['user_login'], '@');
		
		if( $is_email === false ){
			$username = sanitize_user($_POST['user_login']);
			$user_data = get_user_by( 'login', trim($username) );
		} else {
			$email = is_email($_POST['user_login']);
			$user_data = get_user_by( 'email', $email );
		}

		if($user_data->dev7cb_user_status != 'approved'){
			wp_redirect('wp-login.php');
			exit();
		}

		return;
	}
	
	function create_user( $user_login, $user_email, $errors ) 
	{
		if( !$errors->get_error_code() ){
			$user_data = get_user_by( 'login', $user_login );
			if( !empty($user_data) ){
				$errors->add('registration_required' , __('Username already exists', $this->l10n), 'message');
			} else {
				$message  = sprintf(__('%1$s (%2$s) has requested beta access at %3$s', $this->l10n), $user_login, $user_email, get_option('blogname')) . "\r\n\r\n";
				$message .= home_url() . "\r\n\r\n";
				$message .= sprintf(__('To approve or deny this user access to %s go to', $this->l10n), get_option('blogname')) . "\r\n\r\n";
				$message .= admin_url('users.php?page=closed-beta') . "\r\n";
				$message = apply_filters( 'dev7cb_admin_approve_email', $message );
				wp_mail( get_option('admin_email'), sprintf(__('[%s] User Approval', $this->l10n), get_option('blogname')), $message );
				do_action( 'dev7cb_admin_approve_email', $message );

				$user_pass = wp_generate_password();
				$user_id = wp_create_user($user_login, $user_pass, $user_email);
				do_action( 'dev7cb_create_user', $user_id );
				
				// Delete pending count cache
				delete_transient( 'dev7cb_pending_users' );
			}
		}
	}
	
	function login_message( $message ) 
	{
		if( !isset($_GET['action']) ){
			$inside = sprintf( __('Welcome to %s. This site is accessible to approved beta users only. To apply for beta access you must first register.', $this->l10n), get_option('blogname') );
			$inside = apply_filters( 'dev7cb_login_notice', $inside );
			$message .= '<p class="message">' . $inside . '</p>';
		}

		if( isset($_GET['action']) && $_GET['action'] == 'register' && !$_POST ){
			$inside = sprintf( __('After you register, your request will be sent to the site administrator for approval. Once approved you will then receive an email with further instructions.', $this->l10n) );
			$inside = apply_filters( 'dev7cb_login_register_notice', $inside );
			$message .= '<p class="message">' . $inside . '</p>';
		}

		return $message;
	}
	
	function registration_errors($errors) 
	{
		if ( $errors->get_error_code() ) return $errors;

		$message  = sprintf( __('An email has been sent to the site administrator who will review your beta account request. ', $this->l10n) );
		$message .= sprintf( __('Once approved you will receive an email with instructions on what you will need to do next.', $this->l10n) );
		$message .= '<br /><br /><a href="'. home_url() .'">&larr; '. sprintf( __('Back to %s', $this->l10n), get_option('blogname') ) .'</a>';
		$message = apply_filters( 'dev7cb_registered_notice', $message );
		$errors->add( 'registration_required', $message, 'message' );

		if( function_exists('login_header') ){
			login_header( __('Pending Approval', $this->l10n), '<p class="message register">'. __("Registration successful.", $this->l10n) . '</p>', $errors );
		}

		echo "<body></html>";
		exit();
	}
	
    function authenticate_user( $userdata, $password ) 
	{
		$status = get_user_meta( $userdata->ID, 'dev7cb_user_status', true );

		// the user does not have a status so let's assume the user is good to go
		if( empty($status) ) return $userdata;

		switch( $status ){
			case 'pending':
			    do_action( 'dev7cb_login_denied_pending' );
				$userdata = new WP_Error('pending_approval', __('<strong>ERROR</strong>: Your account is still pending approval.'));
				break;
			case 'denied':
			    do_action( 'dev7cb_login_denied' );
				$userdata = new WP_Error('denied_access', __('<strong>ERROR</strong>: Your account has been denied access to this site.'));
				break;
		}

		return $userdata;
	}
	
	function update_user( $user_id, $status )
	{
    	global $wpdb;
    	
    	if( !is_numeric($user_id) ) return;
    	if( $status != 'approve' && $status != 'deny' ) return;
		$user = new WP_User( $user_id );
		if( !$user->exists() ) return;
		
		// Delete pending count cache
		delete_transient( 'dev7cb_pending_users' );

		if( $status == 'approve' ){
    		$new_pass = wp_generate_password();
    		wp_update_user( array( 'ID' => $user->ID, 'user_pass' => $new_pass ) );
    
    		$user_login = stripslashes($user->user_login);
    		$user_email = stripslashes($user->user_email);
    
    		$message  = sprintf(__('You have been approved to access %s', $this->l10n), get_option('blogname')) . "\r\n\r\n";
    		$message .= sprintf(__('Username: %s', $this->l10n), $user_login) . "\r\n";
    		$message .= sprintf(__('Password: %s', $this->l10n), $new_pass) . "\r\n\r\n";
    		$message .= home_url('wp-login.php') ."\r\n";
    		$message = apply_filters( 'dev7cb_approved_email', $message );
    		@wp_mail( $user_email, sprintf(__('[%s] Beta Registration Approved', $this->l10n), get_option('blogname')), $message );
    		do_action( 'dev7cb_approve_email', $message );
    
    		update_user_meta($user->ID, 'dev7cb_user_status', 'approved');
    		add_action('admin_notices', array(&$this, 'admin_notice_approved'));
		}
		
		if( $status == 'deny' ){
    		$user_email = stripslashes($user->user_email);
    
    		$message = sprintf(__('You have been denied beta access to %s', $this->l10n), get_option('blogname'));
    		$message = apply_filters( 'dev7cb_denied_email', $message );
    		@wp_mail( $user_email, sprintf(__('[%s] Beta Registration Denied', $this->l10n), get_option('blogname')), $message );
    		do_action( 'dev7cb_deny_email', $message );
    
    		update_user_meta($user->ID, 'dev7cb_user_status', 'denied');
    		add_action('admin_notices', array(&$this, 'admin_notice_denied'));
		}
	}
	
    function admin_notice_approved()
    {
        echo '<div class="updated"><p>'. __('User successfully approved.', $this->l10n) .'</p></div>';
    }
    
    function admin_notice_denied()
    {
        echo '<div class="updated"><p>'. __('User successfully denied.', $this->l10n) .'</p></div>';
    }
    
    function admin_notice_disabled()
    {
        echo '<div class="error"><p>'. __('Closed Beta is currently disabled. Anyone can register and will be approved automatically.', $this->l10n) .'</p></div>';
    }
	
	function approve_users()
    {
        global $current_user;
        $status = isset($_GET['status']) ? $_GET['status'] : 'pending';
		?>
		<div class="wrap">
			<div id="icon-users" class="icon32"></div>
			<h2><?php _e('User Approval', $this->l10n) ?></h2>
			<ul class="subsubsub">
            	<li><a href="<?php echo admin_url('admin.php?page=closed-beta'); ?>"<?php if($status == 'pending') echo ' class="current"'; ?>><?php _e('Users Pending Approval', $this->l10n); ?></a> |</li>
				<li><a href="<?php echo admin_url('admin.php?page=closed-beta&status=approved'); ?>"<?php if($status == 'approved') echo ' class="current"'; ?>><?php _e('Approved Users', $this->l10n); ?></a> |</li>
				<li><a href="<?php echo admin_url('admin.php?page=closed-beta&status=denied'); ?>"<?php if($status == 'denied') echo ' class="current"'; ?>><?php _e('Denied Users', $this->l10n); ?></a></li>
            </ul>
            <div style="clear:both"></div>
            
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <thead>
            		<tr>
            			<th><?php _e('Username', $this->l10n); ?></th>
            			<th><?php _e('Name', $this->l10n); ?></th>
            			<th><?php _e('E-mail', $this->l10n); ?></th>
            			<th><?php _e('Actions', $this->l10n); ?></th>
            		</tr>
            	</thead>
            	<tfoot>
            		<tr>
            			<th><?php _e('Username', $this->l10n); ?></th>
            			<th><?php _e('Name', $this->l10n); ?></th>
            			<th><?php _e('E-mail', $this->l10n); ?></th>
            			<th><?php _e('Actions', $this->l10n); ?></th>
            		</tr>
            	</tfoot>
            	<tbody>
                    <?php
        			if ( $status != 'approved' ) {
            			$query = array(
            				'meta_key' => 'dev7cb_user_status',
            				'meta_value' => $status,
            			);
            			$wp_user_search = new WP_User_Query( $query );
            		} else {
            			$users = get_users( 'blog_id=1' );
            			$approved_users = array();
            			foreach( $users as $user ){
            				$the_status = get_user_meta( $user->ID, 'dev7cb_user_status', true );
            				if( $the_status == 'approved' || empty( $the_status ) ){
            					$approved_users[] = $user->ID;
            				}
            			}
            			$query = array( 'include' => $approved_users );
            			$wp_user_search = new WP_User_Query( $query );
            		}

            		if( isset($wp_user_search) && $wp_user_search->total_users > 0 ){
                		$row = 1;
                		foreach( $wp_user_search->get_results() as $user ){
                		    if( user_can( $user->ID, 'manage_options' ) ) continue; // Hide admins
                			$class = ($row % 2) ? '' : ' class="alternate"';
                			$avatar = get_avatar( $user->user_email, 32 );
                			if( $status == 'pending' || $status == 'denied' ){
                				$approve_link = admin_url( 'admin.php?page=closed-beta&status='. $status .'&user='. $user->ID .'&status_action=approve' );
                				$approve_link = wp_nonce_url( $approve_link, 'dev7cb_user_action_' . get_class($this) );
                			}
                			if( $status == 'pending' || $status == 'approved' ){
                				$deny_link = admin_url( 'admin.php?page=closed-beta&status='. $status .'&user='. $user->ID .'&status_action=deny' );
                				$deny_link = wp_nonce_url( $deny_link, 'dev7cb_user_action_' . get_class($this) );
                			}
                			
                			if( current_user_can( 'edit_user', $user->ID ) ){
                				if( $current_user->ID == $user->ID ){
                					$edit_link = 'profile.php';
                				} else {
                					$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( esc_url( stripslashes( $_SERVER['REQUEST_URI'] ) ) ), 'user-edit.php?user_id='. $user->ID ) );
                				}
                				$edit = '<strong><a href="'. $edit_link .'">'. $user->user_login .'</a></strong><br />
                				         <div class="row-actions"><span class="edit"><a href="'. $edit_link .'">'. __('Edit', $this->l10n) .'</a></span></div>';
                			} else {
                				$edit = '<strong>'. $user->user_login .'</strong>';
                			}
                
                			?><tr <?php echo $class; ?>>
                				<td class="username column-username"><?php echo $avatar .' '. $edit; ?></td>
                				<td class="name column-name"><?php echo get_user_meta( $user->ID, 'first_name', true ) .' '. get_user_meta( $user->ID, 'last_name', true ); ?></td>
                				<td class="email column-email"><a href="mailto:<?php echo $user->user_email; ?>" title="<?php _e('E-mail:', $this->l10n); ?> <?php echo $user->user_email; ?>"><?php echo $user->user_email; ?></a></td>
                				<td>
                    				<?php if( isset($approve_link) ){ ?><a href="<?php echo $approve_link; ?>" title="<?php _e('Approve', $this->l10n); ?> <?php echo $user->user_login; ?>" class="dev7cb-approve"><?php _e('Approve', $this->l10n); ?></a><?php } ?>
                    				<?php if( isset($deny_link) ){ ?><a href="<?php echo $deny_link; ?>" title="<?php _e('Deny', $this->l10n); ?> <?php echo $user->user_login; ?>" class="dev7cb-deny"><?php _e('Deny', $this->l10n); ?></a><?php } ?>
                				</td>
                			</tr><?php
                			$row++;
                		}
            		} else {
            			$status_string = __('There are no users pending approval.', $this->l10n);
            			if( $status == 'approved' ){
            				$status_string = __('There are no approved users.', $this->l10n);
            			} else if ($status == 'denied') {
            				$status_string = __('There are no denied users.', $this->l10n);
            			}
            
            			echo '<tr><td colspan="4">'. $status_string .'</td></tr>';
            		}
            		?>
        		</tbody>
            </table>
		</div>
		<?php
	}
	
	function display_splash() 
	{
	    if( !strstr($_SERVER['REQUEST_URI'], 'closed-beta-preview') ){
    		if( strstr($_SERVER['PHP_SELF'], 'wp-login.php') 
    		    || strstr($_SERVER['PHP_SELF'], 'wp-signup.php') 
    			|| strstr($_SERVER['PHP_SELF'], 'async-upload.php')
    			|| strstr(htmlspecialchars($_SERVER['REQUEST_URI']), '/plugins/')
    			|| strstr($_SERVER['PHP_SELF'], 'upgrade.php')
    			|| is_user_logged_in()
    		) return;
        }
    
		if( !isset($this->settings['dev7cbsettings_advanced_enablefeeds']) || !$this->settings['dev7cbsettings_advanced_enablefeeds'] ){
    		if( strstr(htmlspecialchars($_SERVER['REQUEST_URI']), '/feed/') || strstr(htmlspecialchars($_SERVER['REQUEST_URI']), 'feed=') ){
    		    do_action( 'dev7cb_block_feed' );
    			nocache_headers();
    			$this->http_header_unavailable(); 
    			exit;
    		}
		}

		if( !isset($this->settings['dev7cbsettings_advanced_enabletrackbacks']) || !$this->settings['dev7cbsettings_advanced_enabletrackbacks'] ){
    		if( strstr(htmlspecialchars($_SERVER['REQUEST_URI']), '/trackback/') || strstr($_SERVER['PHP_SELF'], 'wp-trackback.php') ){
    		    do_action( 'dev7cb_block_trackback' );
    			nocache_headers();
    			$this->http_header_unavailable(); 
    			exit;
    		}
		}

		if( !isset($this->settings['dev7cbsettings_advanced_enablexmlrpc']) || !$this->settings['dev7cbsettings_advanced_enablexmlrpc'] ){
    		if( strstr($_SERVER['PHP_SELF'], 'xmlrpc.php') ){
    		    do_action( 'dev7cb_block_xmlrpc' );
                $this->http_header_unavailable(); 
                exit;
    		}
		}

		if( is_admin() || strstr(htmlspecialchars($_SERVER['REQUEST_URI']), '/wp-admin/') ){
			if( !is_user_logged_in() ) auth_redirect();
			return;
		}
		
		// Display splash
		$page_title = __( 'Closed Beta' );
		if( isset($this->settings['dev7cbsettings_general_page-title']) && $this->settings['dev7cbsettings_general_page-title'] ) $page_title = $this->settings['dev7cbsettings_general_page-title'];
		$tagline = '';
		if( isset($this->settings['dev7cbsettings_general_tagline']) ) $tagline = $this->settings['dev7cbsettings_general_tagline'];
		$page_content = __( 'This site can only be accessed by approved users.' );
		if( isset($this->settings['dev7cbsettings_general_page-content']) ) $page_content = wpautop($this->settings['dev7cbsettings_general_page-content']);
		$username_label = __( 'Enter a username' );
		if( isset($this->settings['dev7cbsettings_general_username-label']) && $this->settings['dev7cbsettings_general_username-label'] ) $username_label = $this->settings['dev7cbsettings_general_username-label'];
		$email_label = __( 'Enter your email address' );
		if( isset($this->settings['dev7cbsettings_general_email-label']) && $this->settings['dev7cbsettings_general_email-label'] ) $email_label = $this->settings['dev7cbsettings_general_email-label'];
		$signup_text = __( 'Sign Up' );
		if( isset($this->settings['dev7cbsettings_general_signup-text']) && $this->settings['dev7cbsettings_general_signup-text'] ) $signup_text = $this->settings['dev7cbsettings_general_signup-text'];
		$login_text = __( 'Login' );
		if( isset($this->settings['dev7cbsettings_general_login-text']) && $this->settings['dev7cbsettings_general_login-text'] ) $login_text = $this->settings['dev7cbsettings_general_login-text'];
		$overlay_class = 'overlay-black';
		if( isset($this->settings['dev7cbsettings_style_overlay']) ) $overlay_class = 'overlay-'. $this->settings['dev7cbsettings_style_overlay'];
		$style = '<style types="text/css">' . "\n";
		$style .= 'body { ';
		if( isset($this->settings['dev7cbsettings_style_background-color']) && $this->settings['dev7cbsettings_style_background-color'] != '' && $this->settings['dev7cbsettings_style_background-color'] != '#' ) $style .= 'background-color: '. $this->settings['dev7cbsettings_style_background-color'] .'; ';
		if( isset($this->settings['dev7cbsettings_style_background-image']) && $this->settings['dev7cbsettings_style_background-image'] ){
    		$style .= 'background-image: url('. $this->settings['dev7cbsettings_style_background-image'] .'); ';
    		$background_position = '50% 0%';
    		$background_repeat = 'no-repeat';
    		if( isset($this->settings['dev7cbsettings_style_background-position']) && $this->settings['dev7cbsettings_style_background-position'] == 'left' ) $background_position = '0% 0%';
    		if( isset($this->settings['dev7cbsettings_style_background-position']) && $this->settings['dev7cbsettings_style_background-position'] == 'right' ) $background_position = '100% 0%';
    		if( isset($this->settings['dev7cbsettings_style_background-style']) && $this->settings['dev7cbsettings_style_background-style'] == 'full_stretched' ) $background_position = '50% 50%';
    		if( isset($this->settings['dev7cbsettings_style_background-style']) && $this->settings['dev7cbsettings_style_background-style'] == 'tiled' ){
    		    $background_position = '0% 0%';
    		    $background_repeat = 'repeat';
    		}
    		$style .= 'background-position: '. $background_position .'; ';
    		$style .= 'background-repeat: '. $background_repeat .'; ';
    		if( isset($this->settings['dev7cbsettings_style_background-style']) && $this->settings['dev7cbsettings_style_background-style'] == 'full_stretched' ){
        		$style .= 'background-attachment:fixed; -webkit-background-size: cover; -moz-background-size: cover; -o-background-size: cover; background-size: cover; ';
    		}
		}
		$style .= '} ' . "\n";
		$style .= 'body, #cb-content { ';
		if( isset($this->settings['dev7cbsettings_style_text-color']) && $this->settings['dev7cbsettings_style_text-color'] != '' && $this->settings['dev7cbsettings_style_text-color'] != '#' ) $style .= 'color: '. $this->settings['dev7cbsettings_style_text-color'] .' !important; ';
		$style .= '} ' . "\n";
		if( isset($this->settings['dev7cbsettings_style_link-color']) && $this->settings['dev7cbsettings_style_link-color'] != '' && $this->settings['dev7cbsettings_style_link-color'] != '#' ) $style .= 'a { color: '. $this->settings['dev7cbsettings_style_link-color'] .' !important; }' . "\n";
		if( isset($this->settings['dev7cbsettings_style_custom-css']) && $this->settings['dev7cbsettings_style_custom-css'] ) $style .= $this->settings['dev7cbsettings_style_custom-css'] . "\n";
		$style .= '</style>' . "\n";
		
		do_action( 'dev7cb_before_template' );
		if( file_exists(get_template_directory() .'/closed-beta-template.php') ){
    		include_once( get_template_directory() .'/closed-beta-template.php' );
		} else {
		    if( file_exists($this->plugin_path .'template/closed-beta-template.php') ){
    		    include_once( $this->plugin_path .'template/closed-beta-template.php' );
    		} else {
        		_e('Missing template file.', $this->l10n);
    		}
		}
		do_action( 'dev7cb_after_template' );
		exit;
	}

}
new Dev7ClosedBeta();

?>
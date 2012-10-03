<?php
/*
Plugin Name: Closed Beta
Plugin URI: http://dev7studios.com
Description: A plugin for controlling access to a "closed beta" site.
Version: 1.0
Author: Dev7studios
Author URI: http://dev7studios.com
*/

class Dev7ClosedBeta {

    private $plugin_path;
    private $plugin_url;
    private $l10n;
    private $wpsf;

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
        
        add_action( 'admin_init', array(&$this, 'admin_init') );
        add_action( 'admin_menu', array(&$this, 'admin_menu'), 99 );
        add_action( 'user_register', array(&$this, 'user_register') );
        add_action( 'lostpassword_post', array(&$this, 'lost_password') );
        add_action( 'register_post', array( $this, 'send_approval_email'), 10, 3 );
        add_filter( 'login_message', array(&$this, 'login_message') );
        add_filter( 'registration_errors', array(&$this, 'registration_errors'), 10, 1 );
        add_filter( 'wp_authenticate_user', array(&$this, 'authenticate_user'), 10, 2 );
    }
    
    function activate( $network_wide ) 
    {
        global $wp_version;
		$min_wp_version = '3.1';
		
		$exit_msg = sprintf( __('Closed Beta requires WordPress %s or newer.', $this->l10n), $min_wp_version );
		if( version_compare( $wp_version, $min_wp_version, '<=' ) ) exit($exit_msg);
    }
    
    function admin_init()
    {
        if(isset($_GET['page']) && $_GET['page'] == 'closed-beta' && isset($_GET['user']) && isset($_GET['status_action'])){
            $this->update_user($_GET['user'], $_GET['status_action']);
        }
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
            			
        $page_hook = add_menu_page( __( 'Closed Beta', $this->l10n ), __( 'Closed Beta', $this->l10n ) . $count, 'update_core', 'closed-beta', array(&$this, 'approve_users') );
        add_submenu_page( 'closed-beta', __( 'User Approval', $this->l10n ), __( 'User Approval', $this->l10n ) . $count, 'update_core', 'closed-beta', array(&$this, 'approve_users') );
        add_submenu_page( 'closed-beta', __( 'Settings', $this->l10n ), __( 'Settings', $this->l10n ), 'update_core', 'closed-beta-settings', array(&$this, 'settings_page') );
    }
    
    function settings_page()
	{
	    ?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"></div>
			<h2><?php _e('Closed Beta', $this->l10n) ?></h2>
			<?php 
			$this->wpsf->settings(); 
			?>
		</div>
		<?php
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
	
	function send_approval_email( $user_login, $user_email, $errors ) 
	{
		if( !$errors->get_error_code() ){
			$user_data = get_user_by( 'login', $user_login );
			if (!empty($user_data)){
				$errors->add('registration_required' , __('User name already exists', $this->l10n), 'message');
			} else {
				$message  = sprintf(__('%1$s (%2$s) has requested a username at %3$s', $this->l10n), $user_login, $user_email, get_option('blogname')) . "\r\n\r\n";
				$message .= home_url() . "\r\n\r\n";
				$message .= sprintf(__('To approve or deny this user access to %s go to', $this->l10n), get_option('blogname')) . "\r\n\r\n";
				$message .= admin_url('users.php?page=closed-beta') . "\r\n";
				wp_mail( get_option('admin_email'), sprintf(__('[%s] User Approval', $this->l10n), get_option('blogname')), $message );

				$user_pass = wp_generate_password();
				$user_id = wp_create_user($user_login, $user_pass, $user_email);
			}
		}
	}
	
	function login_message( $message ) 
	{
		if( !isset($_GET['action']) ){
			$inside = sprintf( __('Welcome to %s. This site is accessible to approved users only. To be approved, you must first register.', $this->l10n), get_option('blogname') );
			$message .= '<p class="message">' . $inside . '</p>';
		}

		if( isset($_GET['action']) && $_GET['action'] == 'register' && !$_POST ){
			$inside = sprintf( __('After you register, your request will be sent to the site administrator for approval. You will then receive an email with further instructions.', $this->l10n) );
			$message .= '<p class="message">' . $inside . '</p>';
		}

		return $message;
	}
	
	function registration_errors($errors) 
	{
		if ( $errors->get_error_code() ) return $errors;

		$message  = sprintf( __('An email has been sent to the site administrator who will review your account request.', $this->l10n) );
		$message .= sprintf( __('You will receive an email with instructions on what you will need to do next. Thanks for your patience.', $this->l10n) );

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
				$userdata = new WP_Error('pending_approval', __('<strong>ERROR</strong>: Your account is still pending approval.'));
				break;
			case 'denied':
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
    		@wp_mail( $user_email, sprintf(__('[%s] Registration Approved', $this->l10n), get_option('blogname')), $message );
    
    		update_user_meta($user->ID, 'dev7cb_user_status', 'approved');
    		add_action('admin_notices', array(&$this, 'admin_notice_approved'));
		}
		
		if( $status == 'deny' ){
    		$user_email = stripslashes($user->user_email);
    
    		$message = sprintf(__('You have been denied access to %s', $this->l10n), get_option('blogname'));
    		@wp_mail( $user_email, sprintf(__('[%s] Registration Denied', $this->l10n), get_option('blogname')), $message );
    
    		update_user_meta($user->ID, 'dev7cb_user_status', 'denied');
    		add_action('admin_notices', array(&$this, 'admin_notice_denied'));
		}
	}
	
    function admin_notice_approved(){
        echo '<div class="updated"><p>'. __('User successfully approved.', $this->l10n) .'</p></div>';
    }
    
    function admin_notice_denied(){
        echo '<div class="updated"><p>'. __('User successfully denied.', $this->l10n) .'</p></div>';
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
                    				<?php if( isset($approve_link) ){ ?><a href="<?php echo $approve_link; ?>" title="<?php _e('Approve', $this->l10n); ?> <?php echo $user->user_login; ?>" style="color:green"><?php _e('Approve', $this->l10n); ?></a>&nbsp;<?php } ?>
                    				<?php if( isset($deny_link) ){ ?><a href="<?php echo $deny_link; ?>" title="<?php _e('Deny', $this->l10n); ?> <?php echo $user->user_login; ?>" style="color:red"><?php _e('Deny', $this->l10n); ?></a><?php } ?>
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

}
new Dev7ClosedBeta();

?>
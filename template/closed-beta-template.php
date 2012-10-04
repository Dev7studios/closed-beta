<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8" />
    
    <title><?php echo $page_title; ?><?php if( $tagline ) echo ' - '. $tagline; ?></title>

    <link rel="stylesheet" href="<?php echo plugins_url( 'style.css' , __FILE__ ); ?>" type="text/css" media="screen" />
    <script src="<?php echo plugins_url( 'scripts/modernizr-2.6.1.min.js' , __FILE__ ); ?>"></script>
    <?php echo $style; ?>
</head>
<body>

    <a href="<?php echo home_url('wp-login.php'); ?>" id="cb-login">Login</a>

    <div id="cb-background"></div>

    <div id="cb-content-wrap">
        <div id="cb-content" class="<?php echo $overlay_class; ?>">
            <h1 class="page-title"><?php echo $page_title; ?></h1>
            <?php if( $tagline ){ ?><h2 class="tagline"><?php echo $tagline; ?></h2><?php } ?>
            <?php if( $page_content ){ ?><div class="page-content"><?php echo $page_content; ?></div><?php } ?>
            <form action="<?php echo home_url('wp-login.php?action=register'); ?>" method="post">
            	<label for="user_login"><?php echo $username_label; ?></label>
                <input type="text" name="user_login" id="user_login" class="user-login" value="" /><br />
                <label for="user_email"><?php echo $email_label; ?></label>
                <input type="email" name="user_email" id="user_email" class="user-email" value="" /><br />
            	<input type="submit" name="wp-submit" id="wp-submit" class="submit" value="<?php echo $button_text; ?>" />
            </form>
        </div>
    </div>

</body>
</html>
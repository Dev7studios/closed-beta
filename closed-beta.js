jQuery(document).ready(function($) {

    $('#users_can_register').attr('disabled', true).parent().append(' (must be enabled while <a href="admin.php?page=closed-beta-settings">Closed Beta</a> is enabled)');

});
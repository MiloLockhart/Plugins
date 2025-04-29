jQuery(document).ready(function ($) {
    $('#adminmenu a').on('click', function () {
        let menuItem = $(this).text().trim();
        $.post(adminTrackerAjax.ajaxurl, {
            action: 'log_admin_navigation',
            menu_item: menuItem
        });
    });
});

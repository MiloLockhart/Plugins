jQuery(document).ready(function($) {
    $('#new-quote-button').on('click', function() {
        $.ajax({
            url: dailyInspiration.ajax_url,
            method: 'POST',
            data: {
                action: 'get_daily_inspiration'
            },
            success: function(response) {
                if (response.success) {
                    var quote = response.data.content;
                    var author = response.data.author;
                    $('#quote-container').html('<p>' + quote + ' - ' + author + '</p>');
                } else {
                    $('#quote-container').html('<p>Something went wrong: ' + response.data + '</p>');
                }
            }
        });
    });
});

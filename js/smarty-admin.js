jQuery(document).ready(function($) {
    $('.smarty-convert-images-button').on('click', function() {
        var nonce = smartyFeedGenerator.nonce;

        $.ajax({
            url: smartyFeedGenerator.ajaxUrl,
            type: 'POST',
            data: {
                action: 'smarty_convert_images',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });

    $('.smarty-generate-feed-button').on('click', function(e) {
        e.preventDefault();

        var action = $(this).data('feed-action');
        var redirectUrl = '';

        switch (action) {
            case 'generate_product_feed':
                redirectUrl = smartyFeedGenerator.siteUrl + '/smarty-google-feed/';
                break;
            case 'generate_reviews_feed':
                redirectUrl = smartyFeedGenerator.siteUrl + '/smarty-google-reviews-feed/';
                break;
            case 'generate_csv_export':
                redirectUrl = smartyFeedGenerator.siteUrl + '/smarty-csv-export/';
                break;
            default:
                alert('Invalid action.');
                return;
        }

        window.open(redirectUrl, '_blank');
    });

    $('select[name="smarty_excluded_categories[]"]').select2({
        width: '100%'
    });
});

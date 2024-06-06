jQuery(document).ready(function($) {
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
});

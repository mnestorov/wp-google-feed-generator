jQuery(document).ready(function($) {
    $('.smarty-convert-images-button').on('click', function (e) {
        e.preventDefault(); // Prevent the default form submission

        var button = $(this);
        button.attr('disabled', true);

        $.ajax({
            url: smartyFeedGenerator.ajaxUrl,
            method: 'POST',
            data: {
                action: 'smarty_convert_images',
                nonce: smartyFeedGenerator.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('AJAX Error: ' + error);
            },
            complete: function () {
                button.attr('disabled', false);
            }
        });
    });

    $('.smarty-generate-feed-button').on('click', function(e) {
        e.preventDefault(); // Prevent the default form submission

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

    $('.smarty-excluded-categories').select2({
        width: '100%' // need to override the changed default
    });

    // Initialize Select2 with AJAX for the Google Product Category select element
    $('.smarty-select2-ajax').select2({
        ajax: {
            url: smartyFeedGenerator.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    action: 'smarty_load_google_categories',
                    nonce: smartyFeedGenerator.nonce
                };
            },
            processResults: function (data) {
                return {
                    results: data.data
                };
            },
            cache: true
        },
        minimumInputLength: 1,
        width: 'resolve'
    });
});

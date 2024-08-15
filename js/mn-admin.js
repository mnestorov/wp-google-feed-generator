jQuery(document).ready(function($) {
    $('.mn-convert-images-button').on('click', function (e) {
        e.preventDefault(); // Prevent the default form submission

        var button = $(this);
        button.attr('disabled', true);

        $.ajax({
            url: mnFeedGenerator.ajaxUrl,
            method: 'POST',
            data: {
                action: 'mn_convert_images',
                nonce: mnFeedGenerator.nonce
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

    $('.mn-generate-feed-button').on('click', function(e) {
        e.preventDefault(); // Prevent the default form submission

        var action = $(this).data('feed-action');
        var redirectUrl = '';

        switch (action) {
            case 'generate_product_feed':
                redirectUrl = mnFeedGenerator.siteUrl + '/mn-google-feed/';
                break;
            case 'generate_reviews_feed':
                redirectUrl = mnFeedGenerator.siteUrl + '/mn-google-reviews-feed/';
                break;
            case 'generate_csv_export':
                redirectUrl = mnFeedGenerator.siteUrl + '/mn-csv-export/';
                break;
            default:
                alert('Invalid action.');
                return;
        }

        window.open(redirectUrl, '_blank');
    });

    $('.mn-excluded-categories').select2({
        width: '100%' // need to override the changed default
    });

    // Initialize Select2 with AJAX for the Google Product Category select element
    $('.mn-select2-ajax').select2({
        ajax: {
            url: mnFeedGenerator.ajaxUrl,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    action: 'mn_load_google_categories',
                    nonce: mnFeedGenerator.nonce
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

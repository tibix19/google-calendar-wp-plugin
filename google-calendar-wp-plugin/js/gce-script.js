jQuery(document).ready(function($) {
    function updateEvents() {
        var filterAthletisme = $('#gce-filter-athletisme').is(':checked');
        var filterAgres = $('#gce-filter-agres').is(':checked');
        var sortOrder = $('#gce-show-past-events').is(':visible') ? 'future' : 'past';
        $.ajax({
            url: gce_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gce_filter_events',
                filter_athletisme: filterAthletisme,
                filter_agres: filterAgres,
                sort_order: sortOrder
            },
            success: function(response) {
                $('#gce-events-list').html(response);
            }
        });
    }

    $('#gce-apply-filters').on('click', updateEvents);
    $('.gce-filter').on('change', updateEvents); // Ajout de cette ligne pour mettre à jour en temps réel

    $('#gce-show-past-events').on('click', function() {
        $(this).hide();
        $('#gce-show-future-events').show();
        updateEvents();
    });

    $('#gce-show-future-events').on('click', function() {
        $(this).hide();
        $('#gce-show-past-events').show();
        updateEvents();
    });
});
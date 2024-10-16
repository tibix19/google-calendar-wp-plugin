<?php
// Shortcode pour afficher les événements sur le frontend
function gce_display_frontend_events() {
    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');
    if (!$api_key || !$calendar_id) {
        return '<p>Veuillez configurer la clé API et l\'ID du calendrier dans l\'administration.</p>';
    }
    ob_start();
    ?>
    <div class="gce-events-container">
        <div class="gce-filters">
            <label><input type="checkbox" id="gce-filter-athletisme" class="gce-filter" data-filter="athletisme" checked> Athlétisme</label>
            <label><input type="checkbox" id="gce-filter-agres" class="gce-filter" data-filter="agres" checked> Agrès</label>
            <button id="gce-apply-filters">Appliquer les filtres</button>
        </div>
        <div class="gce-toggle-events">
            <button id="gce-show-past-events">Voir les événements passés</button>
            <button id="gce-show-future-events" style="display:none;">Voir les événements futurs</button>
        </div>
        <div id="gce-events-list">
            <?php gce_display_events($api_key, $calendar_id, 'future', true, true, false); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gce_frontend_events', 'gce_display_frontend_events');

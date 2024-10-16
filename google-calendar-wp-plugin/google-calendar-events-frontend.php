<?php
// Shortcode pour afficher les événements sur le frontend
function gce_display_frontend_events()
{
    // Get API key et l'ID du calendrier depuis les champs dans WordPress
    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');

    // Vérifier si la clé API et l'ID du calendrier sont configurés
    if (!$api_key || !$calendar_id) {
        return '<p>Veuillez configurer la clé API et l\'ID du calendrier dans l\'administration.</p>'; // Message d'erreur si non configuré
    }

    // Commencer la mise en mémoire tampon pour capturer le HTML
    ob_start();
?>
    <div class="gce-events-container">
        <div class="gce-filters">
            <!-- Filtres pour afficher ou masquer des types d'événements -->
            <label><input type="checkbox" id="gce-filter-athletisme" class="gce-filter" data-filter="athletisme" checked> Athlétisme</label>
            <label><input type="checkbox" id="gce-filter-agres" class="gce-filter" data-filter="agres" checked> Agrès</label>
            <!-- Bouton pour appliquer les filtres -->
            <button id="gce-apply-filters">Appliquer les filtres</button>
        </div>
        <div class="gce-toggle-events">
            <!-- Boutons pour afficher les événements passés ou futurs -->
            <button id="gce-show-past-events">Voir les événements passés</button>
            <button id="gce-show-future-events" style="display:none;">Voir les événements futurs</button>
        </div>
        <div id="gce-events-list">
            <!-- Afficher les événements futurs en appelant la fonction gce_display_events -->
            <?php gce_display_events($api_key, $calendar_id, 'future', true, true, false); ?>
        </div>
    </div>
<?php
    // Retourner le contenu HTML généré
    return ob_get_clean();
}

// Enregistrement du shortcode [gce_frontend_events] qui appelle la fonction gce_display_frontend_events
add_shortcode('gce_frontend_events', 'gce_display_frontend_events');

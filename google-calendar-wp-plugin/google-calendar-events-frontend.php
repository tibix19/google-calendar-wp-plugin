<?php

function adjustBrightness($hex, $steps)
{
    // Enlever le # si présent
    $hex = str_replace('#', '', $hex);

    // Convertir en RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Ajuster la luminosité
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));

    // Reconvertir en hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Shortcode pour afficher les événements sur le frontend
function gce_display_frontend_events()
{
    $categories_manager = new GCE_Categories_Manager();
    $categories = $categories_manager->get_categories();

    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');

    if (!$api_key || !$calendar_id) {
        return '<p>Veuillez configurer la clé API et l\'ID du calendrier dans l\'administration.</p>';
    }

    ob_start();
?>
    <div class="gce-events-container">
        <div class="gce-filters">
            <button id="gce-filter-all" class="gce-filter-btn active">Afficher tout</button>
            <?php foreach ($categories as $category): ?>
                <button id="gce-filter-<?php echo esc_attr($category['slug']); ?>"
                    class="gce-filter-btn"
                    data-filter="<?php echo esc_attr($category['slug']); ?>">
                    <?php echo esc_html($category['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="gce-toggle-events">
            <button id="gce-show-past-events">Voir les événements passés</button>
            <button id="gce-show-future-events" style="display:none;">Voir les événements futurs</button>
        </div>
        <div id="gce-events-list">
            <?php gce_display_events($api_key, $calendar_id, 'future', 'all'); ?>
        </div>
    </div>

    <style>
        <?php foreach ($categories as $category): ?>.gce-<?php echo esc_attr($category['slug']); ?> {
            background-color: <?php echo esc_attr($category['color']); ?>;
        }

        #gce-filter-<?php echo esc_attr($category['slug']); ?> {
            background-color: <?php echo esc_attr($category['color']); ?>;
        }

        #gce-filter-<?php echo esc_attr($category['slug']); ?>.active {
            background-color: <?php echo esc_attr(adjustBrightness($category['color'], -10)); ?>;
        }

        <?php endforeach; ?>
    </style>
<?php
    return ob_get_clean();
}

add_shortcode('gce_frontend_events', 'gce_display_frontend_events');

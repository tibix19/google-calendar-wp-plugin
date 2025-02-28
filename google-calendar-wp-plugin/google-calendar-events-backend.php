<?php
// Configuration functions
function gce_save_settings()
{
    if (isset($_POST['gce_api_key']) && isset($_POST['gce_calendar_id'])) {
        update_option('gce_api_key', sanitize_text_field($_POST['gce_api_key']));
        update_option('gce_calendar_id', sanitize_text_field($_POST['gce_calendar_id']));
        return true;
    }
    return false;
}

function gce_get_settings()
{
    return [
        'api_key' => get_option('gce_api_key'),
        'calendar_id' => get_option('gce_calendar_id')
    ];
}

// API interaction functions
function gce_fetch_events($api_key, $calendar_id, $sort_order = 'future')
{
    $current_time = date('c');

    // Base URL
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) .
        '/events?key=' . urlencode($api_key) . '&singleEvents=true&orderBy=startTime&maxResults=2500';

    if ($sort_order == 'future') {
        $url .= '&timeMin=' . urlencode($current_time);
    } elseif ($sort_order == 'past') {
        // Calculer la date d'il y a un an
        $one_year_ago = date('c', strtotime('-1 year'));
        $url .= '&timeMin=' . urlencode($one_year_ago) . '&timeMax=' . urlencode($current_time);
    }

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Event processing functions
function gce_format_event_date($datetime_str, $is_all_day = false)
{
    $datetime = new DateTime($datetime_str);
    $formatted = $datetime->format('d/m/Y');
    if (!$is_all_day && $datetime->format('H:i') !== '00:00') {
        $formatted .= ' ' . $datetime->format('H:i');
    }
    return $formatted;
}

function gce_get_event_class($summary)
{
    $categories_manager = new GCE_Categories_Manager();
    $categories = $categories_manager->get_categories();
    $summary_lower = mb_strtolower(remove_accents($summary));

    foreach ($categories as $category) {
        $category_name_lower = mb_strtolower(remove_accents($category['name']));
        if (strpos($summary_lower, $category_name_lower) !== false) {
            return 'gce-' . $category['slug'];
        }
    }
    return 'gce-uncategorized';
}

// Display functions
function gce_display_events($api_key, $calendar_id, $sort_order = 'future', $filter = 'all')
{
    $events_data = gce_fetch_events($api_key, $calendar_id, $sort_order);
    $current_time = new DateTime();
    $today = new DateTime('today');

    if (isset($events_data['error'])) {
        echo '<p>Erreur lors de la récupération des événements : ' . $events_data['error'] . '</p>';
        return;
    }

    if (empty($events_data['items'])) {
        echo '<p>Aucun événement trouvé.</p>';
        return;
    }

    $filtered_events = array_filter($events_data['items'], function ($event) use ($today, $sort_order) {
        $start_date = new DateTime($event['start']['dateTime'] ?? $event['start']['date']);
        $end_date = new DateTime($event['end']['dateTime'] ?? $event['end']['date']);
        $end_date->modify('-1 day'); // Pour gérer les événements en journée complète

        if ($sort_order === 'future') {
            // Inclut les événements d'aujourd'hui ou à venir comme "futurs"
            return $end_date >= $today;
        } elseif ($sort_order === 'past') {
            // Inclut uniquement les événements entièrement passés
            return $end_date < $today;
        }

        return true;
    });

    usort($filtered_events, function ($a, $b) use ($sort_order) {
        $a_start = $a['start']['dateTime'] ?? $a['start']['date'];
        $b_start = $b['start']['dateTime'] ?? $b['start']['date'];
        return $sort_order === 'future' ? strcmp($a_start, $b_start) : strcmp($b_start, $a_start);
    });

    gce_render_events_table($filtered_events, $filter);
}

function gce_render_events_table($events, $filter)
{
    $categories_manager = new GCE_Categories_Manager();
    $categories = $categories_manager->get_categories();
    $settings = gce_get_settings();
    $calendar_id = $settings['calendar_id'];

    echo '<div class="gce-events-wrapper">';
    echo '<table class="gce-events-table">';
    echo '<thead><tr>
            <th>Date</th>
            <th>Titre</th>
            <th>Lieu</th>
            <th>Détails</th>
          </tr></thead>';
    echo '<tbody>';

    foreach ($events as $event) {
        $event_class = gce_get_event_class($event['summary'] ?? '');

        // Nouvelle logique de filtrage
        $should_display = false;

        if ($filter === 'all') {
            // Afficher tous les événements
            $should_display = true;
        } elseif ($filter === 'uncategorized') {
            // Afficher uniquement les événements non catégorisés
            $should_display = ($event_class === 'gce-uncategorized');
        } else {
            // Pour une catégorie spécifique, afficher :
            // 1. Les événements de cette catégorie
            // 2. Les événements non catégorisés
            $should_display = ($event_class === 'gce-' . $filter) || ($event_class === 'gce-uncategorized');
        }

        if (!$should_display) {
            continue;
        }

        $is_all_day = !isset($event['start']['dateTime']);
        $start = gce_format_event_date($event['start']['dateTime'] ?? $event['start']['date'], $is_all_day);
        $end = gce_format_event_date($event['end']['dateTime'] ?? $event['end']['date'], $is_all_day);

        if ($is_all_day) {
            $end = (new DateTime($event['end']['date']))->modify('-1 day')->format('d/m/Y');
        }

        $event_id = $event['id'];
        $event_link = 'https://calendar.google.com/calendar/u/0/r/eventedit/' . urlencode($event_id) . '?cid=' . urlencode($calendar_id);

        $category_color = '';
        foreach ($categories as $category) {
            if ('gce-' . $category['slug'] === $event_class) {
                $category_color = $category['color'];
                break;
            }
        }

        echo '<tr>';
        echo '<td class="gce-date-column">';
        if ($category_color) {
            echo '<div class="gce-category-indicator" style="background-color: ' . esc_attr($category_color) . ';"></div>';
        }

        if ($start === $end) {
            echo '<div class="gce-start-date">' . esc_html($start) . '</div>';
        } else {
            echo '<div class="gce-start-date">' . esc_html($start) . '</div>';
            echo '<div class="gce-date-separator"></div>';
            echo '<div class="gce-end-date">' . esc_html($end) . '</div>';
        }
        echo '</td>';
        echo '<td>' . esc_html($event['summary'] ?? 'Sans titre') . '</td>';
        echo '<td>' . esc_html($event['location'] ?? 'Non spécifié') . '</td>';
        echo '<td><a href="' . esc_url($event['htmlLink']) . '" target="_blank">Voir plus</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '<div class="gce-calendar-link">';
    echo '<a href="https://calendar.google.com/calendar/u/0/embed?src=' . urlencode($calendar_id) . '" 
             target="_blank" 
             class="gce-google-calendar-btn">
             Voir le calendrier complet sur Google Calendar
          </a>';
    echo '</div>';
}

// Admin page function
function gce_admin_page()
{
    $categories_manager = new GCE_Categories_Manager();

    // Traitement de l'ajout de catégorie
    if (isset($_POST['add_category'])) {
        $name = sanitize_text_field($_POST['category_name']);
        $slug = sanitize_text_field($_POST['category_slug']);
        $color = sanitize_hex_color($_POST['category_color']);
        $categories_manager->add_category($name, $slug, $color);
    }

    // Traitement de la suppression
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['category_id']);
        $categories_manager->delete_category($id);
    }

    // Récupération des catégories
    $categories = $categories_manager->get_categories();
?>
    <div class="wrap">
        <h1>Google Calendar Events</h1>

        <!-- Configuration API -->
        <form method="post" action="">
            <h2>Configuration API</h2>
            <?php
            if (gce_save_settings()) {
                echo '<div class="updated"><p>Paramètres enregistrés avec succès.</p></div>';
            }
            $settings = gce_get_settings();
            ?>
            <table class="form-table">
                <tr>
                    <th>Google Calendar API Key</th>
                    <td><input type="text" name="gce_api_key" value="<?php echo esc_attr($settings['api_key']); ?>" size="50" /></td>
                </tr>
                <tr>
                    <th>Google Calendar ID</th>
                    <td><input type="text" name="gce_calendar_id" value="<?php echo esc_attr($settings['calendar_id']); ?>" size="50" /></td>
                </tr>
            </table>
            <?php submit_button('Enregistrer les paramètres'); ?>
        </form>

        <!-- Gestion des catégories -->
        <h2>Gestion des catégories</h2>
        <form method="post" action="" class="add-category-form">
            <table class="form-table">
                <tr>
                    <th>Nom de la catégorie</th>
                    <td><input type="text" name="category_name" required /></td>
                </tr>
                <tr>
                    <th>Slug (Nom de la cat. en minuscule)</th>
                    <td><input type="text" name="category_slug" required /></td>
                </tr>
                <tr>
                    <th>Couleur</th>
                    <td><input type="color" name="category_color" value="#ff0000" required /></td>
                </tr>
            </table>
            <input type="submit" name="add_category" class="button button-primary" value="Ajouter une catégorie" />
        </form>

        <h3>Catégories existantes</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Slug</th>
                    <th>Couleur</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo esc_html($category['name']); ?></td>
                        <td><?php echo esc_html($category['slug']); ?></td>
                        <td>
                            <span style="background-color: <?php echo esc_attr($category['color']); ?>; 
                                   display: inline-block; 
                                   width: 20px; 
                                   height: 20px; 
                                   margin-right: 5px;"></span>
                            <?php echo esc_html($category['color']); ?>
                        </td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>" />
                                <input type="submit" name="delete_category" class="button button-small button-link-delete"
                                    value="Supprimer" onclick="return confirm('Êtes-vous sûr ?');" />
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
}

// Ajax handler
function gce_filter_events()
{
    $settings = gce_get_settings();
    $filter = $_POST['filter'] ?? 'all';
    $sort_order = $_POST['sort_order'] ?? 'future';
    gce_display_events($settings['api_key'], $settings['calendar_id'], $sort_order, $filter);
    wp_die();
}

add_action('wp_ajax_gce_filter_events', 'gce_filter_events');
add_action('wp_ajax_nopriv_gce_filter_events', 'gce_filter_events');

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
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) .
        '/events?key=' . urlencode($api_key) . '&singleEvents=true&orderBy=startTime&maxResults=2500';

    if ($sort_order == 'future') {
        $url .= '&timeMin=' . urlencode($current_time);
    } elseif ($sort_order == 'past') {
        $url .= '&timeMax=' . urlencode($current_time);
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
    $summary_lower = mb_strtolower(remove_accents($summary));
    if (strpos($summary_lower, 'athletisme') !== false) return 'gce-athletisme';
    if (strpos($summary_lower, 'agres') !== false) return 'gce-agres';
    if (strpos($summary_lower, 'danse') !== false) return 'gce-gd';
    return '';
}

// Display functions
function gce_display_events($api_key, $calendar_id, $sort_order = 'future', $filter = 'all')
{
    $events_data = gce_fetch_events($api_key, $calendar_id, $sort_order);

    if (isset($events_data['error'])) {
        echo '<p>Erreur lors de la récupération des événements : ' . $events_data['error'] . '</p>';
        return;
    }

    if (empty($events_data['items'])) {
        echo '<p>Aucun événement trouvé.</p>';
        return;
    }

    usort($events_data['items'], function ($a, $b) use ($sort_order) {
        $a_start = isset($a['start']['dateTime']) ? $a['start']['dateTime'] : $a['start']['date'];
        $b_start = isset($b['start']['dateTime']) ? $b['start']['dateTime'] : $b['start']['date'];
        return $sort_order == 'future' ? strcmp($a_start, $b_start) : strcmp($b_start, $a_start);
    });

    gce_render_events_table($events_data['items'], $filter);
}

function gce_render_events_table($events, $filter)
{
    echo '<div class="gce-events-wrapper">';
    echo '<table class="gce-events-table">';
    echo '<thead><tr>
            <th>Date</th>
            <th>Titre</th>
            <th>Lieu</th>
          </tr></thead>';
    echo '<tbody>';

    foreach ($events as $event) {
        $event_class = gce_get_event_class($event['summary'] ?? '');
        if ($filter !== 'all' && $event_class !== 'gce-' . $filter && $event_class !== '') continue;

        $is_all_day = !isset($event['start']['dateTime']);
        $start = gce_format_event_date($event['start']['dateTime'] ?? $event['start']['date'], $is_all_day);
        $end = gce_format_event_date($event['end']['dateTime'] ?? $event['end']['date'], $is_all_day);

        echo '<tr class="' . $event_class . '">';
        echo '<td class="gce-date-column">';
        echo '<div class="gce-start-date">' . esc_html($start) . '</div>';
        // Afficher la date de fin uniquement si elle est différente de la date de début
        if ($start !== $end && !($is_all_day && date('Y-m-d', strtotime($end)) === date('Y-m-d', strtotime($start)))) {
            echo '<div class="gce-date-separator"></div>';
            echo '<div class="gce-end-date">' . esc_html($end) . '</div>';
        }
        echo '</td>';
        echo '<td>' . esc_html($event['summary'] ?? 'Sans titre') . '</td>';
        echo '<td>' . esc_html($event['location'] ?? 'Non spécifié') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// Admin page function
function gce_admin_page()
{
?>
    <div class="wrap">
        <h1>Google Calendar Events</h1>
        <form method="post" action="">
            <?php
            if (gce_save_settings()) {
                echo '<div class="updated"><p>Clé API et ID du calendrier enregistrés avec succès.</p></div>';
            }
            $settings = gce_get_settings();
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Calendar API Key</th>
                    <td><input type="text" name="gce_api_key" value="<?php echo esc_attr($settings['api_key']); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Google Calendar ID</th>
                    <td><input type="text" name="gce_calendar_id" value="<?php echo esc_attr($settings['calendar_id']); ?>" size="50" /></td>
                </tr>
            </table>
            <?php submit_button('Enregistrer les paramètres et actualiser'); ?>
        </form>

        <?php if ($settings['api_key'] && $settings['calendar_id']): ?>
            <h2>Liste des événements</h2>
            <button id="toggle-events">Voir les événements passés</button>
            <div id="gce-events-list">
                <?php gce_display_events($settings['api_key'], $settings['calendar_id'], 'future', 'all'); ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var showingFutureEvents = true;

            $('#toggle-events').on('click', function() {
                var sortOrder = showingFutureEvents ? 'past' : 'future';
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gce_filter_events',
                        filter: 'all',
                        sort_order: sortOrder,
                        is_admin: true
                    },
                    success: function(response) {
                        $('#gce-events-list').html(response);
                        showingFutureEvents = !showingFutureEvents;
                        $('#toggle-events').text(showingFutureEvents ? 'Voir les événements passés' : 'Voir les événements futurs');
                    }
                });
            });
        });
    </script>
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

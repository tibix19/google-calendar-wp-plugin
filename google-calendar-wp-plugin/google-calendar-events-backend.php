<?php
// Fonction principale pour afficher la page d'administration
function gce_admin_page()
{
?>
    <div class="wrap">
        <h1>Google Calendar Events</h1>
        <form method="post" action="">
            <?php
            if (isset($_POST['gce_api_key']) && isset($_POST['gce_calendar_id'])) {
                update_option('gce_api_key', sanitize_text_field($_POST['gce_api_key']));
                update_option('gce_calendar_id', sanitize_text_field($_POST['gce_calendar_id']));
                echo '<div class="updated"><p>Clé API et ID du calendrier enregistrés avec succès.</p></div>';
            }

            $api_key = get_option('gce_api_key');
            $calendar_id = get_option('gce_calendar_id');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google Calendar API Key</th>
                    <td><input type="text" name="gce_api_key" value="<?php echo esc_attr($api_key); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Google Calendar ID</th>
                    <td><input type="text" name="gce_calendar_id" value="<?php echo esc_attr($calendar_id); ?>" size="50" /></td>
                </tr>
            </table>
            <?php submit_button('Enregistrer les paramètres et actualiser'); ?>
        </form>

        <?php if ($api_key && $calendar_id): ?>
            <h2>Liste des événements</h2>
            <button id="toggle-events">Voir les événements passés</button>
            <div id="gce-events-list">
                <?php gce_display_events($api_key, $calendar_id, 'future', true, true, true); ?>
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

function gce_display_events($api_key, $calendar_id, $sort_order = 'future', $filter_athletisme = true, $filter_agres = true, $is_admin = false) {
    $current_time = date('c');
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events?key=' . urlencode($api_key) . '&singleEvents=true&orderBy=startTime&maxResults=2500';

    if ($sort_order == 'future') {
        $url .= '&timeMin=' . urlencode($current_time);
    } elseif ($sort_order == 'past') {
        $url .= '&timeMax=' . urlencode($current_time);
    }

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        echo '<p>Erreur lors de la récupération des événements : ' . $response->get_error_message() . '</p>';
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $events = json_decode($body, true);

    if (empty($events['items'])) {
        echo '<p>Aucun événement trouvé.</p>';
        return;
    }

    usort($events['items'], function ($a, $b) use ($sort_order) {
        $a_start = isset($a['start']['dateTime']) ? $a['start']['dateTime'] : (isset($a['start']['date']) ? $a['start']['date'] : '');
        $b_start = isset($b['start']['dateTime']) ? $b['start']['dateTime'] : (isset($b['start']['date']) ? $b['start']['date'] : '');

        if ($sort_order == 'future') {
            return strcmp($a_start, $b_start);
        } else {
            return strcmp($b_start, $a_start);
        }
    });

    echo '<table class="gce-events-table">';
    echo '<thead><tr><th>Titre</th><th>Date de début</th><th>Date de fin</th><th>Lieu</th></tr></thead><tbody>';

    foreach ($events['items'] as $event) {
        $summary = isset($event['summary']) ? esc_html($event['summary']) : 'Sans titre';
        $start = isset($event['start']['dateTime']) ? $event['start']['dateTime'] : (isset($event['start']['date']) ? $event['start']['date'] : 'Non défini');
        $end = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : (isset($event['end']['date']) ? $event['end']['date'] : 'Non défini');
        $location = isset($event['location']) ? esc_html($event['location']) : 'Non spécifié';

        $summary_lower = mb_strtolower(remove_accents($summary));
        $event_class = '';

        if (!$is_admin) {
            if (strpos($summary_lower, 'athletisme') !== false) {
                if (!$filter_athletisme) continue;
                $event_class = 'gce-athletisme';
            } elseif (strpos($summary_lower, 'agres') !== false) {
                if (!$filter_agres) continue;
                $event_class = 'gce-agres';
            }
        }

        echo '<tr class="' . $event_class . '">';
        echo '<td>' . $summary . '</td>';
        echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($start))) . '</td>';
        echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($end))) . '</td>';
        echo '<td>' . $location . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

function gce_filter_events() {
    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');
    $filter_athletisme = isset($_POST['filter_athletisme']) && $_POST['filter_athletisme'] === 'true';
    $filter_agres = isset($_POST['filter_agres']) && $_POST['filter_agres'] === 'true';
    $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'future';
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] === 'true';

    gce_display_events($api_key, $calendar_id, $sort_order, $filter_athletisme, $filter_agres, $is_admin);
    wp_die();
}
add_action('wp_ajax_gce_filter_events', 'gce_filter_events');
add_action('wp_ajax_nopriv_gce_filter_events', 'gce_filter_events');
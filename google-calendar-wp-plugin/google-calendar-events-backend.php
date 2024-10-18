<?php
// Fonction principale pour afficher la page d'administration
function gce_admin_page()
{
?>
    <div class="wrap">
        <h1>Google Calendar Events</h1>
        <form method="post" action="">
            <?php
            // Vérifier si les données de la clé API et de l'ID du calendrier ont été entrées
            if (isset($_POST['gce_api_key']) && isset($_POST['gce_calendar_id'])) {
                // Mettre à jour les options dans la base de données après avoir nettoyé les entrées
                update_option('gce_api_key', sanitize_text_field($_POST['gce_api_key']));
                update_option('gce_calendar_id', sanitize_text_field($_POST['gce_calendar_id']));
                // Afficher un message de succès après enregistrement
                echo '<div class="updated"><p>Clé API et ID du calendrier enregistrés avec succès.</p></div>';
            }

            // Récupérer les valeurs de la clé API et de l'ID du calendrier depuis la base de données
            $api_key = get_option('gce_api_key');
            $calendar_id = get_option('gce_calendar_id');
            ?>
            <!-- Tableau dans le panel admin avec la clé API, l'ID et les bouton pour enregistrer -->
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
            <div class="gce-filters">
                <!-- <button id="gce-filter-all" class="gce-filter-btn active">Afficher tout</button>
                <button id="gce-filter-athletisme" class="gce-filter-btn" data-filter="athletisme">Athlétisme</button>
                <button id="gce-filter-agres" class="gce-filter-btn" data-filter="agres">Agrès</button> -->
            </div>
            <button id="toggle-events">Voir les événements passés</button>
            <div id="gce-events-list">
                <?php gce_display_events($api_key, $calendar_id, 'future', 'all', true); ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var showingFutureEvents = true;

            $('.gce-filter-btn').on('click', function() {
                $('.gce-filter-btn').removeClass('active');
                $(this).addClass('active');
                var filter = $(this).data('filter') || 'all';
                updateEvents(filter);
            });

            $('#toggle-events').on('click', function() {
                var sortOrder = showingFutureEvents ? 'past' : 'future';
                updateEvents($('.gce-filter-btn.active').data('filter') || 'all', sortOrder);
            });

            function updateEvents(filter, sortOrder) {
                sortOrder = sortOrder || (showingFutureEvents ? 'future' : 'past');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gce_filter_events',
                        filter: filter,
                        sort_order: sortOrder,
                        is_admin: true
                    },
                    success: function(response) {
                        $('#gce-events-list').html(response);
                        showingFutureEvents = (sortOrder === 'future');
                        $('#toggle-events').text(showingFutureEvents ? 'Voir les événements passés' : 'Voir les événements futurs');
                    }
                });
            }
        });
    </script>
<?php
}

// Fonction pour afficher les événements à partir de l'API Google Calendar
function gce_display_events($api_key, $calendar_id, $sort_order = 'future', $filter = 'all', $is_admin = false)
{
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
    echo '<thead><tr><th>Date</th><th>Titre</th><th>Lieu</th></tr></thead><tbody>';

    foreach ($events['items'] as $event) {
        $summary = isset($event['summary']) ? esc_html($event['summary']) : 'Sans titre';
        $start = isset($event['start']['dateTime']) ? $event['start']['dateTime'] : (isset($event['start']['date']) ? $event['start']['date'] : 'Non défini');
        $end = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : (isset($event['end']['date']) ? $event['end']['date'] : 'Non défini');
        $location = isset($event['location']) ? esc_html($event['location']) : 'Non spécifié';

        $summary_lower = mb_strtolower(remove_accents($summary));
        $event_class = '';

        if (strpos($summary_lower, 'athletisme') !== false) {
            $event_class = 'gce-athletisme';
        } elseif (strpos($summary_lower, 'agres') !== false) {
            $event_class = 'gce-agres';
        } elseif (strpos($summary_lower, 'danse') !== false) {
            $event_class = 'gce-gd';
        }


        if ($filter === 'all' || $event_class === 'gce-' . $filter || $event_class === '') {
            $start_datetime = new DateTime($start);
            $end_datetime = new DateTime($end);

            $is_all_day = !isset($event['start']['dateTime']);
            if ($is_all_day) {
                $end_datetime->modify('-1 day');
            }

            $start_formatted = $start_datetime->format('d/m/Y');
            $end_formatted = $end_datetime->format('d/m/Y');

            if ($start_datetime->format('H:i') !== '00:00') {
                $start_formatted .= ' ' . $start_datetime->format('H:i');
            }
            if ($end_datetime->format('H:i') !== '00:00') {
                $end_formatted .= ' ' . $end_datetime->format('H:i');
            }

            echo '<tr class="' . $event_class . '">';
            echo '<td class="gce-date-column">';
            echo '<div class="gce-start-date">' . esc_html($start_formatted) . '</div>';
            if ($start_formatted !== $end_formatted) {
                echo '<div class="gce-date-separator"></div>';
                echo '<div class="gce-end-date">' . esc_html($end_formatted) . '</div>';
            }
            echo '</td>';
            echo '<td>' . $summary . '</td>';
            echo '<td>' . $location . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
}

// Fonction pour filtrer les événements en fonction de la requête AJAX
function gce_filter_events()
{
    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');

    $filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
    $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'future';
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] === 'true';

    gce_display_events($api_key, $calendar_id, $sort_order, $filter, $is_admin);
    wp_die();
}

add_action('wp_ajax_gce_filter_events', 'gce_filter_events');
add_action('wp_ajax_nopriv_gce_filter_events', 'gce_filter_events');

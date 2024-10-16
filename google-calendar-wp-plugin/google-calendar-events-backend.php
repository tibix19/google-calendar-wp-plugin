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
            <button id="toggle-events">Voir les événements passés</button>
            <div id="gce-events-list">
                <!-- Afficher les événements futurs en appelant la fonction gce_display_events -->
                <?php gce_display_events($api_key, $calendar_id, 'future', true, true, true); ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function($) {
            var showingFutureEvents = true; // État pour savoir si les événements futurs sont affichés
            $('#toggle-events').on('click', function() {
                // Déterminer l'ordre de tri (passé ou futur) en fonction de l'état actuel
                var sortOrder = showingFutureEvents ? 'past' : 'future';
                $.ajax({
                    url: ajaxurl, // URL de l'Ajax de WordPress
                    type: 'POST', // Méthode POST pour envoyer la requête
                    data: {
                        action: 'gce_filter_events', // Action à effectuer
                        sort_order: sortOrder, // Ordre de tri sélectionné
                        is_admin: true // Indiquer que la requête vient de l'admin
                    },
                    success: function(response) {
                        // Mettre à jour la liste des événements avec la réponse reçue
                        $('#gce-events-list').html(response);
                        showingFutureEvents = !showingFutureEvents; // Inverser l'état
                        // Changer le texte du bouton en fonction de l'état actuel
                        $('#toggle-events').text(showingFutureEvents ? 'Voir les événements passés' : 'Voir les événements futurs');
                    }
                });
            });
        });
    </script>
<?php
}

// Fonction pour afficher les événements à partir de l'API Google Calendar
function gce_display_events($api_key, $calendar_id, $sort_order = 'future', $filter_athletisme = true, $filter_agres = true, $is_admin = false)
{
    $current_time = date('c'); // Obtenir l'heure actuelle au format ISO 8601
    // define the url for the API request to the Google calendar
    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events?key=' . urlencode($api_key) . '&singleEvents=true&orderBy=startTime&maxResults=2500';

    // Ajuster l'URL selon l'ordre de tri souhaité (futur ou passé)
    if ($sort_order == 'future') {
        $url .= '&timeMin=' . urlencode($current_time); // include only future event
    } elseif ($sort_order == 'past') {
        $url .= '&timeMax=' . urlencode($current_time); // include only past event
    }

    // Effectuer la requête à l'API
    $response = wp_remote_get($url);
    // Vérifier s'il y a une erreur dans la réponse
    if (is_wp_error($response)) {
        echo '<p>Erreur lors de la récupération des événements : ' . $response->get_error_message() . '</p>';
        return;
    }

    // Récupérer le corps de la réponse et décoder le json en tableau
    $body = wp_remote_retrieve_body($response);
    $events = json_decode($body, true);

    // Vérifier si des événements ont été trouvés
    if (empty($events['items'])) {
        echo '<p>Aucun événement trouvé.</p>';
        return;
    }

    // Trier les événements selon l'ordre de tri spécifié
    usort($events['items'], function ($a, $b) use ($sort_order) {
        $a_start = isset($a['start']['dateTime']) ? $a['start']['dateTime'] : (isset($a['start']['date']) ? $a['start']['date'] : '');
        $b_start = isset($b['start']['dateTime']) ? $b['start']['dateTime'] : (isset($b['start']['date']) ? $b['start']['date'] : '');

        // Comparer les dates pour trier les événements
        if ($sort_order == 'future') {
            return strcmp($a_start, $b_start);
        } else {
            return strcmp($b_start, $a_start);
        }
    });

    // Afficher les événements dans un tableau HTML
    echo '<table class="gce-events-table">';
    echo '<thead><tr><th>Date</th><th>Titre</th><th>Lieu</th></tr></thead><tbody>';

    // Parcourir les événements et les afficher
    foreach ($events['items'] as $event) {
        // Récupérer les détails de l'événement
        $summary = isset($event['summary']) ? esc_html($event['summary']) : 'Sans titre';
        $start = isset($event['start']['dateTime']) ? $event['start']['dateTime'] : (isset($event['start']['date']) ? $event['start']['date'] : 'Non défini');
        $end = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : (isset($event['end']['date']) ? $event['end']['date'] : 'Non défini');
        $location = isset($event['location']) ? esc_html($event['location']) : 'Non spécifié';

        // Convertir le titre en minuscules pour le filtrage
        $summary_lower = mb_strtolower(remove_accents($summary));
        $event_class = ''; // Classe CSS pour le filtrage

        // Appliquer le filtrage en fonction des cases à cocher
        if (!$is_admin) {
            if (strpos($summary_lower, 'athletisme') !== false) {
                if (!$filter_athletisme) continue;
                $event_class = 'gce-athletisme';
            } elseif (strpos($summary_lower, 'agres') !== false) {
                if (!$filter_agres) continue;
                $event_class = 'gce-agres';
            }
        }

        // Créer des objets DateTime pour formater les dates
        $start_datetime = new DateTime($start);
        $end_datetime = new DateTime($end);

        // Ajuster la date de fin pour les événements toute la journée
        $is_all_day = !isset($event['start']['dateTime']);
        if ($is_all_day) {
            $end_datetime->modify('-1 day'); // Ajuster la date de fin pour qu'elle soit correcte
        }

        // Formater les dates pour l'affichage
        $start_formatted = $start_datetime->format('d/m/Y');
        $end_formatted = $end_datetime->format('d/m/Y');

        // Si la date de l'heure est égale à 00:00 on n'affiche pas l'heure
        if ($start_datetime->format('H:i') !== '00:00') {
            $start_formatted .= ' ' . $start_datetime->format('H:i');
        }
        if ($end_datetime->format('H:i') !== '00:00') {
            $end_formatted .= ' ' . $end_datetime->format('H:i');
        }

        // Afficher l'événement dans une ligne du tableau
        echo '<tr class="' . $event_class . '">';
        echo '<td class="gce-date-column">';
        echo '<div class="gce-start-date">' . esc_html($start_formatted) . '</div>';
        if ($start_formatted !== $end_formatted) {
            echo '<div class="gce-date-separator"></div>'; // Ajout du séparateur
            echo '<div class="gce-end-date">' . esc_html($end_formatted) . '</div>';
        }
        echo '</td>';
        echo '<td>' . $summary . '</td>';
        echo '<td>' . $location . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// Fonction pour filtrer les événements en fonction de la requête AJAX
function gce_filter_events()
{
    // Récupérer la clé API et l'ID du calendrier depuis les options enregistrées
    $api_key = get_option('gce_api_key');
    $calendar_id = get_option('gce_calendar_id');

    // Récupérer les paramètres envoyés par la requête AJAX
    $filter_athletisme = isset($_POST['filter_athletisme']) && $_POST['filter_athletisme'] === 'true';
    $filter_agres = isset($_POST['filter_agres']) && $_POST['filter_agres'] === 'true';
    $sort_order = isset($_POST['sort_order']) ? $_POST['sort_order'] : 'future'; // Ordre par défaut: événements futurs
    $is_admin = isset($_POST['is_admin']) && $_POST['is_admin'] === 'true'; // Vérifier si la requête vient de l'admin

    // Appeler la fonction pour afficher les événements avec les paramètres de filtrage
    gce_display_events($api_key, $calendar_id, $sort_order, $filter_athletisme, $filter_agres, $is_admin);
    wp_die();
}

// Ajouter les actions AJAX pour filtrer les événements (pour les utilisateurs connectés et non connectés)
add_action('wp_ajax_gce_filter_events', 'gce_filter_events');
add_action('wp_ajax_nopriv_gce_filter_events', 'gce_filter_events');

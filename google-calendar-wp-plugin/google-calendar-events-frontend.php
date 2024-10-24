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
        <button class="gce-burger-menu" aria-label="Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <div class="gce-filters">
            <button id="gce-filter-all" class="gce-filter-btn active">Afficher tout</button>
            <?php foreach ($categories as $category): ?>
                <button id="gce-filter-<?php echo esc_attr($category['slug']); ?>"
                    class="gce-filter-btn"
                    data-filter="<?php echo esc_attr($category['slug']); ?>">
                    <?php echo esc_html($category['name']); ?>
                </button>
            <?php endforeach; ?>
            <button id="gce-filter-uncategorized" class="gce-filter-btn" data-filter="uncategorized">
                Divers
            </button>
        </div>
        <div class="gce-toggle-events">
            <a href="#" id="gce-show-past-events" class="gce-toggle-link">
                &#129144; Voir les événements passés
            </a>
            <a href="#" id="gce-show-future-events" class="gce-toggle-link" style="display:none;">
                Voir les événements futurs &#129146;
            </a>
        </div>
        <div id="gce-events-list">
            <?php gce_display_events($api_key, $calendar_id, 'future', 'all'); ?>
        </div>
    </div>

    <style>
        /* Styles des catégories */
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

        /* Styles de base */
        #gce-filter-uncategorized {
            background-color: #f0f0f0;
        }

        #gce-filter-uncategorized.active {
            background-color: #d0d0d0;
        }

        .gce-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Styles des liens */
        .gce-toggle-link {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s, text-decoration 0.3s;

        }

        .gce-toggle-link:hover {
            color: red;
        }

        /* Styles de la table */
        .gce-events-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .gce-events-table th,
        .gce-events-table td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .gce-events-table th {
            font-weight: bold;
        }

        .gce-date-column {
            position: relative;
            padding-left: 20px !important;
        }

        .gce-category-indicator {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 12px;
        }

        .gce-events-table tr:last-child td {
            border-bottom: none;
        }

        .gce-events-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .gce-events-table tbody tr:hover {
            background-color: rgba(255, 0, 0, 0.1);
        }

        /* Style du bouton burger */
        .gce-burger-menu {
            display: none;
            /* Masqué par défaut pour les grandes fenêtres */
            background: none;
            border: none;
            cursor: pointer;
            padding: 10px;
            position: relative;
            z-index: 1001;
            margin-bottom: 10px;
        }

        /* Barres du burger */
        .gce-burger-menu span {
            display: block;
            width: 25px;
            height: 3px;
            background-color: #333;
            margin: 5px 0;
            transition: all 0.3s ease;
        }

        /* Media query pour mobile : afficher le burger menu */
        @media screen and (max-width: 550px) {
            .gce-burger-menu {
                display: block;
                /* Visible uniquement sur petit écran */
            }

            .gce-filters {
                display: none;
                position: absolute;
                top: 50px;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                flex-direction: column;
                padding: 10px;
                z-index: 1000;
            }

            .gce-filters.show {
                display: flex;
            }

            .gce-filter-btn {
                width: 100%;
                text-align: left;
                padding: 12px;
                margin: 0;
                border: none;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }

            .gce-filter-btn:last-child {
                border-bottom: none;
            }

            .gce-burger-menu.active span:nth-child(1) {
                transform: rotate(-45deg) translate(-5px, 6px);
            }

            .gce-burger-menu.active span:nth-child(2) {
                opacity: 0;
            }

            .gce-burger-menu.active span:nth-child(3) {
                transform: rotate(45deg) translate(-5px, -6px);
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const burgerMenu = document.querySelector('.gce-burger-menu');
            const filters = document.querySelector('.gce-filters');
            const filterButtons = document.querySelectorAll('.gce-filter-btn');

            // Fonction pour gérer l'affichage des filtres et du burger menu en fonction de la taille de l'écran
            function handleResize() {
                if (window.innerWidth > 550) {
                    // Sur les grands écrans, le burger menu n'est pas nécessaire, et les filtres sont toujours visibles
                    filters.style.display = 'flex'; // Afficher les filtres
                    burgerMenu.style.display = 'none'; // Cacher le burger menu
                } else {
                    // Sur les petits écrans, cacher les filtres et afficher le burger menu
                    filters.style.display = 'none'; // Cacher les filtres au départ
                    burgerMenu.style.display = 'block'; // Afficher le burger menu
                }
            }

            // Initialiser l'affichage selon la taille de la fenêtre
            handleResize();

            // Mettre à jour l'affichage lors du redimensionnement de la fenêtre
            window.addEventListener('resize', handleResize);

            // Gestion du clic sur le burger menu pour afficher/masquer les filtres sur mobile
            burgerMenu.addEventListener('click', function() {
                burgerMenu.classList.toggle('active');
                if (filters.style.display === 'none') {
                    filters.style.display = 'flex'; // Afficher les filtres quand le burger est activé
                } else {
                    filters.style.display = 'none'; // Cacher les filtres quand le burger est désactivé
                }
            });
            // Fermer le menu quand on clique sur un bouton de filtre (seulement sur mobile)
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (window.innerWidth <= 550) { // Seulement sur petit écran
                        burgerMenu.classList.remove('active');
                        filters.style.display = 'none'; // Cacher les filtres après la sélection d'un filtre
                    }
                });
            });
        });
    </script>
<?php
    return ob_get_clean();
}

add_shortcode('gce_frontend_events', 'gce_display_frontend_events');

<?php
/*
Plugin Name: Google Calendar Events Plugin Timeo 
Description: Un plugin pour afficher les événements d'un calendrier Google avec filtrage et tri.
Version: 1.3
Author: Beuchat Timéo
*/

// Inclure les fichiers nécessaires pour le frontend et le backend
include(plugin_dir_path(__FILE__) . 'google-calendar-events-backend.php');
include(plugin_dir_path(__FILE__) . 'google-calendar-events-frontend.php');

// Création du menu d'administration dans le tableau de bord de WordPress
function gce_add_admin_menu()
{
    add_menu_page(
        'Google Calendar Events', // Titre de la page
        'Google Calendar Events', // Titre du menu
        'manage_options', // Capacité requise pour accéder à cette page
        'gce-plugin', // Slug du menu
        'gce_admin_page', // Fonction qui affiche le contenu de la page
        'dashicons-calendar-alt' // Icône
    );
}
add_action('admin_menu', 'gce_add_admin_menu'); // Ajouter le menu à l'action 'admin_menu'

// Enregistrement des options dans la base de données
function gce_register_settings()
{
    register_setting('gce_settings_group', 'gce_api_key'); // Enregistrer la clé API
    register_setting('gce_settings_group', 'gce_calendar_id'); // Enregistrer l'ID du calendrier
}
add_action('admin_init', 'gce_register_settings'); // Enregistrer les paramètres lors de l'initialisation de l'admin

// Fonction pour ajouter les scripts et styles
function gce_enqueue_scripts()
{
    // Ajouter le script jQuery
    wp_enqueue_script(
        'gce-script', // handle du script
        plugin_dir_url(__FILE__) . 'js/gce-script.js', // path
        array('jquery'), // // Dépendance jQuery déclaré
        '1.1',
        true // Charger le footer
    );

    // Ajouter le style CSS
    wp_enqueue_style(
        'gce-style', // handle du style
        plugin_dir_url(__FILE__) . 'css/gce-style.css' // path
    );

    // Localiser le script pour permettre l'accès à l'URL d'Ajax
    wp_localize_script('gce-script', 'gce_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Enqueue pour le frontend et le backend
add_action('wp_enqueue_scripts', 'gce_enqueue_scripts');
add_action('admin_enqueue_scripts', 'gce_enqueue_scripts');

<?php
/*
Plugin Name: Google Calendar Events Plugin Timeo 
Description: Un plugin pour afficher les événements d'un calendrier Google avec filtrage et tri.
Version: 2.0
Author: Beuchat Timéo
*/

// Inclure les fichiers nécessaires pour le frontend et le backend
include(plugin_dir_path(__FILE__) . 'google-calendar-events-backend.php');
include(plugin_dir_path(__FILE__) . 'google-calendar-events-frontend.php');

// Création du menu d'administration
function gce_add_admin_menu() {
    add_menu_page('Google Calendar Events', 'Google Calendar Events', 'manage_options', 'gce-plugin', 'gce_admin_page', 'dashicons-calendar-alt');
}
add_action('admin_menu', 'gce_add_admin_menu');

// Enregistrement des options dans la base de données
function gce_register_settings() {
    register_setting('gce_settings_group', 'gce_api_key');
    register_setting('gce_settings_group', 'gce_calendar_id');
}
add_action('admin_init', 'gce_register_settings');

// Fonction pour ajouter les scripts et styles
function gce_enqueue_scripts() {
    wp_enqueue_script('gce-script', plugin_dir_url(__FILE__) . 'js/gce-script.js', array('jquery'), '1.0', true);
    wp_enqueue_style('gce-style', plugin_dir_url(__FILE__) . 'css/gce-style.css');
    wp_localize_script('gce-script', 'gce_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'gce_enqueue_scripts');
add_action('admin_enqueue_scripts', 'gce_enqueue_scripts');

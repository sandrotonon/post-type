<?php
/*
Plugin Name: Post Type
Description: Plugin um den Post Type zu testen
Version: 1.0
Author: Sandro Tonon
License: GPLv2
*/

// Init Includes
require_once('rp-init.php');

/*
 * Registriere den Post-Type
 */
add_action('init', 'rp_registriere_post_type_spieler');
function rp_registriere_post_type_spieler() {
  /*
   * Fuege Kategorie 'Mannschaft' hinzu
   */
  register_taxonomy(
    'rp_spieler_mannschaft',
    'rp_spieler', array(
      'labels' => array(
        'name' => 'Mannschaften',
        'singular_name' => 'Mannschaft',
        'search_items' => 'Mannschaft suchen',
        'all_items' => 'Alle Mannschaften',
        'add_new_item' => 'Neue Mannschaft hinzufügen',
      ),
      'hierarchical' => true,
      'show_ui' => true,
      'public'=> true,
      'rewrite' => array(
        'slug' => 'spieler',
        'with_front' => true
      )
    )
  );

  /*
   * Registriere den Post Type rp_spieler
   */
  register_post_type('rp_spieler', array(
    'label' => 'Spieler',
    'description' => 'Post Type: Spieler',
    'public' => true,
    'show_ui' => true,
    'show_in_menu' => true,
    'capability_type' => 'post',
    'map_meta_cap' => true,
    'hierarchical' => false,
    'rewrite' => array(
      'slug' => 'spieler/%rp_spieler_mannschaft%',
      'with_front' => true
    ),
    'has_archive' => 'spieler',
    'query_var' => true,
    'menu_icon' => 'dashicons-groups',
    'supports' => array(
      'title',
      'editor',
      'custom-fields',
      'revisions',
      'thumbnail',
      'page-attributes',
      'post-formats'),
    'labels' => array(
      'name' => 'Spieler',
      'singular_name' => 'Spieler',
      'menu_name' => 'Spieler',
      'add_new' => 'Spieler hinzufügen',
      'add_new_item' => 'Neuen Spieler hinzufügen',
      'edit' => 'Spieler bearbeiten',
      'edit_item' => 'Edit Spieler',
      'new_item' => 'Neuer Spieler',
      'view' => 'Spieler anzeigen',
      'view_item' => 'View Spieler',
      'search_items' => 'Search Spieler',
      'not_found' => 'Kein Spieler gefunden',
      'not_found_in_trash' => 'Kein Spieler gefunden',
      'parent' => 'Parent Spieler',
    )
  ));

  /*
   * Erstelle den Standard-Taxonomy-Term "Alle Spieler"
   * Beim Insert der Spieler, wird dieser automatisch jedem Spieler zugewiesen
   */
  wp_insert_term('Alle Spieler', 'rp_spieler_mannschaft',
    $args = array(
      'slug' => 'alle-spieler',
      'description' => 'In dieser Kategorie sind alle Spieler des Veriens'
    )
  );
}

/*
 * Activation Hooks:
 * Erstelle Einstellungen-Feld rp_results_parser_einstellungen
 * Erstelle die Seiten "Spieler" und "Alle-Spieler"
 * Erstelle Tabellen rp_spieler_daten und rp_mannschaften_daten
 * Erstelle Taxonomy-Term "Alle Spieler" - TODO
 */
register_activation_hook(__FILE__, 'rp_aktivierungs_hooks');
function rp_aktivierungs_hooks() {
  // Erstelle Einstellungen-Feld rp_results_parser_einstellungen
  // -----------------------------------------------------------
  update_option('rp_results_parser_einstellungen', 'none');

  // Erstelle die Seiten "Spieler" und "Alle-Spieler"
  // ------------------------------------------------
  $spielerSeiteObj = array(
    'post_title'    => 'Spieler',
    'post_content'  => '',
    'post_status'   => 'publish',
    'post_author'   => 1,
    'post_type' => 'page'
  );
  $spielerSeiteId = wp_insert_post($spielerSeiteObj);

  $alleSpielerSeiteObj = array(
    'post_title'    => 'Alle Spieler',
    'post_content'  => '',
    'post_status'   => 'publish',
    'post_author'   => 1,
    'post_type' => 'page',
    'post_parent' => $spielerSeiteId
  );
  $alleSpielerSeiteId = wp_insert_post($alleSpielerSeiteObj);

  // Speichere IDs in den Optionen, damit die Seiten wieder geloescht werden koennen
  $ids = array(
    'spielerSeiteId' => $spielerSeiteId,
    'alleSpielerSeiteId' => $alleSpielerSeiteId
  );
  add_option('rp_results_parser_einstellungen_data', $ids);

  // Erstelle Tabellen rp_spieler_daten und rp_mannschaften_daten
  // ------------------------------------------------------------
  global $wpdb;
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

  $charset_collate = '';

  if (!empty($wpdb->charset)) {
    $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
  }

  if (!empty($wpdb->collate)) {
    $charset_collate .= " COLLATE {$wpdb->collate}";
  }

  // Erstelle Tabellen fuer die geparsten Daten
  // Eine Tabelle fuer eine Mannschaft mit folgenden Daten:
  // tinytext name              Name der Mannschaft
  // text link                  Link zur Click-TT Seite
  // int gegner                 Anzahl der Gegner in der gleichen Liga
  // int position               Die aktuelle Position in der Liga
  // text liga                Liga, in der die Mannschaft spielt
  // text data_tabelle    enthaelt die Tabelle der Mannschaft in der Spielklasse
  // text data_ergebnisse enthaelt die Tabelle mit den bisherigen Ergebnissen der Mannschaft
  $table_name = $wpdb->prefix . 'rp_mannschaften_daten';
  $sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL,
    name tinytext NOT NULL,
    link text,
    gegner mediumint,
    position mediumint,
    liga text,
    data_tabelle text,
    data_ergebnisse text,
    UNIQUE KEY id (id)
  ) $charset_collate;";

  dbDelta($sql);

  // und Tabelle fuer einen Spieler mit den folgenden Daten:
  // int click_tt_id       die Click-TT-ID des Spielers
  // int post_id          die ID des Posts, der erstellt wird beim Import
  // text vorname         Vorname des Spielers
  // text nachname        Nachname des Spielers
  // text mannschaft      der Name der Mannschaft in der der Spieler ist
  // int bilanzwert
  // int gesamt
  // int 1                die bilanz gegen den jeweils ersten der gegnerischen Mannschaft
  // int 2
  // int 3
  // int 4
  // int 5
  // int 6
  // int 1+2
  // int 3+4
  // int 5+6
  // int einzel
  // text einsaetze
  // int position
  // double rang
  // text link
  // text data_einzel     die geparsten Daten (Bilanzen Einzel)
  // text data_doppel     die geparsten Daten (Bilanzen Doppel)
  $table_name = $wpdb->prefix . 'rp_spieler_daten';
  $sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL,
    click_tt_id mediumint NOT NULL,
    post_id mediumint NOT NULL,
    vorname text NOT NULL,
    nachname text NOT NULL,
    mannschaft text NOT NULL,
    bilanzwert int NOT NULL,
    gesamt tinytext NOT NULL,
    `gegner-1` tinytext,
    `gegner-2` tinytext,
    `gegner-3` tinytext,
    `gegner-4` tinytext,
    `gegner-5` tinytext,
    `gegner-6` tinytext,
    `1+2` tinytext,
    `3+4` tinytext,
    `5+6` tinytext,
    einzel text NOT NULL,
    einsaetze text NOT NULL,
    position mediumint NOT NULL,
    rang float NOT NULL,
    link text,
    data_einzel text,
    data_doppel text,
    UNIQUE KEY id (id)
  ) $charset_collate;";

  dbDelta($sql);
}

/*
 * Deactivation Hooks:
 * Loesche die Seiten "Spieler" und "Alle-Spieler"
 * Loesche Tabellen rp_spieler_daten und rp_mannschaften_daten
 * Loesche alle rp_spieler Posts
 * Loesche Taxnomoy rp_spieler_mannschaft und zugehoerige Terms - TODO
 * Loesche Einstellungen bei wp_options rp_results_parser_einstellungen
 */
register_deactivation_hook( __FILE__, 'rp_deaktivierungs_hooks' );
function rp_deaktivierungs_hooks() {
  // Loesche die Seiten "Spieler" und "Alle-Spieler"
  // -----------------------------------------------
  $spielerSeiteId = get_option('rp_results_parser_einstellungen')['spielerSeiteId'];
  $alleSpielerSeiteId = get_option('rp_results_parser_einstellungen')['alleSpielerSeiteId'];
  wp_delete_post($spielerSeiteId, true);
  wp_delete_post($alleSpielerSeiteId, true);


  // Loesche Tabellen rp_spieler_daten und rp_mannschaften_daten
  // -----------------------------------------------------------
  global $wpdb;
  $table = $wpdb->prefix . "rp_spieler_daten";
  $wpdb->query("DROP TABLE IF EXISTS $table");

  $table = $wpdb->prefix . "rp_mannschaften_daten";
  $wpdb->query("DROP TABLE IF EXISTS $table");


  // Loesche alle rp_spieler Posts
  // -----------------------------
  $posts_table = $wpdb->posts;
  $query = "DELETE FROM {$posts_table} WHERE post_type = 'rp_spieler'";
  $wpdb->query($query);


  // Loesche Einstellungen bei wp_options rp_results_parser_einstellungen
  // --------------------------------------------------------------------
  delete_option('rp_results_parser_einstellungen_data');
}

/*
 * Registriere eine eigene Query Variable um die Spieler auf der Alle Spieler Seite sortieren
 * zu koennen
 */
add_filter('query_vars', 'rp_add_sortier_query_variable');
function rp_add_sortier_query_variable($vars) {
  $vars[] = "sortierung";
  return $vars;
}

add_filter('post_type_link', 'rp_spieler_mannschaft_permalinks', 1, 2);
function rp_spieler_mannschaft_permalinks($permalink, $post_id) {
  $mannschaft = get_query_var('rp_spieler_mannschaft');
  if (strpos($permalink, '%rp_spieler_mannschaft%') === FALSE) {
    return $permalink;
  }
  // Get post
  $post = get_post($post_id);
  if (!$post) {
    return $permalink;
  }

  // Get taxonomy terms
  if (term_exists($mannschaft)) {
    $taxonomy_slug = $mannschaft;
  } else {
    $taxonomy_slug = 'alle-spieler';
  }

  $output = str_replace('%rp_spieler_mannschaft%', $taxonomy_slug, $permalink);

  return $output;
}

/*
 * Entferne das 'Mannschaft' aus der Überschrift eines Taxonomy
 */
add_filter('wp_title', 'rp_clean_taxonomy_title');
function rp_clean_taxonomy_title($title) {
  $title = str_replace('Mannschaften &#8211; ', '', $title);
  $title = str_replace('Mannschaften ', '', $title);
  return $title;
}

/*
 * Pruefe ob das Plugin ImagesCategories installiert ist
 * Wenn nicht: zeige message im Admin Bereich
 */
add_action('all_admin_notices', 'rp_eigene_admin_nachrichten');
function rp_eigene_admin_nachrichten() {
  if (get_query_var('post_type') === 'rp_spieler') {
    if (!function_exists('z_taxonomy_image_url')) { ?>
      <div class="error">
        <p>
          Das Plugin <a href="https://wordpress.org/plugins/categories-images/">Categories Images</a> ist nicht installiert! Bitte installiere es um Bilder für einzelne Mannschaften zu aktivieren
        </p>
      </div>
    <?php }
  }
}

/*
 * Lade das CSS fuer alle Seiten die was mit dem Custom Post Type zu tun haben
 */
add_action('admin_enqueue_scripts', 'rp_lade_css_spieler_etc');
add_action('wp_enqueue_scripts', 'rp_lade_css_spieler_etc');
function rp_lade_css_spieler_etc() {
  // If it's not the front page, stop executing code, ie. return
  if (get_post_type() !== 'rp_spieler') {
    return;
  }

  wp_enqueue_style('rp-spieler-stylesheet', plugins_url('css/rp_spieler.css', __FILE__));
}

?>

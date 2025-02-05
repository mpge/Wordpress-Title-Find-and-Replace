<?php
/*
Plugin Name: WP Title Find and Replace
Plugin URI: https://matthewpg.com/
Description: A plugin to find and replace a word in WordPress post titles and Yoast SEO meta titles, with a soft run feature.
Version: 1.1
Author: Matthew Gross
Author URI: https://matthewpg.com/
License: GNU GENERAL PUBLIC LICENSE (See LICENSE)
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Add settings page to the admin menu
add_action( 'admin_menu', 'wp_title_find_replace_menu' );

function wp_title_find_replace_menu() {
    add_options_page(
        'Title Find and Replace',
        'Title Find and Replace',
        'manage_options',
        'wp-title-find-replace-db',
        'wp_title_find_replace_settings_page'
    );
}

// Render the settings page
function wp_title_find_replace_settings_page() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'posts';
    $yoast_meta_key = '_yoast_wpseo_title';

    if ( isset( $_POST['wp_title_find_replace_run'] ) ) {
        check_admin_referer( 'wp_title_find_replace_nonce' );

        $find_word = sanitize_text_field( $_POST['find_word'] );
        $replace_word = sanitize_text_field( $_POST['replace_word'] );
        $exclude_word = sanitize_text_field( $_POST['exclude_word'] );
        $update_yoast = isset( $_POST['update_yoast'] );

        if ( ! empty( $find_word ) ) {
            // SQL condition to exclude posts containing the exclude_word
            $exclude_condition = ! empty( $exclude_word )
                ? "AND post_title NOT LIKE '%" . $wpdb->esc_like( $exclude_word ) . "%'"
                : '';

            // Perform a soft run if 'Soft Run' is selected
            if ( isset( $_POST['soft_run'] ) ) {
                $results = $wpdb->get_results( $wpdb->prepare(
                    "SELECT ID, post_title 
                     FROM $table_name 
                     WHERE post_type = 'post' 
                     AND post_title LIKE %s
                     $exclude_condition",
                    '%' . $wpdb->esc_like( $find_word ) . '%'
                ) );

                echo '<div class="notice notice-info"><p>Soft Run: Found ' . count( $results ) . ' titles containing "' . esc_html( $find_word ) . '" and excluding "' . esc_html( $exclude_word ) . '".</p></div>';

                if ( ! empty( $results ) ) {
                    echo '<h2>Preview of Changes:</h2>';
                    echo '<table class="widefat">';
                    echo '<thead><tr><th>Post ID</th><th>Current Title</th><th>Updated Title</th></tr></thead><tbody>';

                    foreach ( $results as $row ) {
                        $updated_title = str_replace( $find_word, $replace_word, $row->post_title );
                        echo '<tr>';
                        echo '<td>' . esc_html( $row->ID ) . '</td>';
                        echo '<td>' . esc_html( $row->post_title ) . '</td>';
                        echo '<td>' . esc_html( $updated_title ) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                }
            }

            // Perform the actual replacement if 'Run' is selected
            if ( ! isset( $_POST['soft_run'] ) ) {
                $updated_count = $wpdb->query( $wpdb->prepare(
                    "UPDATE $table_name 
                     SET post_title = REPLACE(post_title, %s, %s) 
                     WHERE post_type = 'post' 
                     AND post_title LIKE %s
                     $exclude_condition",
                    $find_word, $replace_word, '%' . $wpdb->esc_like( $find_word ) . '%'
                ) );

                echo '<div class="updated"><p>Updated ' . esc_html( $updated_count ) . ' post titles.</p></div>';

                // Update Yoast titles if selected
                if ( $update_yoast ) {
                    $yoast_results = $wpdb->get_results( $wpdb->prepare(
                        "SELECT post_id, meta_value 
                         FROM {$wpdb->prefix}postmeta 
                         WHERE meta_key = %s 
                         AND meta_value LIKE %s",
                        $yoast_meta_key, '%' . $wpdb->esc_like( $find_word ) . '%'
                    ) );

                    foreach ( $yoast_results as $row ) {
                        $updated_meta_title = str_replace( $find_word, $replace_word, $row->meta_value );
                        $wpdb->update(
                            "{$wpdb->prefix}postmeta",
                            [ 'meta_value' => $updated_meta_title ],
                            [ 'post_id' => $row->post_id, 'meta_key' => $yoast_meta_key ]
                        );
                    }

                    echo '<div class="updated"><p>Updated Yoast SEO meta titles.</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error"><p>Please provide a word to find.</p></div>';
        }
    }

    // Fetch current settings
    $find_word = isset( $_POST['find_word'] ) ? esc_attr( $_POST['find_word'] ) : '';
    $replace_word = isset( $_POST['replace_word'] ) ? esc_attr( $_POST['replace_word'] ) : '';
    $exclude_word = isset( $_POST['exclude_word'] ) ? esc_attr( $_POST['exclude_word'] ) : '';

    ?>
    <div class="wrap">
        <h1>Title Find and Replace</h1>
        <form method="post">
            <?php wp_nonce_field( 'wp_title_find_replace_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="find_word">Find Word</label>
                    </th>
                    <td>
                        <input type="text" id="find_word" name="find_word" value="<?php echo esc_attr( $find_word ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="replace_word">Replace With</label>
                    </th>
                    <td>
                        <input type="text" id="replace_word" name="replace_word" value="<?php echo esc_attr( $replace_word ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="exclude_word">Exclude Posts Containing</label>
                    </th>
                    <td>
                        <input type="text" id="exclude_word" name="exclude_word" value="<?php echo esc_attr( $exclude_word ); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>
                            <input type="checkbox" name="update_yoast" value="1"> Update Yoast SEO Meta Titles
                        </label>
                    </th>
                </tr>
            </table>
            <p>
                <label>
                    <input type="checkbox" name="soft_run" value="1" checked> Soft Run (Preview Changes Without Applying)
                </label>
            </p>
            <p class="submit">
                <button type="submit" name="wp_title_find_replace_run" class="button button-primary">Run</button>
            </p>
        </form>
    </div>
    <?php
}


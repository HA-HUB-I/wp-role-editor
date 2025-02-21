<?php
/**
 * Plugin Name: Dynamic Capabilities Manager
 * Description: Provides an admin page for dynamically viewing and updating role capabilities.
 * Version: 1.0
 * Author: Your Name
 * License: GPL2
 */

defined('ABSPATH') || exit;

/**
 * 1. Add a custom menu page in WP Admin
 */
add_action( 'admin_menu', 'dcm_add_admin_menu' );
function dcm_add_admin_menu() {
    // Only allow Administrators to see this page
    if ( current_user_can('administrator') ) {
        add_menu_page(
            'Capabilities Manager',
            'Capabilities Manager',
            'manage_options', // capability required to see this menu
            'dcm_capabilities',
            'dcm_render_capabilities_page',
            'dashicons-lock',
            90
        );
    }
}

/**
 * 2. Render the Capabilities Page with a Form
 */
function dcm_render_capabilities_page() {
    // Make sure only admins can access
    if ( ! current_user_can('administrator') ) {
        wp_die( 'You do not have permission to access this page.' );
    }

    // Handle form submission
    if ( isset($_POST['dcm_submit']) && check_admin_referer('dcm_update_caps', 'dcm_nonce') ) {
        dcm_process_form_submission( $_POST );
    }

    // Retrieve all roles
    global $wp_roles;
    $all_roles = $wp_roles->roles;
    // It's helpful to have a sorted list of all unique capabilities (across all roles)
    $all_capabilities = array();

    // Gather all capabilities from all roles
    foreach ( $all_roles as $role_key => $role_data ) {
        $caps = array_keys( $role_data['capabilities'] );
        foreach ( $caps as $cap ) {
            $all_capabilities[ $cap ] = $cap; // store in an assoc array to avoid duplicates
        }
    }

    // Sort capabilities by name
    asort( $all_capabilities );
    
    ?>
    <div class="wrap">
        <h1>Dynamic Capabilities Manager</h1>
        <p>Check or uncheck capabilities for each role. Click "Update Capabilities" to save.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('dcm_update_caps', 'dcm_nonce'); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Capability</th>
                        <?php foreach ( $all_roles as $role_key => $role_data ) : ?>
                            <th><?php echo esc_html( $role_data['name'] ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php 
                foreach ( $all_capabilities as $cap ) : 
                    echo '<tr>';
                    // Capability name in first column
                    echo '<td>' . esc_html( $cap ) . '</td>';

                    // For each role, show a checkbox
                    foreach ( $all_roles as $role_key => $role_data ) {
                        // Check if role currently has this capability
                        $role_object = get_role( $role_key );
                        $has_cap = $role_object ? $role_object->has_cap( $cap ) : false;

                        printf(
                            '<td style="text-align:center;">
                                <input type="checkbox" name="dcm_caps[%1$s][%2$s]" value="1" %3$s />
                            </td>',
                            esc_attr( $role_key ),  // dcm_caps[editor]
                            esc_attr( $cap ),       // [edit_posts]
                            checked( $has_cap, true, false )
                        );
                    }

                    echo '</tr>';
                endforeach; 
                ?>
                </tbody>
            </table>
            <p>
                <input type="submit" name="dcm_submit" class="button button-primary" 
                    value="Update Capabilities" />
            </p>
        </form>
    </div>
    <?php
}

/**
 * 3. Process the Form Submission
 *    - Compare posted checkboxes with existing role capabilities
 *    - Add or remove capabilities accordingly
 */
function dcm_process_form_submission( $form_data ) {
    if ( empty( $form_data['dcm_caps'] ) ) {
        // No capabilities toggled on at all? 
        // It's possible user unchecks everything, but let's just handle the scenario:
        $submitted_caps = array();
    } else {
        // This will be an array with structure:
        // $form_data['dcm_caps'] = [
        //    'administrator' => [ 'edit_posts' => '1', 'delete_posts' => '1', ... ],
        //    'editor'        => [ 'edit_posts' => '1', 'delete_posts' => '0', ... ],
        //    ...
        // ]
        $submitted_caps = $form_data['dcm_caps'];
    }

    // Retrieve all roles
    global $wp_roles;
    $all_roles = $wp_roles->roles;

    // For each role, we need to set the correct caps
    foreach ( $all_roles as $role_key => $role_data ) {
        $role_object = get_role( $role_key );
        if ( ! $role_object ) {
            continue;
        }

        // Build a set of capabilities from the form for this role
        $role_submitted_caps = isset( $submitted_caps[ $role_key ] )
            ? $submitted_caps[ $role_key ]
            : array();

        // Gather all capabilities across WP to do add/remove
        // (Alternatively, we can read from $role_data['capabilities'] 
        // but that might skip new capabilities from other roles.)
        $all_role_caps = array_keys( $role_data['capabilities'] );

        // We also want to handle capabilities that the role might not have,
        // but the form can still check/uncheck them. Let's build a superset
        // from the entire $submitted_caps array if we want a comprehensive approach.
        // For simplicity, let's focus only on the capabilities that appear in the form.
        $global_cap_list = [];
        foreach ($submitted_caps as $r_key => $caps_array) {
            foreach ($caps_array as $cap_key => $val) {
                $global_cap_list[ $cap_key ] = true;
            }
        }
        // Now $global_cap_list has all caps that were rendered in the table

        // For each capability in the global list, check if user wants it on or off
        foreach ( $global_cap_list as $cap => $_ ) {
            $wants_cap = isset( $role_submitted_caps[ $cap ] ) && $role_submitted_caps[ $cap ] == '1';

            // Does role currently have it?
            $role_has_cap = $role_object->has_cap( $cap );

            // If role should have it, but doesn't, add it
            if ( $wants_cap && ! $role_has_cap ) {
                $role_object->add_cap( $cap );
            }
            // If role should NOT have it, but does, remove it
            if ( ! $wants_cap && $role_has_cap ) {
                $role_object->remove_cap( $cap );
            }
        }
    }

    // Optional: you can display an admin notice after updating
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible">
            <p>Capabilities updated successfully.</p>
        </div>';
    });
}

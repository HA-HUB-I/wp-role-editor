<?php
/**
 * Plugin Name: Dynamic Role & UI Manager + Export/Import
 * Description: Dynamically manage role capabilities, hide select admin UI elements, and export/import settings.
 * Version: 2.0
 * Author: Gencloud
 * License: GPL2
 */

defined('ABSPATH') || exit;

/**
 * --------------------------------------------------------
 * SECTION 1: Add the main Admin Menu Page
 * --------------------------------------------------------
 */
add_action( 'admin_menu', 'drm_add_admin_menu' );
function drm_add_admin_menu() {
    // Restrict to administrators
    if ( current_user_can( 'administrator' ) ) {
        add_menu_page(
            'Dynamic Manager',
            'Dynamic Manager',
            'manage_options',
            'drm_main_menu',
            'drm_render_main_page',
            'dashicons-admin-generic',
            90
        );
    }
}

/**
 * --------------------------------------------------------
 * SECTION 2: Render the Main Admin Page
 * --------------------------------------------------------
 */
function drm_render_main_page() {
    // Must be admin
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'You do not have permission to access this page.' );
    }

    // Handle form submissions for capabilities and UI sections
    if ( isset($_POST['drm_submit_caps']) && check_admin_referer('drm_update_caps', 'drm_caps_nonce') ) {
        drm_process_caps_form( $_POST );
    }
    if ( isset($_POST['drm_submit_ui']) && check_admin_referer('drm_update_ui', 'drm_ui_nonce') ) {
        drm_process_ui_form( $_POST );
    }

    // Handle import if posted
    if ( isset($_POST['drm_import_submit']) && check_admin_referer('drm_import_settings', 'drm_import_nonce') ) {
        drm_process_import_form( $_POST );
    }

    ?>
    <div class="wrap">
        <h1>Dynamic Role & UI Manager</h1>
        <p>Manage role capabilities, hide certain admin UI elements, and export/import these settings.</p>

        <hr />

        <?php drm_render_caps_section(); ?>

        <hr />

        <?php drm_render_ui_section(); ?>

        <hr />

        <?php drm_render_export_import_section(); ?>

    </div>
    <?php
}

/**
 * --------------------------------------------------------
 * SECTION 3: CAPABILITIES MANAGER
 * (Same structure as previous example)
 * --------------------------------------------------------
 */
function drm_render_caps_section() {
    // Grab global roles
    global $wp_roles;
    $all_roles = $wp_roles->roles;

    // Collect all capabilities across all roles
    $all_capabilities = array();
    foreach ( $all_roles as $role_key => $role_data ) {
        $caps = array_keys( $role_data['capabilities'] );
        foreach ( $caps as $cap ) {
            $all_capabilities[ $cap ] = $cap;
        }
    }
    asort( $all_capabilities );
    ?>
    <h2>Role Capabilities</h2>
    <form method="post" action="">
        <?php wp_nonce_field('drm_update_caps', 'drm_caps_nonce'); ?>

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
            <?php foreach ( $all_capabilities as $cap ) : ?>
                <tr>
                    <td><?php echo esc_html( $cap ); ?></td>
                    <?php foreach ( $all_roles as $role_key => $role_data ) : 
                        $role_object = get_role( $role_key );
                        $has_cap = $role_object ? $role_object->has_cap( $cap ) : false;
                        ?>
                        <td style="text-align:center;">
                            <input type="checkbox" name="drm_caps[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap); ?>]" 
                                value="1" <?php checked( $has_cap, true ); ?> />
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <input type="submit" name="drm_submit_caps" class="button button-primary" value="Update Capabilities">
        </p>
    </form>
    <?php
}

function drm_process_caps_form( $form_data ) {
    if ( empty( $form_data['drm_caps'] ) ) {
        $submitted_caps = array();
    } else {
        $submitted_caps = $form_data['drm_caps'];
    }

    global $wp_roles;
    $all_roles = $wp_roles->roles;

    // Build a global list of capabilities from the posted data
    $global_cap_list = array();
    foreach ( $submitted_caps as $role_key => $caps_array ) {
        foreach ( $caps_array as $cap_key => $val ) {
            $global_cap_list[ $cap_key ] = true;
        }
    }

    // For each role, add/remove capabilities
    foreach ( $all_roles as $role_key => $role_data ) {
        $role_object = get_role( $role_key );
        if ( ! $role_object ) continue;

        // Which caps were submitted for this role?
        $role_submitted_caps = isset( $submitted_caps[ $role_key ] )
            ? $submitted_caps[ $role_key ]
            : array();

        // Add or remove each capability
        foreach ( $global_cap_list as $cap => $_ ) {
            $wants_cap = ! empty( $role_submitted_caps[ $cap ] ); // is the checkbox checked?
            $role_has_cap = $role_object->has_cap( $cap );

            if ( $wants_cap && ! $role_has_cap ) {
                $role_object->add_cap( $cap );
            } elseif ( ! $wants_cap && $role_has_cap ) {
                $role_object->remove_cap( $cap );
            }
        }
    }

    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>Capabilities updated successfully.</p></div>';
    } );
}

/**
 * --------------------------------------------------------
 * SECTION 4: ADMIN UI ELEMENTS MANAGER
 * - Hide Menus, Meta Boxes, or Entire UI Elements
 * --------------------------------------------------------
 */

function drm_get_hideable_ui_items() {
    return array(
        'dashboard_menu' => array(
            'label' => 'Hide Dashboard Menu (index.php)',
            'type'  => 'menu_page',
            'menu_slug' => 'index.php',
        ),
        'slider_revolution_metabox' => array(
            'label'   => 'Hide Slider Revolution Metabox',
            'type'    => 'meta_box',
            'box_id'  => 'slider_revolution_metabox',
            'screens' => array('post','page'),
            'context' => 'normal'
        ),
        'mfn_meta_page' => array(
            'label'   => 'Hide BeTheme Metabox (mfn-meta-page)',
            'type'    => 'meta_box',
            'box_id'  => 'mfn-meta-page',
            'screens' => array('page'),
            'context' => 'normal'
        ),
        'postbox_header' => array(
            'label'   => 'Hide Postbox Header (all .postbox-header via CSS)',
            'type'    => 'css',
            'css'     => '.postbox-header { display: none !important; }'
        ),
        'all_postbox_containers' => array(
            'label'   => 'Hide All Postboxes (via CSS)',
            'type'    => 'css',
            'css'     => '#poststuff .postbox { display: none !important; }'
        ),
    );
}

function drm_render_ui_section() {
    global $wp_roles;
    $all_roles = $wp_roles->roles;

    $hideable_items = drm_get_hideable_ui_items();
    $hide_settings = get_option( 'drm_ui_hide_settings', array() );
    ?>
    <h2>Admin UI Elements</h2>
    <p>Select which elements to hide from each role. (Note: This only hides UI, not real security.)</p>

    <form method="post" action="">
        <?php wp_nonce_field('drm_update_ui', 'drm_ui_nonce'); ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>UI Element</th>
                    <?php foreach ( $all_roles as $role_key => $role_data ) : ?>
                        <th style="text-align:center;"><?php echo esc_html( $role_data['name'] ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $hideable_items as $item_key => $item_data ) : ?>
                <tr>
                    <td><?php echo esc_html( $item_data['label'] ); ?></td>
                    <?php foreach ( $all_roles as $role_key => $role_data ) :
                        $is_hidden_for_role = ! empty( $hide_settings[ $item_key ][ $role_key ] );
                        ?>
                        <td style="text-align:center;">
                            <input type="checkbox" 
                                   name="drm_ui[<?php echo esc_attr($item_key); ?>][<?php echo esc_attr($role_key); ?>]" 
                                   value="1" 
                                   <?php checked( $is_hidden_for_role, true ); ?> />
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <input type="submit" name="drm_submit_ui" class="button button-primary" value="Update UI Hiding">
        </p>
    </form>
    <?php
}

function drm_process_ui_form( $form_data ) {
    $submitted_ui = ! empty( $form_data['drm_ui'] ) ? $form_data['drm_ui'] : array();
    update_option( 'drm_ui_hide_settings', $submitted_ui );

    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-success is-dismissible"><p>Admin UI hiding updated successfully.</p></div>';
    } );
}

/**
 * Apply the Hiding Logic
 */
add_action( 'admin_menu', 'drm_maybe_hide_menu_pages', 999 );
function drm_maybe_hide_menu_pages() {
    if ( is_super_admin() ) {
        return;
    }

    $current_user = wp_get_current_user();
    $user_roles   = (array) $current_user->roles;
    $hide_settings = get_option( 'drm_ui_hide_settings', array() );
    if ( empty($hide_settings) ) return;

    $items = drm_get_hideable_ui_items();
    foreach ( $items as $key => $data ) {
        if ( isset($data['type']) && 'menu_page' === $data['type'] && ! empty($data['menu_slug']) ) {
            foreach ( $user_roles as $role_key ) {
                if ( ! empty( $hide_settings[ $key ][ $role_key ] ) ) {
                    remove_menu_page( $data['menu_slug'] );
                }
            }
        }
    }
}

add_action( 'add_meta_boxes', 'drm_maybe_remove_meta_boxes', 999 );
function drm_maybe_remove_meta_boxes() {
    if ( is_super_admin() ) {
        return;
    }

    $current_user = wp_get_current_user();
    $user_roles   = (array) $current_user->roles;
    $hide_settings = get_option( 'drm_ui_hide_settings', array() );

    $items = drm_get_hideable_ui_items();
    foreach ( $items as $key => $data ) {
        if ( isset($data['type']) && 'meta_box' === $data['type'] ) {
            foreach ( $user_roles as $role_key ) {
                if ( ! empty( $hide_settings[ $key ][ $role_key ] ) ) {
                    $box_id  = $data['box_id'];
                    $screens = ! empty($data['screens']) ? $data['screens'] : array('post','page');
                    $context = ! empty($data['context']) ? $data['context'] : 'normal';
                    foreach ( $screens as $screen ) {
                        remove_meta_box( $box_id, $screen, $context );
                    }
                }
            }
        }
    }
}

add_action( 'admin_head', 'drm_maybe_inject_css_hiding' );
function drm_maybe_inject_css_hiding() {
    if ( is_super_admin() ) {
        return;
    }

    $current_user = wp_get_current_user();
    $user_roles   = (array) $current_user->roles;
    $hide_settings = get_option( 'drm_ui_hide_settings', array() );
    $items = drm_get_hideable_ui_items();
    $css_to_hide = array();

    foreach ( $items as $key => $data ) {
        if ( isset($data['type']) && 'css' === $data['type'] && ! empty($data['css']) ) {
            foreach ( $user_roles as $role_key ) {
                if ( ! empty( $hide_settings[$key][$role_key] ) ) {
                    $css_to_hide[] = $data['css'];
                    break; 
                }
            }
        }
    }

    if ( ! empty($css_to_hide) ) {
        echo '<style type="text/css">' . implode( "\n", $css_to_hide ) . '</style>';
    }
}

/**
 * --------------------------------------------------------
 * SECTION 5: EXPORT / IMPORT
 * --------------------------------------------------------
 */

/**
 * 5.1: Render Export / Import Section on the same admin page
 */
function drm_render_export_import_section() {
    ?>
    <h2>Export / Import Settings</h2>

    <div style="display:flex; gap:40px;">
        <!-- Export Box -->
        <div style="flex:1; min-width:300px;">
            <h3>Export</h3>
            <p>Click the button below to generate a JSON export of your roles’ capabilities and UI hide settings. Copy &amp; paste it somewhere safe or into another site’s import box.</p>
            <form method="post" action="">
                <?php wp_nonce_field('drm_export_settings', 'drm_export_nonce'); ?>
                <p><input type="submit" name="drm_export_submit" class="button button-primary" value="Generate Export" /></p>
            </form>

            <?php
            // If user clicked export, show JSON text area
            if ( isset($_POST['drm_export_submit']) && check_admin_referer('drm_export_settings', 'drm_export_nonce') ) {
                $export_data = drm_build_export_data();
                $json        = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
                ?>
                <textarea style="width:100%; height:300px;"><?php echo esc_textarea($json); ?></textarea>
                <?php
            }
            ?>
        </div>

        <!-- Import Box -->
        <div style="flex:1; min-width:300px;">
            <h3>Import</h3>
            <p>Paste your JSON export below, then click <strong>Import</strong>. You can choose whether to apply all roles or only selected roles.</p>
            <form method="post" action="">
                <?php wp_nonce_field('drm_import_settings', 'drm_import_nonce'); ?>

                <textarea name="drm_import_json" style="width:100%; height:200px;"></textarea>

                <h4>Which roles to apply?</h4>
                <label>
                    <input type="radio" name="drm_import_roles" value="all" checked />
                    All roles (as in the JSON)
                </label>
                <br />
                <label>
                    <input type="radio" name="drm_import_roles" value="choose" />
                    Choose roles to import
                </label>
                <div id="drm-choose-roles-container" style="margin-left:20px; margin-top:5px; display:none;">
                    <?php
                    // Show checkboxes for each role
                    global $wp_roles;
                    foreach ( $wp_roles->roles as $role_key => $role_data ) {
                        echo '<label style="display:block;margin-bottom:4px;">';
                        echo '<input type="checkbox" name="drm_import_roles_array[]" value="' . esc_attr($role_key) . '"> ';
                        echo esc_html($role_data['name']);
                        echo '</label>';
                    }
                    ?>
                </div>

                <p><input type="submit" name="drm_import_submit" class="button button-primary" value="Import Settings" /></p>
            </form>
        </div>
    </div>

    <script>
    // Simple JS to toggle role checkboxes if user chooses "Choose roles"
    document.addEventListener('DOMContentLoaded', function(){
        var radios = document.querySelectorAll('input[name="drm_import_roles"]');
        var container = document.getElementById('drm-choose-roles-container');
        function toggleChooseRoles() {
            var val = document.querySelector('input[name="drm_import_roles"]:checked').value;
            container.style.display = (val === 'choose') ? 'block' : 'none';
        }
        radios.forEach(function(radio){
            radio.addEventListener('change', toggleChooseRoles);
        });
    });
    </script>
    <?php
}

/**
 * 5.2: Build an array of all plugin-managed settings (capabilities + UI hides).
 */
function drm_build_export_data() {
    // 1) Gather role capabilities
    global $wp_roles;
    $roles_caps_data = array(); // e.g. [ 'administrator' => ['edit_posts'=>1, ...], 'editor'=>[...] ]

    foreach ( $wp_roles->roles as $role_key => $role_data ) {
        $role_obj = get_role($role_key);
        if ( ! $role_obj ) {
            continue;
        }
        $capabilities = $role_obj->capabilities; // array of cap => bool
        // We only care about capabilities that are true
        // to keep the file smaller and clearer
        $true_caps = array();
        foreach ( $capabilities as $cap => $enabled ) {
            if ( $enabled ) {
                $true_caps[$cap] = true;
            }
        }
        $roles_caps_data[$role_key] = $true_caps;
    }

    // 2) Gather UI hide settings
    $ui_hide_settings = get_option( 'drm_ui_hide_settings', array() );

    // Return combined data
    return array(
        'plugin_version' => '1.0',
        'generated_at'   => gmdate('c'),
        'capabilities'   => $roles_caps_data,
        'ui_hiding'      => $ui_hide_settings,
    );
}

/**
 * 5.3: Process Import
 */
function drm_process_import_form( $form_data ) {
    $json_raw = isset($form_data['drm_import_json']) ? trim($form_data['drm_import_json']) : '';
    if ( empty($json_raw) ) {
        add_action( 'admin_notices', function(){
            echo '<div class="notice notice-error is-dismissible"><p>No JSON provided for import.</p></div>';
        });
        return;
    }

    // Decode JSON
    $decoded = json_decode( $json_raw, true );
    if ( ! is_array($decoded) || ! isset($decoded['capabilities']) || ! isset($decoded['ui_hiding']) ) {
        add_action( 'admin_notices', function(){
            echo '<div class="notice notice-error is-dismissible"><p>Invalid JSON structure.</p></div>';
        });
        return;
    }

    // Determine which roles to apply
    $roles_mode = isset($form_data['drm_import_roles']) ? $form_data['drm_import_roles'] : 'all';
    $roles_to_import = array();
    if ( 'all' === $roles_mode ) {
        // Use all from JSON
        $roles_to_import = array_keys( $decoded['capabilities'] );
    } elseif ( 'choose' === $roles_mode ) {
        // Use only those selected in the checkboxes
        if ( ! empty($form_data['drm_import_roles_array']) && is_array($form_data['drm_import_roles_array']) ) {
            $roles_to_import = $form_data['drm_import_roles_array'];
        }
    }

    // 1) Apply capabilities for chosen roles
    global $wp_roles;
    foreach ( $decoded['capabilities'] as $role_key => $caps_array ) {
        if ( ! in_array( $role_key, $roles_to_import, true ) ) {
            // skip this role if not chosen
            continue;
        }
        $role_obj = get_role($role_key);
        if ( ! $role_obj ) {
            // Role doesn't exist here, skip or optionally create
            continue;
        }
        // Remove all existing caps from the role first? Not always desired. 
        // For a pure "sync," you might want to remove all, then add. 
        // But we’ll just do a loop of add_cap for the ones in the import.
        
        // Option A: Clear existing role’s caps (except built-in?). 
        // For demonstration, we skip that. We'll just add or remove to match the JSON.

        // Build a list of all caps in the role right now
        $current_caps = array_keys( $role_obj->capabilities );

        // Remove any that are not in the imported array
        foreach ( $current_caps as $existing_cap ) {
            if ( ! isset($caps_array[$existing_cap]) ) {
                $role_obj->remove_cap($existing_cap);
            }
        }

        // Add all from the imported array
        foreach ( $caps_array as $cap_name => $true_val ) {
            if ( $true_val ) {
                $role_obj->add_cap( $cap_name );
            }
        }
    }

    // 2) Apply UI hiding for chosen roles
    // The array is structured like:
    // [ui_key => [role_key => 1, role_key2 => 1], ui_key2 => [...]]
    $existing_ui = get_option('drm_ui_hide_settings', array());
    $imported_ui = $decoded['ui_hiding'];

    // We want to **merge** these in a way that only updates the chosen roles
    foreach ( $imported_ui as $ui_item => $roles_map ) {
        if ( ! is_array($roles_map) ) {
            continue;
        }
        foreach ( $roles_map as $role_key => $val ) {
            if ( in_array($role_key, $roles_to_import, true) ) {
                // set it
                $existing_ui[$ui_item][$role_key] = $val;
            }
        }
    }

    update_option( 'drm_ui_hide_settings', $existing_ui );

    add_action( 'admin_notices', function(){
        echo '<div class="notice notice-success is-dismissible"><p>Import completed successfully.</p></div>';
    });
}



## **How It Works**

**Two Sections in One Plugin**

  

 1. Capabilities: The first half (Sections 1-3) replicates the dynamic
    capabilities manager approach.
    
 2. Admin UI Elements: The second half (Sections 4-5) adds toggles to
        hide certain items from roles.

    

 - [ ] Hideable Items Array (drm_get_hideable_ui_items())

  

## We define a small set of known UI targets:

 - Menu Pages (dashboard_menu) => uses remove_menu_page().
   
   
 - Meta Boxes (slider_revolution_metabox, mfn-meta-page) => uses   
   remove_meta_box().
 - CSS (e.g. hiding .postbox-header) => uses an injected `<style>` block  
   to hide them.

 - You can add or remove items from this array to suit your needs.
 - Storing “Hide” Settings

  

**For each “hideable item”, we provide a row of checkboxes for each    role.**

 - If checked, we store a 1 in drm_ui_hide_settings[ITEM][ROLE].

On each admin page load, we read these settings and conditionally remove or hide the specified items for the user’s role.

Multiple Hooks

  

admin_menu (priority 999) to remove main menu pages.

add_meta_boxes (priority 999) to remove meta boxes.

admin_head to insert CSS for any leftover UI elements not easily removed by a function.

## Extensibility

  

You can easily expand the $hideable_items list for more meta boxes, plugin pages, or CSS selectors.

For some plugins, you might need a different remove_submenu_page() or custom logic if they register admin pages differently.

## Security Reminder

  

Removing or hiding UI elements is not a security measure by itself. You must also remove the relevant capabilities if you want to prevent direct access (e.g. a user guessing or typing in an admin URL).

  
  

# How the Export/Import Works

## Export

  

When you click Generate Export, the plugin:

Collects all roles and each role’s capabilities (only the ones set to true).

Reads the drm_ui_hide_settings option (which tracks hidden UI elements).

Combines these into an array and converts it to JSON.

It displays a <textarea> with the JSON. You can copy that text and paste it into a file or directly into another site’s import box.

## Import

  

You paste the JSON into the Import area on another site’s admin.

You choose All roles or Selected roles to apply.

The plugin then parses the JSON:

For each role found in capabilities, we apply those capabilities on the target site only if that role exists. (We skip roles that don’t exist, but you could optionally create them.)

We merge the UI hide settings so that only the chosen roles get updated.

Finally, a notice shows “Import completed successfully.”

Partial Role Import

  

If you only want to apply the settings for the “Editor” and “Author” roles, select “Choose roles,” then check those boxes.

The plugin will skip the other roles in the JSON.

## Merging vs. Overwriting

  

Capabilities: We remove any existing caps that aren’t in the imported list, then add any that are. This effectively synchronizes the local role to the imported role’s set of capabilities.

UI Hides: We merge the imported UI hide entries for the selected roles into the existing drm_ui_hide_settings. This means only the roles you picked get updated for each UI item.
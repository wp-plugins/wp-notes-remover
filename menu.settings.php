<?php
$settings_key = $WebWeb_WP_NotesRemover_obj->get('plugin_settings_key');
$opts = $WebWeb_WP_NotesRemover_obj->get_options();

?>
<div class="WebWeb_WP_NotesRemover">
    <div class="wrap">
        <div class="main_content">
            <h2>Settings</h2>

            <form method="post" action="options.php">
                <?php settings_fields($WebWeb_WP_NotesRemover_obj->get('plugin_dir_name')); ?>
                <div class="message">
                    <p><?php echo $WebWeb_WP_NotesRemover_obj->msg("The plugin is active and running.
                        No additional action is required on your part.", 1); ?>
                    </p>
                </div>
            </form>
        </div> <!-- /main_content -->

        <?php include_once(dirname(__FILE__) . '/zzz_admin_sidebar.php'); ?>
    </div> <!-- /wrap -->
</div>
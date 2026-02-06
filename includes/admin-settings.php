<?php
if (!defined('ABSPATH')) exit;

// 1. Settings API (FFmpeg Path)
add_action('admin_init', function () {
    register_setting('wpffmpeg_options', 'wpffmpeg_ffmpeg_path');
    
    add_settings_section(
        'wpffmpeg_main',
        'FFmpeg Settings',
        function() {
            echo '<p>FFmpeg binary path (leave empty for auto-detect via PATH)</p>';
        },
        'wpffmpeg'
    );
    
    add_settings_field(
        'wpffmpeg_ffmpeg_path',
        'FFmpeg Path',
        function() {
            $val = esc_attr(get_option('wpffmpeg_ffmpeg_path', ''));
            echo "<input type='text' name='wpffmpeg_ffmpeg_path' value='$val' style='width:400px;' placeholder='/usr/bin/ffmpeg or C:\\ffmpeg\\bin\\ffmpeg.exe' />";
        },
        'wpffmpeg',
        'wpffmpeg_main'
    );
});

// 2. Main Settings Page + Status
add_action('admin_menu', function() {
    add_options_page(
        'WP FFmpeg Thumbnail',
        'FFmpeg Thumbnail',
        'manage_options',
        'wpffmpeg',
        function() {
            ?>
            <div class="wrap">
                <h1>🎬 WP FFmpeg Thumbnail Generator</h1>
                <?php
                // Show bulk success message
                if($notice = get_transient('wpffmpeg_bulk_notice')) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . $notice . '</p></div>';
                    delete_transient('wpffmpeg_bulk_notice');
                }
                ?>

                <!-- SETTINGS FORM (FFmpeg Path) -->
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wpffmpeg_options');
                    do_settings_sections('wpffmpeg');
                    submit_button('Save FFmpeg Path');
                    ?>
                </form>
                <!-- STATUS COUNTER + BULK BUTTON -->
                <h2>✅ Videos Status</h2>
                <?php
                $total = count(get_posts([
                    'post_type' => 'attachment',
                    'post_mime_type' => ['video/mp4','video/avi','video/mov','video/mkv'],
                    'numberposts' => -1,
                    'post_status' => 'inherit'
                ]));

                $processed = 0;
                $has_thumbnails = 0;
                foreach(get_posts([
                    'post_type' => 'attachment',
                    'post_mime_type' => ['video/mp4','video/avi','video/mkv'],
                    'numberposts' => -1,
                    'post_status' => 'inherit'
                ]) as $video) {
                    if(get_post_meta($video->ID, '_wpffmpeg_thumb_id', true)) {
                        $processed++;
                        $thumb_id = get_post_meta($video->ID, '_wpffmpeg_thumb_id', true);
                        if(get_attached_file($thumb_id)) $has_thumbnails++;
                    }
                }

                echo "<p><strong>Database: $processed/$total | Files: $has_thumbnails/$total</strong></p>";

                if($processed < $total) {
                    echo '<p><span class="notice notice-warning">⚠️ Missing Database Entries</span></p>';
                    echo '<p><a href="'.admin_url('admin.php?page=wpffmpeg&bulk_generate=1').'" class="button button-primary">🔄 Generate Missing ('.($total-$processed).' needed)</a></p>';
                } elseif($has_thumbnails < $total) {
                    echo '<p><span class="notice notice-warning">⚠️ Missing Thumbnail Files (FFmpeg issue)</span></p>';
                    echo '<p><a href="'.admin_url('admin.php?page=wpffmpeg&bulk_generate=1').'" class="button button-primary">🔄 Regenerate Files ('.($total-$has_thumbnails).' missing)</a></p>';
                } else {
                    echo '<p class="notice notice-success">✅ All videos have thumbnails!</p>';
                }
                ?>

            </div>
            <?php
        }
    );
});

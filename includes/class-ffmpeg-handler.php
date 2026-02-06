<?php
class WPFFmpeg_Handler {
    
    public function init() {
        // Check FFmpeg on activation
        register_activation_hook(WPFMPEG_PATH . 'wp-ffmpeg-thumbnail-generator.php', [$this, 'check_ffmpeg']);
        
        // Auto thumbnail on video upload
        add_filter('wp_generate_attachment_metadata', [$this, 'generate_thumbnail'], 10, 2);
        
        // Admin menu
       // add_action('admin_menu', [$this, 'admin_menu']);
           add_action('admin_init', [$this, 'handle_bulk_generate']);

    }
    
    public function check_ffmpeg() {
        if (!$this->is_ffmpeg_installed()) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>FFmpeg not found! Install FFmpeg on server.</p></div>';
            });
        }
    }
    
    public function get_ffmpeg_binary() {
        $option = trim(get_option('wpffmpeg_ffmpeg_path', ''));
        if (!empty($option)) {
            // If it's an absolute path and exists and is executable, use it
            if (file_exists($option) && is_executable($option)) {
                return $option;
            }
            // Try appending typical bin path on Windows or Unix
            if (DIRECTORY_SEPARATOR === '\\') {
                $alt = rtrim($option, '\\/') . '\\bin\\ffmpeg.exe';
                if (file_exists($alt)) return $alt;
            } else {
                $alt = rtrim($option, '\\/') . '/bin/ffmpeg';
                if (file_exists($alt) && is_executable($alt)) return $alt;
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows common locations
            $candidates = [
                'C:\\ffmpeg\\bin\\ffmpeg.exe',
                'C:\\ffmpeg\\ffmpeg.exe'
            ];

            foreach ($candidates as $cmd) {
                if (file_exists($cmd)) return $cmd;
            }

            // Check PATH using 'where'
            exec('where ffmpeg 2>&1', $output, $return_var);
            if ($return_var === 0 && !empty($output)) {
                return trim($output[0]);
            }
        } else {
            // Unix-like common locations
            $candidates = [
                '/usr/bin/ffmpeg',
                '/usr/local/bin/ffmpeg',
                '/snap/bin/ffmpeg',
                '/opt/ffmpeg/bin/ffmpeg'
            ];

            foreach ($candidates as $cmd) {
                if (file_exists($cmd) && is_executable($cmd)) return $cmd;
            }

            // Check PATH using 'command -v'
            exec('command -v ffmpeg 2>&1', $output, $return_var);
            if ($return_var === 0 && !empty($output)) {
                return trim($output[0]);
            }
        }

        // Fallback: try running ffmpeg directly (PATH)
        exec('ffmpeg -version 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            return 'ffmpeg';
        }

        return '';
    }

    public function is_ffmpeg_installed() {
        $bin = $this->get_ffmpeg_binary();
        return !empty($bin);
    }

    public function generate_thumbnail($metadata, $attachment_id) {
        $file = get_attached_file($attachment_id);
        $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, ['mp4', 'avi', 'mov', 'mkv'])) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $thumb_path = $upload_dir['path'] . '/' . $attachment_id . '_thumb.jpg';

        $bin = $this->get_ffmpeg_binary();
        if (empty($bin)) {
            return $metadata;
        }
        
        // FFmpeg command using configured binary
        $command = sprintf(
            '%s -ss 00:00:15 -i %s -vf thumbnail -frames:v 1 %s -y',
            escapeshellarg($bin),
            escapeshellarg($file),
            escapeshellarg($thumb_path)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($thumb_path)) {
            // Set as featured image
            $thumb_id = wp_insert_attachment([
                'guid' => $upload_dir['url'] . '/' . $attachment_id . '_thumb.jpg',
                'post_mime_type' => 'image/jpeg',
                'post_title' => get_the_title($attachment_id),
                'post_content' => '',
                'post_status' => 'inherit'
            ], $thumb_path, $attachment_id);
            
            $thumb_meta = wp_generate_attachment_metadata($thumb_id, $thumb_path);
            wp_update_attachment_metadata($thumb_id, $thumb_meta);
            
            update_post_meta($attachment_id, '_wpffmpeg_thumb_id', $thumb_id);
        }
        
        return $metadata;
    }
    
    public function settings_page() {
        echo '<div class="wrap">';
        echo '<h1>WP FFmpeg Thumbnail Generator</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('wpffmpeg_options');
        do_settings_sections('wpffmpeg');
        submit_button();
        echo '</form>';

        echo '<h2>Status</h2>';
        if ($this->is_ffmpeg_installed()) {
            echo '<p style="color:green;">✅ FFmpeg is installed! Binary: ' . esc_html($this->get_ffmpeg_binary()) . '</p>';
        } else {
            echo '<p style="color:red;">❌ FFmpeg not found! Please install FFmpeg or set the correct path.</p>';
        }

        echo '</div>';
    }
        // Bulk generate function add करें (class के अंदर)
       public function handle_bulk_generate() {
            if (!isset($_GET['bulk_generate'])) return;
            
            $videos = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => ['video/mp4','video/avi','video/mov','video/mkv'],
                'numberposts' => -1,
                'post_status' => 'inherit'
            ]);
            
            $processed = 0;
            foreach($videos as $video) {
                // 1. पुराना meta + physical file दोनों delete
                $old_thumb_id = get_post_meta($video->ID, '_wpffmpeg_thumb_id', true);
                if($old_thumb_id) {
                    wp_delete_attachment($old_thumb_id, true); // true = files भी delete
                    delete_post_meta($video->ID, '_wpffmpeg_thumb_id');
                }
                
                // 2. नया thumbnail generate
                $file = get_attached_file($video->ID);
                $thumb_path = wp_upload_dir()['path'] . '/' . $video->ID . '_ffmpeg_thumb.jpg';
                
                $command = sprintf('ffmpeg -ss 00:00:15 -i "%s" -vf thumbnail -frames:v 1 "%s" -y', $file, $thumb_path);
                exec($command, $output, $return_var);
                
                // 3. Success check + attachment बनाएं
                if(file_exists($thumb_path)) {
                    $thumb_id = wp_insert_attachment([
                        'guid' => wp_upload_dir()['url'] . '/' . $video->ID . '_ffmpeg_thumb.jpg',
                        'post_mime_type' => 'image/jpeg',
                        'post_title' => 'Thumb-' . $video->ID,
                        'post_status' => 'inherit'
                    ], $thumb_path, $video->ID);
                    
                    $thumb_meta = wp_generate_attachment_metadata($thumb_id, $thumb_path);
                    wp_update_attachment_metadata($thumb_id, $thumb_meta);
                    update_post_meta($video->ID, '_wpffmpeg_thumb_id', $thumb_id);
                    $processed++;
                }
            }
            
            add_action('admin_notices', function() use ($processed) {
                echo '<div class="notice notice-success is-dismissible"><p>✅ CLEAN: ' . $processed . ' thumbnails generated!</p></div>';
            });
            wp_redirect(admin_url('admin.php?page=wpffmpeg'));
            exit;
        }

}

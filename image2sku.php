<?php
/**
 * Plugin Name: Image2SKU
 * Plugin URI:  https://github.com/islandsvefir/image2sku
 * Description: Automatically associates uploaded product images with their corresponding SKUs in a WordPress e-commerce site. Features include a drag-and-drop interface, image previews, CSV report generation, and progress tracking.
 * Version:     2.0.0
 * Author:      Islandsvefir
 * Author URI:  https://islandsvefir.is
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image2sku
 * Domain Path: /languages
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if WooCommerce is active
 */
function image2sku_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'image2sku_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function image2sku_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('Image2SKU requires WooCommerce to be installed and active.', 'image2sku'); ?></p>
    </div>
    <?php
}

/**
 * Enqueue scripts and styles
 */
function image2sku_enqueue_dependencies($hook)
{
    if ('toplevel_page_image2sku' != $hook) {
        return;
    }

    // Enqueue Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
    
    // Enqueue Bootstrap CSS and JS
    wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), '4.5.2', true);

    // Enqueue custom JS and CSS
    wp_enqueue_script('image2sku-js', plugin_dir_url(__FILE__) . 'js/image2sku.js', array('jquery'), '2.0.0', true);
    wp_enqueue_style('image2sku-css', plugin_dir_url(__FILE__) . 'css/image2sku.css', array(), '2.0.0');

    // Localize script
    wp_localize_script('image2sku-js', 'image2sku_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('image2sku_upload_images_nonce'),
        'max_file_size' => wp_max_upload_size(),
        'max_file_size_mb' => size_format(wp_max_upload_size()),
    ));
}

add_action('admin_enqueue_scripts', 'image2sku_enqueue_dependencies');

/**
 * Create admin page
 */
function image2sku_create_admin_menu()
{
    add_menu_page('Image2SKU', 'Image2SKU', 'manage_options', 'image2sku', 'image2sku_admin_page');
}

add_action('admin_menu', 'image2sku_create_admin_menu');

/**
 * Admin page content
 */
function image2sku_admin_page()
{
    if (!image2sku_check_woocommerce()) {
        return;
    }
    ?>
    <div class="wrap image2sku-container">
        <!-- Header -->
        <div class="image2sku-header">
            <h1>
                <i class="fas fa-images"></i>
                Image2SKU
                <span class="plugin-version">v2.0.0</span>
            </h1>
        </div>

        <!-- Upload Card -->
        <div class="image2sku-card">
            <div class="image2sku-card-header">
                <i class="fas fa-cloud-upload-alt"></i>
                Upload Product Images
            </div>
            
            <form id="image2sku-form" enctype="multipart/form-data">
                <input type="file" name="images[]" id="image2sku-file-input" multiple accept="image/*" style="display:none;">
                
                <!-- Drag & Drop Area -->
                <div id="image2sku-drag-drop" class="image2sku-drag-drop">
                    <div class="drag-drop-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="drag-drop-text">
                        Drag & Drop Images Here
                    </div>
                    <div class="drag-drop-hint">
                        or <strong>click to browse</strong>
                    </div>
                    <div class="drag-drop-hint" style="margin-top: 12px;">
                        <i class="fas fa-info-circle"></i> Name your files with product SKUs (e.g., ABC123.jpg)
                    </div>
                </div>

                <!-- Image Previews -->
                <div id="image2sku-previews" class="image2sku-previews" style="display:none;">
                    <div class="image2sku-previews-header">
                        <i class="fas fa-images"></i>
                        <span id="image2sku-preview-count">0 images selected</span>
                    </div>
                    <div id="image2sku-previews-grid" class="image2sku-previews-grid"></div>
                </div>

                <!-- Options -->
                <div class="image2sku-options" style="margin-top: 20px; padding: 20px; background: var(--gray-50); border-radius: 8px;">
                    <label class="image2sku-option-label" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; cursor: pointer;">
                        <input type="checkbox" name="image2sku_auto_upload" id="image2sku_auto_upload" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--gray-700); font-size: 14px;">
                            <i class="fas fa-bolt" style="color: var(--warning);"></i>
                            Start upload automatically when images are added
                        </span>
                    </label>

                    <label class="image2sku-option-label" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; cursor: pointer;">
                        <input type="checkbox" name="image2sku_enable_rename" id="image2sku_enable_rename" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--gray-700); font-size: 14px;">
                            <i class="fas fa-edit" style="color: var(--primary);"></i>
                            Enable renaming of images if SKU not found
                        </span>
                    </label>

                    <label class="image2sku-option-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="image2sku_handle_conflicts" id="image2sku_handle_conflicts" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--gray-700); font-size: 14px;">
                            <i class="fas fa-exchange-alt" style="color: var(--danger);"></i>
                            If product has an image, let me decide which one to use
                        </span>
                    </label>
                </div>

                <!-- Progress -->
                <div id="image2sku-progress-wrapper" class="progress-wrapper">
                    <progress id="image2sku-progress" value="0" max="100"></progress>
                    <div id="image2sku-progress-text" class="progress-text">Preparing upload...</div>
                </div>

                <!-- Submit Buttons -->
                <div class="image2sku-btn-group">
                    <button type="submit" class="image2sku-btn image2sku-btn-primary">
                        <i class="fas fa-upload"></i>
                        <span>Upload Images</span>
                    </button>
                    <button type="button" id="image2sku-clear-button" class="image2sku-btn image2sku-btn-secondary">
                        <i class="fas fa-times"></i>
                        <span>Clear Images</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div id="image2sku-results" class="image2sku-results"></div>

        <!-- Action Buttons -->
        <div id="image2sku-actions" class="image2sku-btn-group" style="display:none;">
            <button id="image2sku-download-report" class="image2sku-btn image2sku-btn-secondary">
                <i class="fas fa-download"></i>
                Download CSV Report
            </button>
            <button id="image2sku-undo" class="image2sku-btn image2sku-btn-warning">
                <i class="fas fa-undo"></i>
                Undo Last Upload
            </button>
        </div>
    </div>
    <?php
}

/**
 * Get detailed upload error message
 *
 * @param int $error_code PHP upload error code
 * @return string Human-readable error message
 */
function image2sku_get_upload_error_message($error_code) {
    $upload_errors = array(
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded - please try again',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension',
    );
    
    return isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Unknown upload error (code: ' . $error_code . ')';
}

/**
 * Validate filename for security and compatibility
 *
 * @param string $filename Original filename
 * @return array Array with 'valid' (bool) and 'message' (string)
 */
function image2sku_validate_filename($filename) {
    // Check for empty filename
    if (empty($filename)) {
        return array('valid' => false, 'message' => 'Filename is empty');
    }
    
    // Check for dangerous characters
    if (preg_match('/[<>:"\/\\|?*\x00-\x1F]/', $filename)) {
        return array('valid' => false, 'message' => 'Filename contains invalid characters');
    }
    
    // Check filename length (most filesystems have 255 char limit)
    if (strlen($filename) > 255) {
        return array('valid' => false, 'message' => 'Filename is too long (max 255 characters)');
    }
    
    // Check for file extension
    if (!preg_match('/\.[^.]+$/', $filename)) {
        return array('valid' => false, 'message' => 'File has no extension');
    }
    
    return array('valid' => true, 'message' => 'Valid filename');
}

/**
 * Validate SKU format
 *
 * @param string $sku SKU to validate
 * @return array Array with 'valid' (bool) and 'message' (string)
 */
function image2sku_validate_sku($sku) {
    $sku = trim($sku);
    
    if (empty($sku)) {
        return array('valid' => false, 'message' => 'SKU is empty');
    }
    
    if (strlen($sku) > 100) {
        return array('valid' => false, 'message' => 'SKU is too long (max 100 characters)');
    }
    
    // Check for suspicious characters that might indicate parsing issues
    if (preg_match('/[<>"\'\x00-\x1F]/', $sku)) {
        return array('valid' => false, 'message' => 'SKU contains invalid characters');
    }
    
    return array('valid' => true, 'message' => 'Valid SKU');
}

/**
 * Process image upload and attach to product
 *
 * @param array $uploaded_images Files array from $_FILES
 * @param int $index Index of current file
 * @param int $product_id WooCommerce product ID
 * @return array Array with 'success' (bool), 'attachment_id' (int|null), 'message' (string)
 */
function process_image_upload($uploaded_images, $index, $product_id)
{
    // Validate file upload error
    if (isset($uploaded_images['error'][$index]) && $uploaded_images['error'][$index] !== UPLOAD_ERR_OK) {
        $error_msg = image2sku_get_upload_error_message($uploaded_images['error'][$index]);
        error_log('Image2SKU: Upload error for file at index ' . $index . ': ' . $error_msg);
        return array('success' => false, 'attachment_id' => null, 'message' => $error_msg);
    }
    // Get original filename for validation
    $original_filename = $uploaded_images['name'][$index];
    
    // Validate filename
    $filename_check = image2sku_validate_filename($original_filename);
    if (!$filename_check['valid']) {
        error_log('Image2SKU: Invalid filename: ' . $filename_check['message']);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Invalid filename: ' . $filename_check['message']);
    }
    
    $filename = sanitize_file_name($original_filename);
    $filetmp = $uploaded_images['tmp_name'][$index];
    $filesize = $uploaded_images['size'][$index];
    
    // Validate file exists and is readable
    if (!file_exists($filetmp) || !is_readable($filetmp)) {
        error_log('Image2SKU: Cannot read uploaded file ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Cannot read uploaded file - please try again');
    }
    
    // Validate file size
    $max_size = wp_max_upload_size();
    if ($filesize > $max_size) {
        $error_msg = 'File too large: ' . size_format($filesize) . ' (max: ' . size_format($max_size) . ')';
        error_log('Image2SKU: ' . $error_msg . ' for ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => $error_msg);
    }
    
    // Check for zero-byte files
    if ($filesize === 0) {
        error_log('Image2SKU: Zero-byte file: ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => 'File is empty (0 bytes)');
    }
    
    // Validate file type
    $filetype = wp_check_filetype(basename($filename), null);
    if (!$filetype['ext'] || !$filetype['type']) {
        error_log('Image2SKU: Invalid/unknown file type for ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Invalid or unknown file type');
    }
    
    // Validate it's an image
    $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
    if (!in_array($filetype['type'], $allowed_types)) {
        error_log('Image2SKU: Unsupported image type: ' . $filetype['type'] . ' for ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Unsupported image type: ' . $filetype['type'] . ' (allowed: JPG, PNG, GIF, WebP)');
    }
    
    // Validate actual image content (check for corrupted files)
    $image_info = @getimagesize($filetmp);
    if ($image_info === false) {
        error_log('Image2SKU: Corrupted or invalid image file: ' . $filename);
        return array('success' => false, 'attachment_id' => null, 'message' => 'File appears to be corrupted or is not a valid image');
    }
    
    // Check image dimensions
    if ($image_info[0] < 50 || $image_info[1] < 50) {
        error_log('Image2SKU: Image too small: ' . $filename . ' (' . $image_info[0] . 'x' . $image_info[1] . ')');
        return array('success' => false, 'attachment_id' => null, 'message' => 'Image too small: ' . $image_info[0] . 'x' . $image_info[1] . 'px (minimum 50x50px)');
    }

    // Upload the image to the WordPress media library
    $upload = wp_upload_bits($filename, null, file_get_contents($filetmp));
    if ($upload['error']) {
        error_log('Image2SKU: Upload error for ' . $filename . ': ' . $upload['error']);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Upload failed: ' . $upload['error']);
    }

    // Create an attachment for the uploaded image
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
        'post_content' => '',
        'post_status' => 'inherit',
    );

    $attach_id = wp_insert_attachment($attachment, $upload['file'], $product_id);
    if (!$attach_id || is_wp_error($attach_id)) {
        $error_msg = is_wp_error($attach_id) ? $attach_id->get_error_message() : 'Unknown error creating attachment';
        error_log('Image2SKU: Failed to create attachment for ' . $filename . ': ' . $error_msg);
        return array('success' => false, 'attachment_id' => null, 'message' => 'Failed to create attachment: ' . $error_msg);
    }

    // Include the image.php file for the wp_generate_attachment_metadata() function
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Generate metadata and update the attachment
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    if (is_wp_error($attach_data)) {
        error_log('Image2SKU: Failed to generate metadata for ' . $filename);
        // Continue anyway, metadata failure shouldn't stop the upload
    } else {
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    // Get the current product's featured image
    $featured_image_id = get_post_thumbnail_id($product_id);

    if (!$featured_image_id) {
        // Set the uploaded image as the featured image if the product doesn't have one
        $result = set_post_thumbnail($product_id, $attach_id);
        if (!$result) {
            error_log('Image2SKU: Failed to set featured image for product ' . $product_id);
            return array('success' => false, 'attachment_id' => $attach_id, 'message' => 'Image uploaded but failed to set as featured image');
        }
    } else {
        // Add the uploaded image as an additional image if the product already has a featured image
        $product_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_ids = !empty($product_gallery) ? explode(',', $product_gallery) : array();
        
        // Prevent duplicate images in gallery
        if (in_array($attach_id, $gallery_ids)) {
            error_log('Image2SKU: Image already exists in gallery for product ' . $product_id);
            wp_delete_attachment($attach_id, true);
            return array('success' => false, 'attachment_id' => null, 'message' => 'This image is already in the product gallery');
        }
        
        $gallery_ids[] = $attach_id;
        $product_gallery = implode(',', $gallery_ids);
        update_post_meta($product_id, '_product_image_gallery', $product_gallery);
    }

    return array('success' => true, 'attachment_id' => $attach_id, 'message' => 'Image uploaded successfully');
}


/**
 * AJAX handler for image uploads
 */
function image2sku_upload_images_callback()
{
    check_ajax_referer('image2sku_upload_images_nonce', 'security');
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }
    
    // Check WooCommerce is active
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce is not active.');
        return;
    }

    $uploaded_images = isset($_FILES['images']) ? $_FILES['images'] : null;

    // Get options from POST data
    $rename_enabled = isset($_POST['rename_enabled']) && $_POST['rename_enabled'] === 'true';
    $handle_conflicts = isset($_POST['handle_conflicts']) && $_POST['handle_conflicts'] === 'true';

    // Placeholder results array
    $results = array();
    $images_to_rename = array();
    $images_with_conflicts = array();

    if ($uploaded_images) {
        // Loop through each uploaded image
        for ($i = 0; $i < count($uploaded_images['name']); $i++) {
            // Skip empty uploads
            if (empty($uploaded_images['name'][$i]) || $uploaded_images['error'][$i] !== UPLOAD_ERR_OK) {
                $results[] = array(
                    'filename' => isset($uploaded_images['name'][$i]) ? $uploaded_images['name'][$i] : 'Unknown',
                    'status' => 'error',
                    'message' => 'File upload error: ' . (isset($uploaded_images['error'][$i]) ? $uploaded_images['error'][$i] : 'unknown'),
                );
                continue;
            }
            
            // Perform server-side error checking and processing here
            $filename = sanitize_file_name($uploaded_images['name'][$i]);
            $image_sku = sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)); // Get SKU from image filename (without extension)
            
            // Validate SKU format
            $sku_validation = image2sku_validate_sku($image_sku);
            if (!$sku_validation['valid']) {
                $results[] = array(
                    'filename' => $filename,
                    'status' => 'invalid',
                    'message' => 'Invalid SKU format: ' . $sku_validation['message'],
                );
                continue;
            }
            
            $product_id = wc_get_product_id_by_sku($image_sku);
            
            // If exact SKU match not found, try removing trailing numbers (for variants/additional images)
            if (!$product_id) {
                $pattern = '/(-)?\d+$/';
                $nameWithoutIncrement = preg_replace($pattern, '', $image_sku);
                $product_id = wc_get_product_id_by_sku($nameWithoutIncrement);
            }
            
            if ($product_id) {
                // Check if we need to handle conflicts (product already has featured image)
                $existing_featured = get_post_thumbnail_id($product_id);
                $pattern = '/(-)?\d+$/';
                $is_variant = preg_match($pattern, $image_sku);
                
                if ($existing_featured && !$is_variant && $handle_conflicts) {
                    // Collect data for conflict resolution
                    $product = wc_get_product($product_id);
                    $images_with_conflicts[] = array(
                        'index' => $i,
                        'filename' => $filename,
                        'product_id' => $product_id,
                        'product_name' => $product->get_name(),
                        'existing_image_url' => wp_get_attachment_url($existing_featured),
                    );
                    continue;
                }
                
                // Process image, upload to the media library, and set as featured or gallery image
                $upload_result = process_image_upload($uploaded_images, $i, $product_id);
                
                if ($upload_result['success']) {
                    $product = wc_get_product($product_id);
                    // Check if this became the featured image or was added to gallery
                    $featured_image_id = get_post_thumbnail_id($product_id);
                    $is_featured = ($featured_image_id === $upload_result['attachment_id']);
                    
                    $results[] = array(
                        'name' => $product->get_name(),
                        'image' => $product->get_image(),
                        'filename' => $filename,
                        'status' => 'success',
                        'message' => $is_featured ? 'Image set as featured' : 'Image added to gallery',
                        'link' => $product->get_permalink(),
                        'attachment_id' => $upload_result['attachment_id'],
                        'product_id' => $product_id,
                        'is_featured' => $is_featured,
                    );
                } else {
                    $results[] = array(
                        'filename' => $filename,
                        'status' => 'error',
                        'message' => $upload_result['message'],
                    );
                }
            } else {
                if ($rename_enabled) {
                    // Collect data for renaming
                    $images_to_rename[] = array(
                        'index' => $i,
                        'filename' => $filename,
                        'sku' => $image_sku,
                    );
                } else {
                    $results[] = array(
                        'filename' => $filename,
                        'status' => 'invalid',
                        'message' => 'No product found with SKU: ' . esc_html($image_sku),
                    );
                }
            }

        }
    } else {
        wp_send_json_error('No images were uploaded.');
        return;
    }
    wp_send_json_success(array(
        'results' => $results,
        'images_to_rename' => $images_to_rename,
        'images_with_conflicts' => $images_with_conflicts,
    ));

}

add_action('wp_ajax_image2sku_upload_images', 'image2sku_upload_images_callback');

/**
 * AJAX handler for undo functionality
 */
function image2sku_undo_uploads_callback()
{
    check_ajax_referer('image2sku_upload_images_nonce', 'security');
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }
    
    $undo_data = isset($_POST['undo_data']) ? json_decode(stripslashes($_POST['undo_data']), true) : array();
    
    if (empty($undo_data)) {
        wp_send_json_error('No undo data provided.');
        return;
    }
    
    $undone = 0;
    $errors = 0;
    
    foreach ($undo_data as $item) {
        if (empty($item['attachment_id']) || empty($item['product_id']) || !isset($item['is_featured'])) {
            $errors++;
            continue;
        }
        
        $attachment_id = intval($item['attachment_id']);
        $product_id = intval($item['product_id']);
        $is_featured = (bool) $item['is_featured'];
        
        // Delete the attachment from WordPress media library
        $deleted = wp_delete_attachment($attachment_id, true);
        
        if ($deleted) {
            // If it was a featured image, clear the featured image
            if ($is_featured) {
                delete_post_thumbnail($product_id);
            } else {
                // If it was a gallery image, remove it from the gallery
                $product_gallery = get_post_meta($product_id, '_product_image_gallery', true);
                if (!empty($product_gallery)) {
                    $gallery_ids = explode(',', $product_gallery);
                    $gallery_ids = array_diff($gallery_ids, array($attachment_id));
                    
                    if (!empty($gallery_ids)) {
                        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                    } else {
                        delete_post_meta($product_id, '_product_image_gallery');
                    }
                }
            }
            
            $undone++;
        } else {
            error_log('Image2SKU Undo: Failed to delete attachment ' . $attachment_id);
            $errors++;
        }
    }
    
    wp_send_json_success(array(
        'undone' => $undone,
        'errors' => $errors,
        'message' => sprintf(
            'Successfully undone %d upload(s)%s',
            $undone,
            $errors > 0 ? ' with ' . $errors . ' error(s)' : ''
        ),
    ));
}

add_action('wp_ajax_image2sku_undo_uploads', 'image2sku_undo_uploads_callback');

/**
 * AJAX handler for renaming images
 */
function image2sku_rename_images_callback()
{
    check_ajax_referer('image2sku_upload_images_nonce', 'security');
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }
    
    $rename_data = isset($_POST['rename_data']) ? json_decode(stripslashes($_POST['rename_data']), true) : array();
    $uploaded_images = isset($_FILES['images']) ? $_FILES['images'] : null;
    
    if (empty($rename_data) || !$uploaded_images) {
        wp_send_json_error('No rename data provided.');
        return;
    }
    
    $results = array();
    
    foreach ($rename_data as $item) {
        $index = intval($item['index']);
        $new_sku = sanitize_text_field($item['new_sku']);
        
        if (empty($new_sku)) {
            $results[] = array(
                'filename' => $uploaded_images['name'][$index],
                'status' => 'error',
                'message' => 'No SKU provided for renaming',
            );
            continue;
        }
        
        $product_id = wc_get_product_id_by_sku($new_sku);
        if (!$product_id) {
            $results[] = array(
                'filename' => $uploaded_images['name'][$index],
                'status' => 'invalid',
                'message' => 'No product found with SKU: ' . esc_html($new_sku),
            );
            continue;
        }
        
        // Process the image
        $upload_result = process_image_upload($uploaded_images, $index, $product_id);
        
        if ($upload_result['success']) {
            $product = wc_get_product($product_id);
            $featured_image_id = get_post_thumbnail_id($product_id);
            $is_featured = ($featured_image_id === $upload_result['attachment_id']);
            
            $results[] = array(
                'name' => $product->get_name(),
                'image' => $product->get_image(),
                'filename' => $uploaded_images['name'][$index],
                'status' => 'success',
                'message' => $is_featured ? 'Image set as featured (renamed from ' . $uploaded_images['name'][$index] . ')' : 'Image added to gallery (renamed)',
                'link' => $product->get_permalink(),
            );
        } else {
            $results[] = array(
                'filename' => $uploaded_images['name'][$index],
                'status' => 'error',
                'message' => $upload_result['message'],
            );
        }
    }
    
    wp_send_json_success(array('results' => $results));
}

add_action('wp_ajax_image2sku_rename_images', 'image2sku_rename_images_callback');

/**
 * AJAX handler for resolving image conflicts
 */
function image2sku_resolve_conflicts_callback()
{
    check_ajax_referer('image2sku_upload_images_nonce', 'security');
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('You do not have permission to perform this action.');
        return;
    }
    
    $conflict_data = isset($_POST['conflict_data']) ? json_decode(stripslashes($_POST['conflict_data']), true) : array();
    $uploaded_images = isset($_FILES['images']) ? $_FILES['images'] : null;
    
    if (empty($conflict_data) || !$uploaded_images) {
        wp_send_json_error('No conflict data provided.');
        return;
    }
    
    $results = array();
    
    foreach ($conflict_data as $item) {
        $index = intval($item['index']);
        $product_id = intval($item['product_id']);
        $choice = sanitize_text_field($item['choice']);
        
        if ($choice === 'use_new') {
            // Delete existing featured image
            $existing_featured = get_post_thumbnail_id($product_id);
            if ($existing_featured) {
                wp_delete_attachment($existing_featured, true);
            }
            
            // Upload new image
            $upload_result = process_image_upload($uploaded_images, $index, $product_id);
            
            if ($upload_result['success']) {
                $product = wc_get_product($product_id);
                $results[] = array(
                    'name' => $product->get_name(),
                    'image' => $product->get_image(),
                    'filename' => $uploaded_images['name'][$index],
                    'status' => 'success',
                    'message' => 'Replaced existing featured image with new image',
                    'link' => $product->get_permalink(),
                );
            } else {
                $results[] = array(
                    'filename' => $uploaded_images['name'][$index],
                    'status' => 'error',
                    'message' => 'Failed to upload new image: ' . $upload_result['message'],
                );
            }
        } else {
            // Keep existing image
            $product = wc_get_product($product_id);
            $results[] = array(
                'name' => $product->get_name(),
                'image' => $product->get_image(),
                'filename' => $uploaded_images['name'][$index],
                'status' => 'skipped',
                'message' => 'Kept existing featured image',
                'link' => $product->get_permalink(),
            );
        }
    }
    
    wp_send_json_success(array('results' => $results));
}

add_action('wp_ajax_image2sku_resolve_conflicts', 'image2sku_resolve_conflicts_callback');

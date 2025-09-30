<?php
/**
 * Plugin Name: Image2SKU
 * Plugin URI:  https://github.com/islandsvefir/image2sku
 * Description: Automatically associates uploaded product images with their corresponding SKUs in a WordPress e-commerce site. Features include a drag-and-drop interface, image previews, CSV report generation, and progress tracking.
 * Version:     1.1.0
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
    wp_enqueue_script('image2sku-js', plugin_dir_url(__FILE__) . 'js/image2sku.js', array('jquery'), '1.1.0', true);
    wp_enqueue_style('image2sku-css', plugin_dir_url(__FILE__) . 'css/image2sku.css', array(), '1.1.0');

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
                <span class="plugin-version">v1.1.0</span>
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

                <!-- Progress -->
                <div id="image2sku-progress-wrapper" class="progress-wrapper">
                    <progress id="image2sku-progress" value="0" max="100"></progress>
                    <div id="image2sku-progress-text" class="progress-text">Preparing upload...</div>
                </div>

                <!-- Submit Button -->
                <div class="image2sku-btn-group">
                    <button type="submit" class="image2sku-btn image2sku-btn-primary">
                        <i class="fas fa-upload"></i>
                        <span>Upload Images</span>
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
 * Process image upload and attach to product
 *
 * @param array $uploaded_images Files array from $_FILES
 * @param int $index Index of current file
 * @param int $product_id WooCommerce product ID
 * @return int|false Attachment ID on success, false on failure
 */
function process_image_upload($uploaded_images, $index, $product_id)
{
    // Validate file upload error
    if (isset($uploaded_images['error'][$index]) && $uploaded_images['error'][$index] !== UPLOAD_ERR_OK) {
        error_log('Image2SKU: Upload error code ' . $uploaded_images['error'][$index] . ' for file at index ' . $index);
        return false;
    }
    $filename = sanitize_file_name($uploaded_images['name'][$index]);
    $filetmp = $uploaded_images['tmp_name'][$index];
    $filetype = wp_check_filetype(basename($filename), null);
    
    // Validate file size
    $filesize = $uploaded_images['size'][$index];
    if ($filesize > wp_max_upload_size()) {
        error_log('Image2SKU: File too large: ' . $filename . ' (' . size_format($filesize) . ')');
        return false;
    }
    
    // Validate file type
    if (!$filetype['ext'] || !$filetype['type']) {
        error_log('Image2SKU: Invalid file type for ' . $filename);
        return false;
    }
    
    // Validate it's an image
    if (strpos($filetype['type'], 'image/') !== 0) {
        error_log('Image2SKU: Not an image file: ' . $filename);
        return false;
    }

    // Validate file exists and is readable
    if (!file_exists($filetmp) || !is_readable($filetmp)) {
        error_log('Image2SKU: Cannot read uploaded file ' . $filename);
        return false;
    }

    // Upload the image to the WordPress media library
    $upload = wp_upload_bits($filename, null, file_get_contents($filetmp));
    if ($upload['error']) {
        error_log('Image2SKU: Upload error for ' . $filename . ': ' . $upload['error']);
        return false;
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
        return false;
    }

    // Include the image.php file for the wp_generate_attachment_metadata() function
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Generate metadata and update the attachment
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    // Get the current product's featured image
    $featured_image_id = get_post_thumbnail_id($product_id);

    if (!$featured_image_id) {
        // Set the uploaded image as the featured image if the product doesn't have one
        set_post_thumbnail($product_id, $attach_id);
    } else {
        // Add the uploaded image as an additional image if the product already has a featured image
        $product_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_ids = !empty($product_gallery) ? explode(',', $product_gallery) : array();
        
        // Prevent duplicate images in gallery
        if (!in_array($attach_id, $gallery_ids)) {
            $gallery_ids[] = $attach_id;
            $product_gallery = implode(',', $gallery_ids);
            update_post_meta($product_id, '_product_image_gallery', $product_gallery);
        }
    }

    return $attach_id;
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

    // Placeholder results array
    $results = array();

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
            $product_id = wc_get_product_id_by_sku($image_sku);
            
            // If exact SKU match not found, try removing trailing numbers (for variants/additional images)
            if (!$product_id) {
                $pattern = '/(-)?\d+$/';
                $nameWithoutIncrement = preg_replace($pattern, '', $image_sku);
                $product_id = wc_get_product_id_by_sku($nameWithoutIncrement);
            }
            
            if ($product_id) {
                // Process image, upload to the media library, and set as featured or gallery image
                $image_id = process_image_upload($uploaded_images, $i, $product_id);
                $product = wc_get_product($product_id);
                
                if ($image_id) {
                    // Check if this became the featured image or was added to gallery
                    $featured_image_id = get_post_thumbnail_id($product_id);
                    $is_featured = ($featured_image_id === $image_id);
                    
                    $results[] = array(
                        'name' => $product->get_name(),
                        'image' => $product->get_image(),
                        'filename' => $filename,
                        'status' => 'success',
                        'message' => $is_featured ? 'Image set as featured' : 'Image added to gallery',
                        'link' => $product->get_permalink(),
                        'attachment_id' => $image_id,
                        'product_id' => $product_id,
                        'is_featured' => $is_featured,
                    );
                } else {
                    $results[] = array(
                        'filename' => $filename,
                        'status' => 'error',
                        'message' => 'An error occurred while processing the image',
                    );
                }
            } else {
                $results[] = array(
                    'filename' => $filename,
                    'status' => 'invalid',
                    'message' => 'No product found with SKU: ' . esc_html($image_sku),
                );
            }

        }
    } else {
        wp_send_json_error('No images were uploaded.');
        return;
    }
    wp_send_json_success($results);

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

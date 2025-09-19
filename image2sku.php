<?php
/**
 * Plugin Name: Image2SKU
 * Plugin URI:  https://github.com/islandsvefir/image2sku
 * Description: Automatically associates uploaded product images with their corresponding SKUs in a WordPress e-commerce site. Features include a drag-and-drop interface, image previews, CSV report generation, and progress tracking.
 * Version:     1.0.0
 * Author:      Islandsvefir
 * Author URI:  https://islandsvefir.is
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: image2sku
 * Domain Path: /languages
 */

// Enqueue scripts and styles
function image2sku_enqueue_dependencies($hook)
{
    if ('toplevel_page_image2sku' != $hook) {
        return;
    }

    // Enqueue Bootstrap CSS and JS
    wp_enqueue_style('bootstrap-css', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), '4.5.2', true);

    // Enqueue custom JS and CSS
    wp_enqueue_script('image2sku-js', plugin_dir_url(__FILE__) . 'js/image2sku.js', array('jquery'), '1.0.0', true);
    wp_enqueue_style('image2sku-css', plugin_dir_url(__FILE__) . 'css/image2sku.css');

    // Localize script
    wp_localize_script('image2sku-js', 'image2sku_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('image2sku_upload_images_nonce'),
    ));
}

add_action('admin_enqueue_scripts', 'image2sku_enqueue_dependencies');

// Create admin page
function image2sku_create_admin_menu()
{
    add_menu_page('Image2SKU', 'Image2SKU', 'manage_options', 'image2sku', 'image2sku_admin_page');
}

add_action('admin_menu', 'image2sku_create_admin_menu');

// Admin page content
function image2sku_admin_page()
{
    ?>
    <div class="wrap">
        <h1>Image2SKU</h1>
        <form id="image2sku-form" enctype="multipart/form-data" class="mb-4">
            <input type="file" name="images[]" id="image2sku-file-input" multiple accept="image/*"
                   style="display:none;">
            <div id="image2sku-drag-drop" class="image2sku-drag-drop border border-primary p-3 rounded text-center">
                <p class="mb-0">Drag and drop images here, or click to select files.</p>
            </div>
            <div id="image2sku-previews" class="image2sku-previews my-4"></div>
            <progress id="image2sku-progress" value="0" max="100" class="w-100"></progress>
            <button type="submit" class="btn btn-primary mt-3">Upload Images</button>
        </form>
        <div id="image2sku-results" class="image2sku-results"></div>
        <button id="image2sku-download-report" class="btn btn-secondary" style="display:none;">Download CSV Report
        </button>
    </div>
    <?php
}

// Process image upload function (implement according to your requirements)
function process_image_upload($uploaded_images, $index, $product_id)
{
    $filename = $uploaded_images['name'][$index];
    $filetmp = $uploaded_images['tmp_name'][$index];
    $filetype = wp_check_filetype(basename($filename), null);

    // Validate file type
    if (!$filetype['ext'] || !$filetype['type']) {
        error_log('Image2SKU: Invalid file type for ' . $filename);
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


// AJAX
function image2sku_upload_images_callback()
{
    check_ajax_referer('image2sku_upload_images_nonce', 'security');

    $uploaded_images = isset($_FILES['images']) ? $_FILES['images'] : null;

    // Placeholder results array
    $results = array();

    if ($uploaded_images) {
        // Loop through each uploaded image
        for ($i = 0; $i < count($uploaded_images['name']); $i++) {
            // Perform server-side error checking and processing here
            $filename = $uploaded_images['name'][$i];
            $image_sku = pathinfo($filename, PATHINFO_FILENAME); // Get SKU from image filename (without extension)
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
                    $is_featured = ($featured_image_id == $image_id);
                    
                    $results[] = array(
                        'name' => $product->get_name(),
                        'image' => $product->get_image(),
                        'filename' => $filename,
                        'status' => 'success',
                        'message' => $is_featured ? 'Image set as featured' : 'Image added to gallery',
                        'link' => $product->get_permalink(),
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
                    'message' => 'No product found with SKU: ' . $image_sku,
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

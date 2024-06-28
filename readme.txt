=== Image2SKU ===
Contributors: islandsvefir
Tags: images, sku, e-commerce, product management, upload
Requires at least: 5.0
Tested up to: 5.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically associates uploaded product images with their corresponding SKUs in a WordPress e-commerce site.

== Description ==

The Image2SKU plugin simplifies the process of managing product images in your WordPress-powered e-commerce platform. With a user-friendly drag-and-drop interface, it allows you to quickly associate product images with their respective SKUs. The plugin supports image previews, CSV report generation for uploads, and a progress bar for tracking upload status. It is designed to enhance productivity and efficiency in managing your online store's inventory.

== Installation ==

1. Upload the `image2sku` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Access the Image2SKU interface from the WordPress admin dashboard to start using the plugin.

== Usage ==

1. Navigate to the Image2SKU page in the admin dashboard.
2. Drag and drop images into the upload area or click the area to select images from your device.
3. Review the image previews and confirm the uploads.
4. Click "Upload Images" to begin the association process.
5. View the upload results in the generated table and download the CSV report if needed.

== Frequently Asked Questions ==

= Can I upload multiple images at once? =

Yes, Image2SKU supports multiple file uploads. You can select multiple images or drag and drop them into the upload area.

= How is the SKU derived from the image? =

The plugin assumes the image filename (excluding the extension) is the SKU. Ensure your image filenames match the SKUs of your products.

= What happens if an SKU does not match any product? =

The plugin will mark such images as failed in the upload results, allowing you to identify and correct any issues.

== Screenshots ==

1. Drag-and-drop interface for uploading images.
2. Image previews before processing.
3. CSV report download button and upload progress bar.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial Release.

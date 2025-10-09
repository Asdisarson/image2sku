jQuery(document).ready(function ($) {
    let isUploading = false;
    let lastUploadData = []; // Store data for undo functionality
    const CHUNK_SIZE = 10; // Process 10 images at a time

    // Function to validate files
    function validateFiles(files) {
        const errors = [];
        const warnings = [];
        const maxSize = image2sku_vars.max_file_size || 10485760; // 10MB default
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const seenFilenames = {};

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check for empty files
            if (file.size === 0) {
                errors.push(`<strong>${file.name}</strong>: File is empty (0 bytes)`);
                continue;
            }
            
            // Check file type
            if (!allowedTypes.includes(file.type)) {
                errors.push(`<strong>${file.name}</strong>: Invalid file type "${file.type}". <br><small>✓ Allowed: JPG, PNG, GIF, WebP</small>`);
                continue;
            }
            
            // Check file size
            if (file.size > maxSize) {
                const fileSize = (file.size / 1048576).toFixed(2); // Convert to MB
                errors.push(`<strong>${file.name}</strong>: File too large (${fileSize}MB). <br><small>✓ Maximum: ${image2sku_vars.max_file_size_mb || '10MB'}</small>`);
                continue;
            }
            
            // Check for duplicate filenames
            if (seenFilenames[file.name]) {
                warnings.push(`<strong>${file.name}</strong>: Duplicate filename detected`);
            }
            seenFilenames[file.name] = true;
            
            // Validate filename
            if (file.name.length > 255) {
                errors.push(`<strong>${file.name}</strong>: Filename too long (${file.name.length} chars, max 255)`);
            }
            
            // Check for invalid characters in filename
            if (/[<>:"\/\\|?*\x00-\x1F]/.test(file.name)) {
                errors.push(`<strong>${file.name}</strong>: Filename contains invalid characters`);
            }
            
            // Check for file extension
            if (!/\.[^.]+$/.test(file.name)) {
                errors.push(`<strong>${file.name}</strong>: File has no extension`);
            }
            
            // Warn about suspicious SKU names
            const sku = file.name.replace(/\.[^.]+$/, ''); // Remove extension
            if (sku.length < 2) {
                warnings.push(`<strong>${file.name}</strong>: SKU seems very short (${sku.length} chars)`);
            }
            if (sku.length > 50) {
                warnings.push(`<strong>${file.name}</strong>: SKU seems unusually long (${sku.length} chars)`);
            }
        }

        return { errors, warnings };
    }

    // Function to display image previews
    function displayPreviews(files) {
        const previewsContainer = $('#image2sku-previews');
        const previewsGrid = $('#image2sku-previews-grid');
        const previewCount = $('#image2sku-preview-count');
        
        previewsGrid.empty();
        previewCount.text(`${files.length} image${files.length !== 1 ? 's' : ''} selected`);
        previewsContainer.fadeIn(300);

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function (e) {
                const wrapper = $('<div>').addClass('image-preview-wrapper');
                const img = $('<img>').attr('src', e.target.result);
                const overlay = $('<div>').addClass('image-preview-overlay');
                const name = $('<div>').addClass('image-preview-name').text(file.name);
                
                overlay.append(name);
                wrapper.append(img).append(overlay);
                previewsGrid.append(wrapper);
            };
            reader.readAsDataURL(file);
        }
        
        // Check if auto-upload is enabled
        if ($('#image2sku_auto_upload').is(':checked')) {
            setTimeout(function() {
                $('#image2sku-form').submit();
            }, 300);
        }
    }
    
    // Clear button functionality
    $('#image2sku-clear-button').on('click', function () {
        $('#image2sku-file-input').val('');
        $('#image2sku-previews').fadeOut(300, function() {
            $('#image2sku-previews-grid').empty();
        });
        $('.image2sku-alert').fadeOut(300, function() { $(this).remove(); });
    });

    // Function to generate a CSV report from the results
    function generateCSVReport(results) {
        let csv = 'Filename,Status,Message\n';

        results.forEach(result => {
            csv += `"${result.filename}","${result.status}","${result.message}"\n`;
        });

        return csv;
    }

    // Function to download a CSV report
    function downloadCSVReport(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Handle drag and drop events
    const dropZone = $('#image2sku-drag-drop');
    dropZone.on('dragover', function (e) {
        e.preventDefault();
        $(this).addClass('drag-over');
    });

    dropZone.on('dragleave', function () {
        $(this).removeClass('drag-over');
    });

    dropZone.on('drop', function (e) {
        e.preventDefault();
        $(this).removeClass('drag-over');

        const files = e.originalEvent.dataTransfer.files;
        
        // Remove previous alerts
        $('.image2sku-alert').remove();
        
        // Validate files
        const validation = validateFiles(files);
        
        // Show errors if any
        if (validation.errors.length > 0) {
            const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                '<i class="fas fa-exclamation-circle"></i><div><strong>Cannot upload:</strong><br>' + validation.errors.join('<br><br>') + '</div>'
            );
            $('.image2sku-card').prepend(errorMessage);
            return;
        }
        
        // Show warnings if any
        if (validation.warnings.length > 0) {
            const warningMessage = $('<div>').addClass('image2sku-alert image2sku-alert-warning').html(
                '<i class="fas fa-exclamation-triangle"></i><div><strong>Warnings:</strong><br>' + validation.warnings.join('<br><br>') + '</div>'
            );
            $('.image2sku-card').prepend(warningMessage);
        }
        
        $('#image2sku-file-input').prop('files', files);
        displayPreviews(files);
    });

    // Handle click event to open file dialog
    dropZone.on('click', function () {
        $('#image2sku-file-input').click();
    });

    // Handle file input change event to update previews
    $('#image2sku-file-input').on('change', function () {
        // Remove previous alerts
        $('.image2sku-alert').remove();
        
        if (this.files.length === 0) {
            $('#image2sku-previews').fadeOut(300);
            return;
        }
        
        // Validate files
        const validation = validateFiles(this.files);
        
        // Show errors if any
        if (validation.errors.length > 0) {
            const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                '<i class="fas fa-exclamation-circle"></i><div><strong>Cannot upload:</strong><br>' + validation.errors.join('<br><br>') + '</div>'
            );
            $('.image2sku-card').prepend(errorMessage);
            this.value = ''; // Clear the input
            return;
        }
        
        // Show warnings if any
        if (validation.warnings.length > 0) {
            const warningMessage = $('<div>').addClass('image2sku-alert image2sku-alert-warning').html(
                '<i class="fas fa-exclamation-triangle"></i><div><strong>Warnings:</strong><br>' + validation.warnings.join('<br><br>') + '<br><small>You can still proceed with the upload.</small></div>'
            );
            $('.image2sku-card').prepend(warningMessage);
        }
        
        displayPreviews(this.files);
    });

    // Function to upload files in chunks
    function uploadInChunks(files, startIndex, allResults, submitButton) {
        const endIndex = Math.min(startIndex + CHUNK_SIZE, files.length);
        const chunk = Array.from(files).slice(startIndex, endIndex);
        
        // Update progress text
        const progress = Math.round((startIndex / files.length) * 100);
        const progressWrapper = $('#image2sku-progress-wrapper');
        const progressText = $('#image2sku-progress-text');
        
        progressWrapper.addClass('active');
        submitButton.html('<span class="image2sku-spinner"></span> Uploading... (' + startIndex + '/' + files.length + ')');
        progressText.html('<i class="fas fa-sync fa-spin"></i> Processing images ' + startIndex + ' to ' + endIndex + ' of ' + files.length);
        $('#image2sku-progress').val(progress);
        
        // Create FormData for this chunk
        const formData = new FormData();
        formData.append('action', 'image2sku_upload_images');
        formData.append('security', image2sku_vars.nonce);
        formData.append('rename_enabled', $('#image2sku_enable_rename').is(':checked') ? 'true' : 'false');
        formData.append('handle_conflicts', $('#image2sku_handle_conflicts').is(':checked') ? 'true' : 'false');
        
        chunk.forEach(file => {
            formData.append('images[]', file);
        });

        $.ajax({
            url: image2sku_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Merge results
                    allResults = allResults.concat(response.data.results);
                    
                    // Store rename and conflict data from first chunk only
                    if (startIndex === 0) {
                        window.imagesToRename = response.data.images_to_rename || [];
                        window.imagesWithConflicts = response.data.images_with_conflicts || [];
                    }
                    
                    // Check if there are more files to upload
                    if (endIndex < files.length) {
                        // Upload next chunk
                        uploadInChunks(files, endIndex, allResults, submitButton);
                    } else {
                        // All uploads complete
                        isUploading = false;
                        submitButton.prop('disabled', false).html('<i class="fas fa-upload"></i> <span>Upload Images</span>');
                        $('#image2sku-progress').val(100);
                        $('#image2sku-progress-text').html('<i class="fas fa-check-circle"></i> Upload complete!');
                        
                        // Check if we need to handle renaming or conflicts
                        if (window.imagesToRename && window.imagesToRename.length > 0) {
                            showRenameModal(window.imagesToRename, files);
                        } else if (window.imagesWithConflicts && window.imagesWithConflicts.length > 0) {
                            showConflictModal(window.imagesWithConflicts, files);
                        } else {
                            displayResults(allResults);
                        }
                    }
                } else {
                    // Error in chunk upload
                    isUploading = false;
                    submitButton.prop('disabled', false).html('<i class="fas fa-upload"></i> <span>Upload Images</span>');
                    $('#image2sku-progress-wrapper').removeClass('active');
                    $('#image2sku-progress').val(0);
                    
                    const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                        '<i class="fas fa-exclamation-circle"></i><div>' + (response.data || 'An error occurred') + '</div>'
                    );
                    $('.image2sku-card').prepend(errorMessage);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                isUploading = false;
                submitButton.prop('disabled', false).html('<i class="fas fa-upload"></i> <span>Upload Images</span>');
                $('#image2sku-progress-wrapper').removeClass('active');
                $('#image2sku-progress').val(0);
                
                const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                    '<i class="fas fa-exclamation-circle"></i><div>An error occurred: ' + textStatus + ': ' + errorThrown + '</div>'
                );
                $('.image2sku-card').prepend(errorMessage);
            }
        });
    }
    
    // Function to display results
    function displayResults(results) {
        // Store successful uploads for undo
        lastUploadData = results.filter(r => r.status === 'success' && r.attachment_id);
        
        const successCount = results.filter(r => r.status === 'success').length;
        const errorCount = results.filter(r => r.status === 'error' || r.status === 'invalid').length;
        
        // Build stats cards
        let statsHTML = '<div class="image2sku-card"><div class="image2sku-stats">';
        statsHTML += `
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>${successCount}</h3>
                    <p>Successful</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-error">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>${errorCount}</h3>
                    <p>Failed</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-total">
                    <i class="fas fa-images"></i>
                </div>
                <div class="stat-content">
                    <h3>${results.length}</h3>
                    <p>Total Processed</p>
                </div>
            </div>
        `;
        statsHTML += '</div></div>';
        
        // Build results table
        let tableHTML = '<div class="image2sku-card">';
        tableHTML += '<div class="image2sku-card-header"><i class="fas fa-list-alt"></i> Upload Results</div>';
        tableHTML += '<table class="table table-striped">';
        tableHTML += '<thead><tr><th>Product</th><th>Image</th><th>Filename</th><th>Status</th><th>Message</th><th>Action</th></tr></thead><tbody>';

                    results.forEach(result => {
            const name = result.name || '-';
            const image = result.image || '<span style="color: var(--gray-400);">No image</span>';
            const link = result.link ? `<a href="${result.link}" target="_blank" class="image2sku-btn image2sku-btn-icon image2sku-btn-secondary" style="padding: 6px 12px; font-size: 12px;"><i class="fas fa-external-link-alt"></i></a>` : '-';
            
            let statusBadge = '';
            if (result.status === 'success') {
                statusBadge = '<span class="status-badge status-badge-success"><i class="fas fa-check"></i> Success</span>';
            } else if (result.status === 'error') {
                statusBadge = '<span class="status-badge status-badge-error"><i class="fas fa-times"></i> Error</span>';
            } else {
                statusBadge = '<span class="status-badge status-badge-invalid"><i class="fas fa-exclamation"></i> Invalid</span>';
            }
            
            tableHTML += `<tr>
                <td><strong>${name}</strong></td>
                <td>${image}</td>
                <td><code style="font-size: 12px; color: var(--gray-600);">${result.filename || 'Unknown'}</code></td>
                <td>${statusBadge}</td>
                <td>${result.message || ''}</td>
                <td>${link}</td>
    </tr>`;
                    });

        tableHTML += '</tbody></table></div>';
        
        $('#image2sku-results').html(statsHTML + tableHTML);
        
        // Clear the form
        $('#image2sku-file-input').val('');
        $('#image2sku-previews').fadeOut(300, function() {
            $(this).find('#image2sku-previews-grid').empty();
        });
        
        // Hide progress
        $('#image2sku-progress-wrapper').removeClass('active');
        
        // Show action buttons
        $('#image2sku-actions').fadeIn(300);
        
        // Bind download button
        $('#image2sku-download-report').off('click').on('click', function () {
                        const csv = generateCSVReport(results);
                        downloadCSVReport(csv, 'image2sku-report.csv');
        });
        
        // Show/hide undo button
        if (lastUploadData.length > 0) {
            $('#image2sku-undo').show();
        } else {
            $('#image2sku-undo').hide();
        }
        
        // Hide any previous error messages
        $('.image2sku-alert-error').fadeOut(300, function() { $(this).remove(); });
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#image2sku-results').offset().top - 100
        }, 500);
    }

    // Function to show rename modal
    function showRenameModal(imagesToRename, allFiles) {
        let modalHTML = '<div class="image2sku-card">';
        modalHTML += '<div class="image2sku-card-header"><i class="fas fa-edit"></i> Rename Images - SKU Not Found</div>';
        modalHTML += '<div style="padding: 20px;"><p style="margin-bottom: 20px;">The following images could not be matched to products. Enter the correct SKU for each image:</p>';
        modalHTML += '<form id="rename-form">';
        
        imagesToRename.forEach(item => {
            modalHTML += `<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px; padding: 15px; background: var(--gray-50); border-radius: 8px;">
                <div style="flex: 1;"><strong>${item.filename}</strong><br><small style="color: var(--gray-500);">Original SKU: ${item.sku}</small></div>
                <input type="text" class="rename-sku-input" data-index="${item.index}" placeholder="Enter correct SKU" 
                       style="padding: 8px 12px; border: 2px solid var(--gray-300); border-radius: 6px; flex: 1;">
            </div>`;
        });
        
        modalHTML += '<div style="margin-top: 20px;"><button type="submit" class="image2sku-btn image2sku-btn-primary"><i class="fas fa-check"></i> Apply Renames</button></div>';
        modalHTML += '</form></div></div>';
        
        $('#image2sku-results').html(modalHTML);
        
        $('#rename-form').on('submit', function(e) {
            e.preventDefault();
            const renameData = [];
            $('.rename-sku-input').each(function() {
                const newSKU = $(this).val().trim();
                if (newSKU) {
                    renameData.push({
                        index: parseInt($(this).data('index')),
                        new_sku: newSKU
                    });
                }
            });
            
            if (renameData.length === 0) {
                alert('Please enter at least one SKU');
                return;
            }
            
            processRenames(renameData, allFiles);
        });
    }
    
    // Function to process renames
    function processRenames(renameData, allFiles) {
        const formData = new FormData();
        formData.append('action', 'image2sku_rename_images');
        formData.append('security', image2sku_vars.nonce);
        formData.append('rename_data', JSON.stringify(renameData));
        
        renameData.forEach(item => {
            formData.append('images[]', allFiles[item.index]);
        });
        
        $.ajax({
            url: image2sku_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Check if there are conflicts to handle
                    if (window.imagesWithConflicts && window.imagesWithConflicts.length > 0) {
                        showConflictModal(window.imagesWithConflicts, allFiles);
                    } else {
                        displayResults(response.data.results);
                    }
                }
            }
        });
    }
    
    // Function to show conflict resolution modal
    function showConflictModal(imagesWithConflicts, allFiles) {
        let modalHTML = '<div class="image2sku-card">';
        modalHTML += '<div class="image2sku-card-header"><i class="fas fa-exchange-alt"></i> Resolve Image Conflicts</div>';
        modalHTML += '<div style="padding: 20px;"><p style="margin-bottom: 20px;">The following products already have featured images. Choose which image to use:</p>';
        modalHTML += '<form id="conflict-form">';
        
        imagesWithConflicts.forEach(item => {
            modalHTML += `<div style="margin-bottom: 25px; padding: 20px; background: var(--gray-50); border-radius: 8px;">
                <h4 style="margin-bottom: 15px;">${item.product_name}</h4>
                <div style="display: flex; gap: 20px; align-items: center;">
                    <div style="flex: 1; text-align: center;">
                        <p style="margin-bottom: 10px; font-weight: 600;">Existing Image</p>
                        <img src="${item.existing_image_url}" style="max-width: 150px; max-height: 150px; border-radius: 8px; box-shadow: var(--shadow);">
                        <br><label style="margin-top: 10px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="choice_${item.index}" value="keep_existing" style="width: 18px; height: 18px;">
                            <span>Keep This</span>
                        </label>
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <p style="margin-bottom: 10px; font-weight: 600;">New Image</p>
                        <p style="padding: 60px 20px; background: white; border: 2px dashed var(--gray-300); border-radius: 8px;">${item.filename}</p>
                        <label style="margin-top: 10px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="radio" name="choice_${item.index}" value="use_new" checked style="width: 18px; height: 18px;">
                            <span>Use This</span>
                        </label>
                    </div>
                </div>
            </div>`;
        });
        
        modalHTML += '<div style="margin-top: 20px;"><button type="submit" class="image2sku-btn image2sku-btn-primary"><i class="fas fa-check"></i> Apply Choices</button></div>';
        modalHTML += '</form></div></div>';
        
        $('#image2sku-results').html(modalHTML);
        
        $('#conflict-form').on('submit', function(e) {
            e.preventDefault();
            const conflictData = [];
            
            imagesWithConflicts.forEach(item => {
                const choice = $(`input[name="choice_${item.index}"]:checked`).val();
                conflictData.push({
                    index: item.index,
                    product_id: item.product_id,
                    choice: choice
                });
            });
            
            processConflicts(conflictData, allFiles);
        });
    }
    
    // Function to process conflicts
    function processConflicts(conflictData, allFiles) {
        const formData = new FormData();
        formData.append('action', 'image2sku_resolve_conflicts');
        formData.append('security', image2sku_vars.nonce);
        formData.append('conflict_data', JSON.stringify(conflictData));
        
        conflictData.forEach(item => {
            if (item.choice === 'use_new') {
                formData.append('images[]', allFiles[item.index]);
            }
        });
        
        $.ajax({
            url: image2sku_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    displayResults(response.data.results);
                }
            }
        });
    }

    // Handle form submission
    $('#image2sku-form').on('submit', function (e) {
        e.preventDefault();
        
        // Prevent double submission
        if (isUploading) {
            return;
        }
        
        const fileInput = $('#image2sku-file-input')[0];
        if (!fileInput.files || fileInput.files.length === 0) {
            const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-warning').html(
                '<i class="fas fa-exclamation-triangle"></i><div>Please select at least one image to upload.</div>'
            );
            $('.image2sku-card').prepend(errorMessage);
            return;
        }
        
        isUploading = true;
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).html('<span class="image2sku-spinner"></span> Preparing...');
        
        // Hide previous results and actions
        $('#image2sku-actions').hide();
        $('#image2sku-results').empty();
        lastUploadData = [];
        
        // Remove previous alerts
        $('.image2sku-alert').fadeOut(300, function() { $(this).remove(); });
        
        // Start chunked upload
        uploadInChunks(fileInput.files, 0, [], submitButton);
    });
    
    // Handle undo button
    $('#image2sku-undo').on('click', function () {
        if (lastUploadData.length === 0) {
            const warningMessage = $('<div>').addClass('image2sku-alert image2sku-alert-warning').html(
                '<i class="fas fa-exclamation-triangle"></i><div>No uploads to undo.</div>'
            );
            $('#image2sku-results').prepend(warningMessage);
            setTimeout(function() { warningMessage.fadeOut(300, function() { $(this).remove(); }); }, 3000);
            return;
        }
        
        if (!confirm(`⚠️ Are you sure you want to undo the last upload?\n\nThis will permanently delete ${lastUploadData.length} image(s) and remove them from their products.\n\nThis action cannot be undone.`)) {
            return;
        }
        
        const undoButton = $(this);
        undoButton.prop('disabled', true).html('<span class="image2sku-spinner"></span> Undoing...');
        
        $.ajax({
            url: image2sku_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'image2sku_undo_uploads',
                security: image2sku_vars.nonce,
                undo_data: JSON.stringify(lastUploadData)
            },
            success: function (response) {
                undoButton.prop('disabled', false).html('<i class="fas fa-undo"></i> Undo Last Upload');
                
                if (response.success) {
                    const successMessage = $('<div>').addClass('image2sku-alert image2sku-alert-success').html(
                        '<i class="fas fa-check-circle"></i><div><strong>Success!</strong> ' + response.data.message + '</div>'
                    );
                    $('#image2sku-results').prepend(successMessage);
                    
                    // Clear undo data and hide actions
                    lastUploadData = [];
                    $('#image2sku-actions').fadeOut(300);
                    
                    // Remove success message after 5 seconds
                    setTimeout(function () {
                        successMessage.fadeOut(300, function () {
                            $(this).remove();
                        });
                    }, 5000);
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: $('#image2sku-results').offset().top - 100
                    }, 300);
                } else {
                    const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                        '<i class="fas fa-exclamation-circle"></i><div><strong>Error:</strong> ' + (response.data || 'Undo failed') + '</div>'
                    );
                    $('#image2sku-results').prepend(errorMessage);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                undoButton.prop('disabled', false).html('<i class="fas fa-undo"></i> Undo Last Upload');
                
                const errorMessage = $('<div>').addClass('image2sku-alert image2sku-alert-error').html(
                    '<i class="fas fa-exclamation-circle"></i><div><strong>Error:</strong> Undo failed - ' + textStatus + ': ' + errorThrown + '</div>'
                );
                $('#image2sku-results').prepend(errorMessage);
            }
        });
    });
});
jQuery(document).ready(function ($) {
    // Function to display image previews
    function displayPreviews(files) {
        const previewsContainer = $('#image2sku-previews');
        previewsContainer.empty();

        for (let i = 0; i < files.length; i++) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = $('<img>').attr('src', e.target.result).addClass('image-preview').css({ width: '150px', height: '150px', objectFit: 'cover', margin: '5px' });
                previewsContainer.append(img);
            };
            reader.readAsDataURL(files[i]);
        }
    }

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
        $('#image2sku-file-input').prop('files', files);
        displayPreviews(files);
    });

    // Handle click event to open file dialog
    dropZone.on('click', function () {
        $('#image2sku-file-input').click();
    });

    // Handle file input change event to update previews
    $('#image2sku-file-input').on('change', function () {
        displayPreviews(this.files);
    });

    // Handle form submission
    $('#image2sku-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'image2sku_upload_images');
        formData.append('security', image2sku_vars.nonce);

        $.ajax({
            url: image2sku_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $('#image2sku-progress').val(percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                if (response.success) {
                    const results = response.data;
                    let output = '<table class="table table-striped">';
                    output += '<thead><tr><th>Name</th><th>Image</th><th>Filename</th><th>Status</th><th>Message</th><th>Link</th></tr></thead><tbody>';

                    results.forEach(result => {
                        const statusClass = result.status === 'success' ? 'text-success' : 'text-danger';
                        output += `<tr>
        <td>${result.name}</td>
        <td>${result.image}</td>
        <td>${result.filename}</td>
        <td class="${statusClass}">${result.status}</td>
        <td>${result.message}</td>
        <td><a href="${result.link}" target="_blank">View</a></td>
    </tr>`;
                    });

                    output += '</tbody></table>';
                    $('#image2sku-results').html(output);


                    $('#image2sku-download-report').show().off('click').on('click', function () {
                        const csv = generateCSVReport(results);
                        downloadCSVReport(csv, 'image2sku-report.csv');
                    });
                    // Hide any previous error messages
                    $('#image2sku-error').remove();
                } else {
                    // Display an error message
                    const errorMessage = $('<div>').attr('id', 'image2sku-error').addClass('alert alert-danger').text(response.data);
                    $('#image2sku-form').before(errorMessage);
                }
                $('#image2sku-progress').val(0);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Display an error message
                const errorMessage = $('<div>').attr('id', 'image2sku-error').addClass('alert alert-danger').text(`An error occurred while processing the request: ${textStatus}: ${errorThrown}`);
                $('#image2sku-form').before(errorMessage);

                $('#image2sku-progress').val(0);
            },
        });
    });
});
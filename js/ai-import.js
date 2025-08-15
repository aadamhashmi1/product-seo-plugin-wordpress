jQuery(document).ready(function ($) {
    $('#ai-upload-form').on('submit', function (e) {
        e.preventDefault();

        let formData = new FormData(this);

        // Hide form, show progress bar
        $('#ai-upload-form').hide();
        $('#ai-progress-section').show();

        // Step 1: Upload CSV
        $.ajax({
            url: AIGen.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                $('#ai-progress-text').text('Uploading CSV...');
            },
            success: function (res) {
                if (res.success) {
                    // Step 2: Start batch processing
                    processBatch(0);
                } else {
                    $('#ai-progress-text').text('Error: ' + res.data.message);
                }
            },
            error: function () {
                $('#ai-progress-text').text('Error uploading CSV.');
            },
            xhrFields: {
                withCredentials: true
            },
            data: formData.append('action', 'ai_gen_upload_csv')
        });
    });

    function processBatch(offset) {
        $.post(AIGen.ajax_url, {
            action: 'ai_gen_process_batch',
            offset: offset
        }, function (res) {
            if (res.success) {
                let percent = Math.round((res.data.offset / res.data.total) * 100);
                $('#ai-progress-fill').css('width', percent + '%');
                $('#ai-progress-text').text(percent + '%');

                if (!res.data.done) {
                    processBatch(res.data.offset);
                } else {
                    $('#ai-progress-text').text('✅ Done! All products generated.');
                }
            } else {
                $('#ai-progress-text').text('❌ Error: ' + res.data.message);
            }
        }, 'json');
    }
});

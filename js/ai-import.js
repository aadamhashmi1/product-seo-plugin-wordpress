jQuery(document).ready(function($) {
    const batchSize = 5;
    const totalRows = 100; // You can pass this dynamically
    const totalBatches = Math.ceil(totalRows / batchSize);
    let currentBatch = 0;
    const parallelLimit = 3;

    function sendBatch(batchIndex) {
        return $.ajax({
            url: ai_ajax.ajaxurl,
            method: 'POST',
            data: {
                action: 'ai_generate_batch',
                batch_index: batchIndex
            }
        });
    }

    function processBatches() {
        const batchGroup = [];
        for (let i = 0; i < parallelLimit && currentBatch < totalBatches; i++) {
            batchGroup.push(sendBatch(currentBatch));
            currentBatch++;
        }

        Promise.all(batchGroup).then(responses => {
            const processed = responses.reduce((sum, res) => sum + res.data.processed, 0);
            $('#progress-bar').val($('#progress-bar').val() + processed);

            if (currentBatch < totalBatches) {
                processBatches();
            } else {
                $('#status').text('✅ Import Complete!');
            }
        }).catch(error => {
            console.error('Batch error:', error);
            $('#status').text('❌ Error occurred. Check console.');
        });
    }

    $('#start-import').on('click', function() {
        $('#progress-bar').val(0);
        $('#status').text('⏳ Importing...');
        processBatches();
    });
});

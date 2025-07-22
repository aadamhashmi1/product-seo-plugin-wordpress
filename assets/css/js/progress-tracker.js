jQuery(document).ready(function($) {
  const products = JSON.parse($('#aipg-product-data').val());
  let total = products.length;
  let index = 0;

  function updateUI(id, text, status) {
    $('#product-' + id).text(`Product ${id}: ${text}`).removeClass().addClass(status);
    $('#aipg-progress-bar').val(((index + 1) / total) * 100);
  }

  function generateNext() {
    const id = products[index];
    updateUI(id, 'Generating...', 'pending');

    $.post(ajaxurl, { action: 'generate_ai_product', product_id: id }, function(res) {
      if (res.success) {
        updateUI(id, `✅ Done in ${res.data.duration}s`, 'done');
      } else {
        updateUI(id, '❌ Failed', 'error');
      }

      index++;
      if (index < total) generateNext();
    });
  }

  generateNext();
});

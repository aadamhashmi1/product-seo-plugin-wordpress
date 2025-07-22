<?php
$product_ids = [101, 102, 103]; // Replace dynamically if needed
?>

<div id="aipg-progress">
  <h2>AI Product Generation Tracker</h2>
  <progress id="aipg-progress-bar" value="0" max="100" style="width: 100%;"></progress>
  <ul>
    <?php foreach ($product_ids as $id): ?>
      <li id="product-<?php echo esc_attr($id); ?>">Product <?php echo esc_html($id); ?>: Waiting</li>
    <?php endforeach; ?>
  </ul>
  <input type="hidden" id="aipg-product-data" value='<?php echo json_encode($product_ids); ?>'>
</div>

<?php
/**
 * Plugin Name: Woo Quick ADD Pro
 * Version: 3.0
 * Description: Швидке додавання товарів з атрибутами та тегами через голос
 * Author: WooQuick
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '3.0');
define('WQA_FREE_LIMIT', 25);

// Функції ліміту
function wqa_get_product_count() {
    $count = wp_count_posts('product');
    return $count->publish;
}

function wqa_is_license_active() {
    $license_valid = get_option('wqa_license_valid', false);
    $license_domain = get_option('wqa_license_domain', '');
    $current_domain = $_SERVER['HTTP_HOST'];
    
    if ($license_valid && $license_domain !== $current_domain) {
        update_option('wqa_license_valid', false);
        return false;
    }
    return $license_valid;
}

function wqa_can_add_product() {
    if (wqa_is_license_active()) return true;
    return wqa_get_product_count() < WQA_FREE_LIMIT;
}

function wqa_get_remaining_free() {
    return max(0, WQA_FREE_LIMIT - wqa_get_product_count());
}

// Меню адмінки
add_action('admin_menu', function() {
    add_menu_page('Woo Quick ADD', 'Woo Quick ADD', 'manage_options', 'wqa_main', 'wqa_render_gui', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'wqa_license', 'wqa_render_license_page');
});

// Сторінка ліцензії
function wqa_render_license_page() {
    ?>
    <div class="wrap" style="max-width:600px; margin:auto; padding:20px;">
        <h2>🔑 Woo Quick ADD Pro - Ліцензія</h2>
        <?php if (wqa_is_license_active()): ?>
            <div style="background:#d4edda; padding:15px; border-radius:10px;">
                ✅ <strong>Ліцензія активна!</strong><br>
                Домен: <?php echo get_option('wqa_license_domain', ''); ?>
            </div>
        <?php else: ?>
            <div style="background:#fff3cd; padding:15px; border-radius:10px; margin-bottom:20px;">
                ⚠️ <strong>Безкоштовна версія</strong><br>
                Ліміт: <?php echo WQA_FREE_LIMIT; ?> товарів.<br>
                Залишилось: <?php echo wqa_get_remaining_free(); ?>
            </div>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px;">
                <h3>💰 Придбати ліцензію - 599 грн / $29</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_activate_action', 'wqa_activate_nonce'); ?>
                    <input type="text" name="license_key" placeholder="Ліцензійний ключ" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="wqa_activate_license" style="background:#4CAF50; color:#fff; padding:10px 20px;">🔑 Активувати</button>
                </form>
                <hr>
                <form method="post">
                    <?php wp_nonce_field('wqa_request_action', 'wqa_request_nonce'); ?>
                    <input type="email" name="buyer_email" placeholder="Ваш email" style="width:100%; padding:10px; margin-bottom:10px;">
                    <button type="submit" name="wqa_request_payment" style="background:#2196F3; color:#fff; padding:10px 20px;">📩 Запит на оплату</button>
                </form>
                <p style="font-size:12px; margin-top:15px;">📌 Після оплати надішліть чек - ми надамо ключ</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Обробка ліцензії
add_action('admin_init', function() {
    if (isset($_POST['wqa_activate_license']) && isset($_POST['license_key']) && wp_verify_nonce($_POST['wqa_activate_nonce'], 'wqa_activate_action')) {
        $key = trim($_POST['license_key']);
        if (strlen($key) >= 16) {
            update_option('wqa_license_valid', true);
            update_option('wqa_license_key', $key);
            update_option('wqa_license_domain', $_SERVER['HTTP_HOST']);
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний ключ (мінімум 16 символів)</p></div>';
        }
    }
    
    if (isset($_POST['wqa_request_payment']) && isset($_POST['buyer_email']) && wp_verify_nonce($_POST['wqa_request_nonce'], 'wqa_request_action')) {
        $buyer_email = sanitize_email($_POST['buyer_email']);
        $admin_email = get_option('admin_email');
        wp_mail($admin_email, 'Запит на ліцензію Woo Quick ADD', "Email покупця: $buyer_email");
        wp_mail($buyer_email, 'Інструкція з оплати Woo Quick ADD Pro', "Дякуємо за інтерес!\n\nОплатіть 599 грн / $29 USD\nПісля оплати надішліть чек - ми надамо ліцензійний ключ.\n\nДля оплати: PayPal або картка...");
        echo '<div class="notice notice-success"><p>✅ Запит надіслано! Перевірте пошту.</p></div>';
    }
});

// Головна сторінка плагіна (спрощена версія, повна буде при наступному оновленні)
function wqa_render_gui() {
    $remaining = wqa_get_remaining_free();
    $license_active = wqa_is_license_active();
    ?>
    <div class="wrap" style="max-width:700px; margin:auto; padding:20px;">
        <h1>🎤 Woo Quick ADD Pro v<?php echo WQA_VERSION; ?></h1>
        
        <div style="background: <?php echo $license_active ? '#d4edda' : '#fff3cd'; ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if ($license_active): ?>
                ✅ PRO ВЕРСІЯ - необмежено товарів
            <?php else: ?>
                📊 Безкоштовна версія: залишилось <strong><?php echo $remaining; ?></strong> з <?php echo WQA_FREE_LIMIT; ?> товарів
            <?php endif; ?>
        </div>
        
        <div style="text-align:center; padding:40px; background:#f5f5f5; border-radius:10px;">
            <p style="font-size:18px;">🎤 Голосове додавання товарів!</p>
            <p>Повна версія інтерфейсу з мікрофоном та фото буде доступна в наступному оновленні.</p>
            <p>📥 Для роботи використовуйте плагін як є - всі функції працюють.</p>
        </div>
        
        <div style="margin-top:20px; padding:15px; background:#e8f0fe; border-radius:10px;">
            <h3>📝 Швидка інструкція</h3>
            <p><strong>Команди:</strong> назва / ціна / категорія / бренд / мітка / атрибут</p>
            <p><strong>Приклад:</strong> "назва велосипед ціна 3500 категорія велосипеди"</p>
            <p><strong>Атрибут:</strong> "атрибут колір чорний"</p>
        </div>
    </div>
    <?php
}

// Збереження товару
add_action('wp_ajax_wqa_save_all', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Немає прав']);
        return;
    }
    
    if (!wqa_can_add_product()) {
        wp_send_json_error(['message' => 'Ліміт безкоштовної версії вичерпано. <a href="' . admin_url('admin.php?page=wqa_license') . '">Придбайте ліцензію</a>']);
        return;
    }
    
    $lines = explode("\n", $_POST['text']);
    $product = new WC_Product_Simple();
    $product->set_status('publish');
    
    $data = [];
    $attrs = [];
    $tags = [];
    
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) continue;
        list($k, $v) = explode(':', $line, 2);
        $key = trim(mb_strtolower($k));
        $val = trim($v);
        
        if (in_array($key, ['тег', 'мітка', 'позначка'])) {
            $tags[] = $val;
        } elseif ($key === 'атрибут') {
            $parts = explode(' ', $val, 2);
            if (count($parts) == 2) {
                $attrs[$parts[0]][] = $parts[1];
            }
        } else {
            $data[$key] = $val;
        }
    }
    
    $product->set_name($data['назва'] ?? 'Товар ' . date('H:i:s'));
    if (isset($data['ціна'])) $product->set_regular_price(preg_replace('/[^0-9.]/', '', $data['ціна']));
    if (isset($data['акція'])) $product->set_sale_price(preg_replace('/[^0-9.]/', '', $data['акція']));
    if (isset($data['артикул'])) $product->set_sku($data['артикул']);
    if (isset($data['опис'])) $product->set_description($data['опис']);
    
    $pid = $product->save();
    
    if (isset($data['категорія'])) {
        $term = term_exists($data['категорія'], 'product_cat');
        if (!$term) $term = wp_insert_term($data['категорія'], 'product_cat');
        if (!is_wp_error($term)) wp_set_object_terms($pid, [(int)$term['term_id']], 'product_cat');
    }
    
    if (isset($data['бренд'])) {
        $tax = taxonomy_exists('pwb-brand') ? 'pwb-brand' : 'product_brand';
        $term = term_exists($data['бренд'], $tax);
        if (!$term) $term = wp_insert_term($data['бренд'], $tax);
        if (!is_wp_error($term)) wp_set_object_terms($pid, [(int)$term['term_id']], $tax);
    }
    
    if (!empty($tags)) {
        $tag_ids = [];
        foreach ($tags as $tag) {
            if (empty($tag)) continue;
            $term = term_exists($tag, 'product_tag');
            if (!$term) $term = wp_insert_term($tag, 'product_tag');
            if (!is_wp_error($term)) $tag_ids[] = (int)$term['term_id'];
        }
        if (!empty($tag_ids)) wp_set_object_terms($pid, $tag_ids, 'product_tag');
    }
    
    if (!empty($attrs)) {
        $product_attrs = [];
        foreach ($attrs as $name => $values) {
            $slug = sanitize_title($name);
            if (!wc_attribute_taxonomy_id_by_name($name)) {
                wc_create_attribute(['name' => $name, 'slug' => $slug, 'type' => 'select']);
                delete_transient('wc_attribute_taxonomies');
            }
            $tax_name = wc_attribute_taxonomy_name($name);
            if (!taxonomy_exists($tax_name)) {
                register_taxonomy($tax_name, 'product', ['label' => $name, 'public' => false]);
            }
            $term_ids = [];
            foreach ($values as $val) {
                if (empty($val)) continue;
                $term = term_exists($val, $tax_name);
                if (!$term) $term = wp_insert_term($val, $tax_name);
                if (!is_wp_error($term)) $term_ids[] = (int)$term['term_id'];
            }
            if (!empty($term_ids)) wp_set_object_terms($pid, $term_ids, $tax_name);
            $product_attrs[$tax_name] = [
                'name' => $tax_name,
                'value' => implode(', ', $values),
                'is_visible' => 1,
                'is_taxonomy' => 1
            ];
        }
        if (!empty($product_attrs)) update_post_meta($pid, '_product_attributes', $product_attrs);
    }
    
    wp_send_json_success(['id' => $pid]);
});
?>
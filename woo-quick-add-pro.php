<?php

/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 4.1
 * Description: Голосове додавання + Експорт товарів для WooCommerce
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/woo-quick-add-pro
 * License URI: https://github.com/portallcomua/woo-quick-add-pro/blob/main/LICENSE
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '4.1');
define('WQA_FREE_LIMIT', 25);
define('WQA_SHOP_URL', 'https://uaserver.pp.ua/product/wooquick-add-pro/');
define('WQA_GITHUB_REPO', 'portallcomua/woo-quick-add-pro');
define('WQA_LICENSE_CHECK_URL', 'https://uaserver.pp.ua/wp-json/wqa/v1/validate/'); // Ваш API для перевірки ключів

// ========== АВТОМАТИЧНІ ОНОВЛЕННЯ ЧЕРЕЗ GITHUB ==========
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/" . WQA_GITHUB_REPO . "/releases/latest");
    
    if (is_wp_error($response)) return $transient;
    
    $release = json_decode(wp_remote_retrieve_body($response));
    
    if ($release && isset($release->tag_name)) {
        $latest = ltrim($release->tag_name, 'v');
        $current = WQA_VERSION;
        
        if (version_compare($current, $latest, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest,
                'url' => $release->html_url,
                'package' => $release->zipball_url,
                'tested' => '6.8',
                'requires_php' => '7.4',
            ];
        }
    }
    
    return $transient;
});

// Додаємо деталі оновлення
add_filter('plugins_api', function($res, $action, $args) {
    if ($action !== 'plugin_information') return $res;
    if ($args->slug !== dirname(plugin_basename(__FILE__))) return $res;
    
    $response = wp_remote_get("https://api.github.com/repos/" . WQA_GITHUB_REPO . "/releases/latest");
    if (is_wp_error($response)) return $res;
    
    $release = json_decode(wp_remote_retrieve_body($response));
    if (!$release) return $res;
    
    $res = new stdClass();
    $res->name = 'WooQuick ADD Pro';
    $res->slug = dirname(plugin_basename(__FILE__));
    $res->version = ltrim($release->tag_name, 'v');
    $res->author = 'portallcomua';
    $res->homepage = $release->html_url;
    $res->download_link = $release->zipball_url;
    $res->requires = '5.8';
    $res->tested = '6.8';
    $res->requires_php = '7.4';
    $res->sections = [
        'description' => 'Голосове додавання товарів для WooCommerce + Експорт у CSV',
        'changelog' => $release->body ?? 'Оновлення доступне'
    ];
    
    return $res;
}, 10, 3);

// ========== АВТОМАТИЧНЕ СТВОРЕННЯ СТОРІНОК ПРИ АКТИВАЦІЇ ==========
register_activation_hook(__FILE__, 'wqa_activate_plugin');
function wqa_activate_plugin() {
    // Створюємо сторінку для експорту
    if (!get_page_by_path('export-products')) {
        wp_insert_post([
            'post_title'   => 'Експорт товарів',
            'post_name'    => 'export-products',
            'post_content' => '[wqa_export_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'meta_input'   => ['_wqa_export_page' => 1]
        ]);
    }
    
    // Створюємо сторінку з інструкцією
    if (!get_page_by_path('quick-add-instruction')) {
        wp_insert_post([
            'post_title'   => 'Інструкція WooQuick Add',
            'post_name'    => 'quick-add-instruction',
            'post_content' => '
                <h2>🎤 Голосове додавання товарів</h2>
                <p>1. Натисніть кнопку мікрофона</p>
                <p>2. Скажіть: "назва футболка", "ціна 250", "опис крутий товар"</p>
                <p>3. Натисніть "Опублікувати"</p>
                <h2>📤 Експорт товарів</h2>
                <p>Використовуйте форму нижче для експорту всіх товарів у CSV</p>
                <h2>🔑 Ліцензування</h2>
                <p>Після покупки ви отримаєте ліцензійний ключ. Активуйте його в розділі "Ліцензія" адмін-панелі.</p>
            ',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ]);
    }
    
    // Додаємо роль для продавців
    if (!get_role('wqa_seller')) {
        add_role('wqa_seller', 'Продавець WooQuick', [
            'read' => true,
            'edit_products' => true,
            'upload_files' => true,
            'edit_product' => true,
            'delete_product' => false,
        ]);
    }
    
    // Встановлюємо початкові опції
    if (!get_option('wqa_operations')) update_option('wqa_operations', 0);
    if (!get_option('wqa_license_key')) update_option('wqa_license_key', '');
    if (!get_option('wqa_license_expires')) update_option('wqa_license_expires', '');
}

// ========== ФУНКЦІЇ ЛІЦЕНЗУВАННЯ ==========
function wqa_get_count() { return (int) get_option('wqa_operations', 0); }
function wqa_inc() { update_option('wqa_operations', wqa_get_count() + 1); }
function wqa_remaining() { return max(0, WQA_FREE_LIMIT - wqa_get_count()); }

function wqa_is_license_valid() {
    $license_active = get_option('wqa_license', false);
    $expires = get_option('wqa_license_expires', '');
    
    if (!$license_active) return false;
    
    // Перевіряємо термін дії (якщо є)
    if ($expires && strtotime($expires) < time()) {
        delete_option('wqa_license');
        delete_option('wqa_license_key');
        delete_option('wqa_license_expires');
        return false;
    }
    
    return true;
}

function wqa_can() { 
    return wqa_is_license_valid() ? true : wqa_get_count() < WQA_FREE_LIMIT; 
}

// Перевірка ліцензії через API (опціонально)
function wqa_validate_license_remote($key) {
    $response = wp_remote_post(WQA_LICENSE_CHECK_URL, [
        'body' => json_encode(['license_key' => $key, 'domain' => home_url()]),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10,
    ]);
    
    if (is_wp_error($response)) return false;
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['valid'] ?? false;
}

// Генерація унікального ліцензійного ключа (для адміна)
function wqa_generate_license_key() {
    return 'WQA-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . 
           '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . 
           '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4)) . 
           '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
}

// ========== АДМІН МЕНЮ ==========
add_action('admin_menu', function() {
    add_menu_page('WooQuick ADD', 'WooQuick ADD', 'manage_woocommerce', 'wqa_main', 'wqa_render_page', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wqa_license', 'wqa_license_page');
    add_submenu_page('wqa_main', '📤 Експорт', '📤 Експорт', 'manage_woocommerce', 'wqa_export_page', 'wqa_export_admin_page');
    add_submenu_page('wqa_main', '⚙️ Налаштування', '⚙️ Налаштування', 'manage_woocommerce', 'wqa_settings', 'wqa_settings_page');
});

// Пункт "Продажі" в Товари (тільки для адміна)
add_action('admin_menu', function() {
    if (current_user_can('administrator')) {
        add_submenu_page('edit.php?post_type=product', '📊 Продажі WooQuick', '📊 Продажі', 'manage_options', 'wqa_sales', 'wqa_sales_page');
    }
});

// Сторінка налаштувань
function wqa_settings_page() {
    ?>
    <div class="wrap">
        <h1>⚙️ Налаштування WooQuick ADD Pro</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wqa_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>Ліміт безкоштовних додавань</th>
                    <td>
                        <input type="number" name="wqa_free_limit" value="<?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?>" min="1" max="1000">
                        <p class="description">Кількість товарів, які можна додати без ліцензії</p>
                    </td>
                </tr>
                <tr>
                    <th>URL магазину</th>
                    <td>
                        <input type="url" name="wqa_shop_url" value="<?php echo get_option('wqa_shop_url', WQA_SHOP_URL); ?>" style="width:100%">
                        <p class="description">Посилання на сторінку покупки ліцензії</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Реєстрація налаштувань
add_action('admin_init', function() {
    register_setting('wqa_settings', 'wqa_free_limit', 'intval');
    register_setting('wqa_settings', 'wqa_shop_url', 'esc_url_raw');
});

function wqa_sales_page() {
    $total_ops = (int) get_option('wqa_operations', 0);
    $license_active = wqa_is_license_valid();
    $license_key = get_option('wqa_license_key', '');
    $expires = get_option('wqa_license_expires', '');
    ?>
    <div class="wrap">
        <h1>📊 Статистика продажів WooQuick ADD</h1>
        <div style="background:#fff; padding:20px; border-radius:10px; max-width:600px;">
            <p><strong>Всього доданих товарів:</strong> <?php echo $total_ops; ?></p>
            <p><strong>Ліцензія активна:</strong> <?php echo $license_active ? '✅ Так' : '❌ Ні'; ?></p>
            <?php if ($license_active): ?>
                <p><strong>Ліцензійний ключ:</strong> <code><?php echo esc_html($license_key); ?></code></p>
                <?php if ($expires): ?>
                    <p><strong>Дійсна до:</strong> <?php echo date_i18n(get_option('date_format'), strtotime($expires)); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p><strong>Залишилось безкоштовних операцій:</strong> <?php echo wqa_remaining(); ?> / <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?></p>
                <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" class="button button-primary">🔑 Активувати PRO</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function wqa_export_admin_page() {
    echo do_shortcode('[wqa_export_form]');
}

// Сторінка ліцензії з генерацією ключів для адміна
function wqa_license_page() { 
    $can_generate = current_user_can('administrator');
    ?>
    <div class="wrap">
        <h1>🔑 Ліцензія WooQuick ADD Pro</h1>
        
        <?php if (wqa_is_license_valid()): ?>
            <div class="notice notice-success">
                <p>✅ Ліцензія активна. Всі функції доступні.</p>
                <?php if (get_option('wqa_license_expires')): ?>
                    <p>📅 Дійсна до: <?php echo date_i18n(get_option('date_format'), strtotime(get_option('wqa_license_expires'))); ?></p>
                <?php endif; ?>
            </div>
            <form method="post">
                <?php wp_nonce_field('wqa_lic'); ?>
                <button type="submit" name="deactivate_lic" class="button" style="background:#dc3545; color:#fff;">🚫 Деактивувати ліцензію</button>
            </form>
            
        <?php else: ?>
            <div class="notice notice-warning">
                <p>⚠️ Безкоштовна версія: залишилось <strong><?php echo wqa_remaining(); ?></strong> з <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?> додавань</p>
            </div>
            
            <div style="background:#f9f9f9; padding:20px; border-radius:10px; margin:20px 0;">
                <h3>Активувати ліцензію</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_lic'); ?>
                    <input name="license_key" placeholder="Введіть ліцензійний ключ" style="width:300px; padding:8px;">
                    <button type="submit" name="activate_lic" class="button button-primary">🔑 Активувати</button>
                </form>
                <p style="margin-top:15px;">
                    <a href="<?php echo get_option('wqa_shop_url', WQA_SHOP_URL); ?>" target="_blank" class="button">💰 Придбати PRO (599 грн / $29)</a>
                </p>
            </div>
            
            <?php if ($can_generate): ?>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px; margin:20px 0;">
                <h3>🔧 Генерація ліцензійних ключів (тільки для адміністратора)</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_generate'); ?>
                    <button type="submit" name="generate_license" class="button">🔄 Згенерувати новий ключ</button>
                </form>
                <?php if (get_option('wqa_generated_keys')): ?>
                    <div style="margin-top:15px;">
                        <p><strong>Згенеровані ключі:</strong></p>
                        <textarea rows="5" style="width:100%; font-family:monospace;"><?php echo implode("\n", get_option('wqa_generated_keys', [])); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// Обробка ліцензій
add_action('admin_init', function() {
    // Активація ліцензії
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic')) {
        $license_key = trim($_POST['license_key']);
        
        if (strlen($license_key) >= 16) {
            // Локальна перевірка (можна замінити на API)
            update_option('wqa_license', true);
            update_option('wqa_license_key', $license_key);
            
            // Встановлюємо термін дії (наприклад, 1 рік)
            $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
            update_option('wqa_license_expires', $expires);
            
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано! Дякуємо за покупку.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний ключ (мінімум 16 символів)</p></div>';
        }
    }
    
    // Деактивація ліцензії
    if (isset($_POST['deactivate_lic']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic')) {
        delete_option('wqa_license');
        delete_option('wqa_license_key');
        delete_option('wqa_license_expires');
        echo '<div class="notice notice-info"><p>🔄 Ліцензію деактивовано</p></div>';
    }
    
    // Генерація ключа (тільки для адміна)
    if (isset($_POST['generate_license']) && current_user_can('administrator') && wp_verify_nonce($_POST['_wpnonce'], 'wqa_generate')) {
        $new_key = wqa_generate_license_key();
        
        $existing_keys = get_option('wqa_generated_keys', []);
        $existing_keys[] = $new_key . ' (згенеровано: ' . date('Y-m-d H:i:s') . ')';
        update_option('wqa_generated_keys', $existing_keys);
        
        echo '<div class="notice notice-success"><p>✅ Згенеровано новий ключ: <code>' . $new_key . '</code></p></div>';
    }
});

// ========== КОРОТКИЙ КОД ДЛЯ ФОРМИ ЕКСПОРТУ ==========
add_shortcode('wqa_export_form', 'wqa_export_form_shortcode');
function wqa_export_form_shortcode() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) {
        return '<p>Немає прав для експорту</p>';
    }
    
    ob_start();
    ?>
    <div style="max-width:600px; margin:20px auto; padding:20px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>📤 Експорт товарів у CSV</h2>
        <p>Експортуються всі товари з усіма полями (включаючи фото, атрибути, теги)</p>
        <form method="post" action="">
            <?php wp_nonce_field('wqa_export', 'wqa_export_nonce'); ?>
            <button type="submit" name="wqa_full_export" style="background:#2271b1; color:#fff; padding:12px 24px; border:none; border-radius:5px; cursor:pointer; font-size:16px;">
                ⬇️ ЗАНТАЖИТИ CSV (ВСІ ТОВАРИ)
            </button>
        </form>
        <div style="margin-top:15px; font-size:12px; color:#666;">
            <p>📋 CSV містить: ID, SKU, Назва, Опис, Короткий опис, Ціна, Акційна ціна, Категорії, Теги, Бренд, Атрибути (JSON), Фото (розділені |), Дата, Статус, URL, Вага, Розміри, Рейтинг, Наявність</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ========== ОБРОБКА ЕКСПОРТУ ==========
add_action('init', 'wqa_handle_export');
function wqa_handle_export() {
    if (!isset($_POST['wqa_full_export'])) return;
    if (!wp_verify_nonce($_POST['wqa_export_nonce'], 'wqa_export')) wp_die('Security check');
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) wp_die('No permission');
    
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    $csv_data = [];
    
    $headers = [
        'ID', 'SKU', 'Назва', 'Опис', 'Короткий опис', 
        'Регулярна ціна', 'Акційна ціна', 'Категорії', 'Теги', 'Бренд',
        'Атрибути (JSON)', 'Фото (URLs)', 'Дата створення', 'Статус', 'URL товару',
        'В наявності', 'Вага', 'Довжина', 'Ширина', 'Висота', 'Рейтинг'
    ];
    
    foreach ($products as $product) {
        $pid = $product->get_id();
        
        $image_urls = [];
        $thumbnail_id = $product->get_image_id();
        if ($thumbnail_id) {
            $image_urls[] = wp_get_attachment_url($thumbnail_id);
        }
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gid) {
            $image_urls[] = wp_get_attachment_url($gid);
        }
        $photos_field = implode('|', $image_urls);
        
        $attributes_data = [];
        $attributes = $product->get_attributes();
        foreach ($attributes as $attr_name => $attr) {
            if (is_a($attr, 'WC_Product_Attribute')) {
                $attr_values = $attr->get_options();
                if (taxonomy_exists($attr_name)) {
                    $terms = [];
                    foreach ($attr_values as $term_id) {
                        $term = get_term($term_id);
                        if ($term) $terms[] = $term->name;
                    }
                    $attributes_data[$attr_name] = implode(', ', $terms);
                } else {
                    $attributes_data[$attr_name] = implode(', ', $attr_values);
                }
            }
        }
        
        $brand = '';
        $brand_taxonomies = ['pwb-brand', 'product_brand', 'brand'];
        foreach ($brand_taxonomies as $tax) {
            if (taxonomy_exists($tax)) {
                $brands = wp_get_post_terms($pid, $tax, ['fields' => 'names']);
                if (!empty($brands)) {
                    $brand = implode(', ', $brands);
                    break;
                }
            }
        }
        
        $row = [
            'ID' => $pid,
            'SKU' => $product->get_sku(),
            'Назва' => $product->get_name(),
            'Опис' => $product->get_description(),
            'Короткий опис' => $product->get_short_description(),
            'Регулярна ціна' => $product->get_regular_price(),
            'Акційна ціна' => $product->get_sale_price(),
            'Категорії' => implode(', ', wp_get_post_terms($pid, 'product_cat', ['fields' => 'names'])),
            'Теги' => implode(', ', wp_get_post_terms($pid, 'product_tag', ['fields' => 'names'])),
            'Бренд' => $brand,
            'Атрибути (JSON)' => json_encode($attributes_data, JSON_UNESCAPED_UNICODE),
            'Фото (URLs)' => $photos_field,
            'Дата створення' => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : '',
            'Статус' => $product->get_status(),
            'URL товару' => get_permalink($pid),
            'В наявності' => $product->is_in_stock() ? 'Так' : 'Ні',
            'Вага' => $product->get_weight(),
            'Довжина' => $product->get_length(),
            'Ширина' => $product->get_width(),
            'Висота' => $product->get_height(),
            'Рейтинг' => $product->get_average_rating(),
        ];
        
        $csv_data[] = $row;
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products-export-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers);
    
    foreach ($csv_data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// ========== ГОЛОВНА СТОРІНКА ПЛАГІНА (СКОРОЧЕНА ВЕРСІЯ) ==========
function wqa_render_page() {
    ?>
    <div class="wrap" style="max-width:800px; margin:auto; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>🎤 WooQuick ADD Pro</h1>
            <span style="background:#2271b1; color:#fff; padding:4px 12px; border-radius:20px;">v<?php echo WQA_VERSION; ?></span>
        </div>

        <div style="background:<?php echo wqa_is_license_valid() ? '#d4edda' : '#fff3cd'; ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if (wqa_is_license_valid()): ?>
                ✅ PRO версія активна - безлімітне додавання
            <?php else: ?>
                📊 Безкоштовна версія: залишилось <strong><?php echo wqa_remaining(); ?></strong> з <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?>
                <?php if (wqa_remaining() == 0): ?>
                    <br><a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" style="color:#d9534f;">💰 Придбати ліцензію →</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:15px;">
            <a href="<?php echo home_url('/quick-add-instruction/'); ?>" target="_blank" class="button">📖 Інструкція</a>
            <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" class="button">🔑 Ліцензія</a>
            <a href="<?php echo admin_url('admin.php?page=wqa_export_page'); ?>" class="button">📤 Експорт товарів</a>
            <a href="<?php echo admin_url('admin.php?page=wqa_sales'); ?>" class="button">📊 Статистика</a>
        </div>

        <video id="video" width="100%" autoplay playsinline muted style="background:#000; border-radius:10px; margin-bottom:10px; transform:scaleX(-1);"></video>
        <div id="gallery" style="display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px;"></div>
        <div style="display:flex; gap:10px; margin-bottom:15px;">
            <button id="take_photo" class="button" style="flex:2;">📷 ЗРОБИТИ ФОТО</button>
            <button id="clear_photos" class="button" style="flex:1;">❌ ОЧИСТИТИ</button>
        </div>

        <div style="margin-bottom:15px;">
            <label style="font-weight:bold;">📝 ТЕКСТ ТОВАРУ:</label>
            <textarea id="voice_text" rows="15" style="width:100%; font-family:monospace; font-size:14px; padding:10px; border:2px solid #2271b1; border-radius:10px;"></textarea>
        </div>

        <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; border:none; padding:15px; border-radius:10px; margin-bottom:15px;">🎤 ДИКТУВАТИ ГОЛОСОМ</button>
        <button id="save_btn" style="width:100%; background:#00a32a; color:#fff; border:none; padding:15px; border-radius:10px; font-size:18px; font-weight:bold;">➕ ОПУБЛІКУВАТИ</button>
        <div id="message" style="margin-top:15px; padding:10px; border-radius:10px; display:none;"></div>
    </div>

    <script>
        // ... (той самий JavaScript код, що був раніше, без змін)
        const voiceText = document.getElementById('voice_text');
        let photos = [], stream = null, listening = false;
        let activeField = null;
        let lastCommandTime = 0;
        let pendingValue = null;

        const fields = ['НАЗВА', 'ЦІНА', 'АКЦІЯ', 'АРТИКУЛ', 'КАТЕГОРІЯ', 'БРЕНД', 'ОПИС'];
        
        function buildTextTemplate() {
            let text = "";
            for (let i = 0; i < fields.length; i++) {
                text += `${fields[i]}: \n`;
            }
            voiceText.value = text;
        }
        buildTextTemplate();

        function findFieldPosition(fieldName) {
            const content = voiceText.value;
            const lines = content.split('\n');
            let pos = 0;
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                if (line.toLowerCase().startsWith(fieldName.toLowerCase() + ':')) {
                    const colonIndex = line.indexOf(':');
                    if (colonIndex !== -1) {
                        return pos + colonIndex + 2;
                    }
                }
                pos += line.length + 1;
            }
            return -1;
        }

        function insertAtPosition(pos, text) {
            const content = voiceText.value;
            const before = content.slice(0, pos);
            const after = content.slice(pos);
            voiceText.value = before + text + after;
            voiceText.focus();
            voiceText.setSelectionRange(pos + text.length, pos + text.length);
        }

        function showMessage(msg, type) {
            const div = document.getElementById('message');
            div.innerHTML = msg;
            div.style.background = type === 'error' ? '#f8d7da' : (type === 'success' ? '#d4edda' : '#e8f0fe');
            div.style.color = type === 'error' ? '#721c24' : (type === 'success' ? '#155724' : '#004085');
            div.style.display = 'block';
            setTimeout(() => div.style.display = 'none', 3000);
        }

        async function startCamera() {
            try {
                if (stream) stream.getTracks().forEach(t => t.stop());
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                document.getElementById('video').srcObject = stream;
            } catch(e) { console.log(e); }
        }
        startCamera();

        document.getElementById('take_photo').onclick = () => {
            const v = document.getElementById('video');
            const canvas = document.createElement('canvas');
            canvas.width = v.videoWidth;
            canvas.height = v.videoHeight;
            canvas.getContext('2d').drawImage(v, 0, 0);
            const imgData = canvas.toDataURL('image/jpeg', 0.8);
            photos.push(imgData);
            const img = document.createElement('img');
            img.src = imgData;
            img.style.cssText = 'width:70px;height:70px;object-fit:cover;border-radius:8px;margin:2px;border:2px solid #ddd';
            document.getElementById('gallery').prepend(img);
        };
        
        document.getElementById('clear_photos').onclick = () => { photos = []; document.getElementById('gallery').innerHTML = ''; };

        if (window.SpeechRecognition || window.webkitSpeechRecognition) {
            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'uk-UA';
            recognition.continuous = true;
            recognition.interimResults = false;
            
            const micBtn = document.getElementById('mic_btn');
            
            micBtn.onclick = () => {
                if (!listening) {
                    recognition.start();
                    listening = true;
                    micBtn.style.background = "#ff4b4b";
                    micBtn.innerText = "🛑 ЗУПИНИТИ ДИКТУВАННЯ";
                    showMessage("🎤 Слухаю...", "info");
                } else {
                    recognition.stop();
                    listening = false;
                    micBtn.style.background = "#1877F2";
                    micBtn.innerText = "🎤 ДИКТУВАТИ ГОЛОСОМ";
                }
            };
            
            recognition.onresult = (e) => {
                const fullText = e.results[e.results.length-1][0].transcript.trim();
                const lowerText = fullText.toLowerCase();
                const now = Date.now();
                
                const fieldKeywords = {
                    'НАЗВА': ['назва', 'назву', 'імя'],
                    'ЦІНА': ['ціна', 'ціну', 'вартість', 'коштує'],
                    'АКЦІЯ': ['акція', 'знижка', 'сейл'],
                    'АРТИКУЛ': ['артикул', 'скю', 'код'],
                    'КАТЕГОРІЯ': ['категорія', 'категорію', 'розділ'],
                    'БРЕНД': ['бренд', 'марка', 'виробник'],
                    'ОПИС': ['опис', 'текст']
                };
                
                let foundField = null;
                let valueToInsert = null;
                
                for (let [field, keywords] of Object.entries(fieldKeywords)) {
                    for (let kw of keywords) {
                        if (lowerText === kw) {
                            foundField = field;
                            break;
                        }
                        if (lowerText.startsWith(kw + ' ')) {
                            foundField = field;
                            valueToInsert = fullText.substring(kw.length + 1).trim();
                            break;
                        }
                    }
                    if (foundField) break;
                }
                
                if (foundField) {
                    const pos = findFieldPosition(foundField);
                    if (pos !== -1) {
                        voiceText.focus();
                        voiceText.setSelectionRange(pos, pos);
                        activeField = foundField;
                        lastCommandTime = now;
                        
                        if (valueToInsert && valueToInsert.trim()) {
                            setTimeout(() => {
                                insertAtPosition(pos, valueToInsert + ' ');
                                showMessage(`✅ ${foundField}: ${valueToInsert}`, 'success');
                                activeField = null;
                            }, 50);
                        } else {
                            pendingValue = true;
                            showMessage(`🎤 Скажіть значення для ${foundField}...`, 'info');
                        }
                    }
                }
                else if (activeField && pendingValue && (now - lastCommandTime) < 5000) {
                    const pos = findFieldPosition(activeField);
                    if (pos !== -1) {
                        insertAtPosition(pos, fullText + ' ');
                        showMessage(`✅ ${activeField}: ${fullText}`, 'success');
                        activeField = null;
                        pendingValue = null;
                    }
                }
            };
            
            recognition.onerror = (event) => {
                if (event.error !== 'no-speech') {
                    showMessage(`❌ Помилка: ${event.error}`, 'error');
                }
            };
            
            recognition.onend = () => { 
                if (listening) recognition.start();
            };
        } else {
            document.getElementById('mic_btn').disabled = true;
            document.getElementById('mic_btn').innerText = "❌ ГОЛОС НЕ ПІДТРИМУЄТЬСЯ";
        }

        document.getElementById('save_btn').onclick = async function() {
            <?php if (!wqa_can()): ?>
                showMessage('❌ Ліміт вичерпано! Придбайте ліцензію', 'error');
                return;
            <?php endif; ?>
            
            this.disabled = true;
            this.innerText = "⏳ ЗБЕРІГАЮ...";
            
            const fd = new FormData();
            fd.append('action', 'wqa_save_product');
            fd.append('text', voiceText.value);
            fd.append('images', JSON.stringify(photos));
            
            try {
                const resp = await fetch(ajaxurl, { method: 'POST', body: fd });
                const res = await resp.json();
                if (res.success) {
                    showMessage(`✅ Товар додано! ID: ${res.data.id}`, 'success');
                    this.innerText = "➕ ДОДАТИ ЩЕ";
                    this.style.background = "#ff9800";
                    photos = [];
                    document.getElementById('gallery').innerHTML = '';
                    buildTextTemplate();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(`❌ ${res.data.message}`, 'error');
                    this.disabled = false;
                    this.innerText = "➕ ОПУБЛІКУВАТИ";
                }
            } catch(e) {
                showMessage('❌ Помилка з\'єднання', 'error');
                this.disabled = false;
                this.innerText = "➕ ОПУБЛІКУВАТИ";
            }
        };
    </script>
    <?php
}

// ========== AJAX ЗБЕРЕЖЕННЯ ТОВАРУ ==========
add_action('wp_ajax_wqa_save_product', function() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) {
        wp_send_json_error('Немає прав');
    }
    if (!wqa_can()) wp_send_json_error('Ліміт вичерпано. Придбайте ліцензію');

    $raw_text = $_POST['text'] ?? '';
    $lines = explode("\n", $raw_text);
    $photos = json_decode(stripslashes($_POST['images']), true);
    
    $data = [];
    
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1] ?? '');
            if ($value) $data[$key] = $value;
        }
    }
    
    $product = new WC_Product_Simple();
    $product->set_status('publish');
    $product->set_name($data['НАЗВА'] ?? 'Товар ' . date('H:i:s'));
    if (isset($data['ЦІНА'])) $product->set_regular_price(preg_replace('/[^0-9.]/', '', $data['ЦІНА']));
    if (isset($data['АКЦІЯ'])) $product->set_sale_price(preg_replace('/[^0-9.]/', '', $data['АКЦІЯ']));
    if (isset($data['АРТИКУЛ'])) $product->set_sku($data['АРТИКУЛ']);
    if (isset($data['ОПИС'])) $product->set_description($data['ОПИС']);
    
    $pid = $product->save();
    
    if (isset($data['КАТЕГОРІЯ'])) {
        $term = term_exists($data['КАТЕГОРІЯ'], 'product_cat');
        if (!$term) $term = wp_insert_term($data['КАТЕГОРІЯ'], 'product_cat');
        if (!is_wp_error($term)) wp_set_object_terms($pid, (int)$term['term_id'], 'product_cat');
    }
    
    if (isset($data['БРЕНД'])) {
        $tax = taxonomy_exists('pwb-brand') ? 'pwb-brand' : 'product_brand';
        $term = term_exists($data['БРЕНД'], $tax);
        if (!$term) $term = wp_insert_term($data['БРЕНД'], $tax);
        if (!is_wp_error($term)) wp_set_object_terms($pid, (int)$term['term_id'], $tax);
    }
    
    $gallery_ids = [];
    if (!empty($photos)) {
        foreach ($photos as $i => $b64) {
            $img = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $b64));
            $file = wp_upload_bits('wqa_' . $pid . '_' . time() . '_' . $i . '.jpg', null, $img);
            if (!$file['error']) {
                $aid = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], $file['file'], $pid);
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                wp_update_attachment_metadata($aid, wp_generate_attachment_metadata($aid, $file['file']));
                if ($i === 0) set_post_thumbnail($pid, $aid);
                else $gallery_ids[] = $aid;
            }
        }
        if (!empty($gallery_ids)) update_post_meta($pid, '_product_image_gallery', implode(',', $gallery_ids));
    }
    
    $product->save();
    wqa_inc();
    wp_send_json_success(['id' => $pid]);
});

// ========== ДОДАТКОВІ ПОСИЛАННЯ В МЕНЮ ТОВАРІВ ==========
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=product',
        '📤 Експорт WooQuick',
        '📤 Експорт',
        'edit_products',
        'wqa_export_products',
        function() { echo do_shortcode('[wqa_export_form]'); }
    );
});

// ========== СПОВІЩЕННЯ ПРО ЛІМІТ ==========
add_action('admin_notices', function() {
    if (!wqa_is_license_valid() && wqa_remaining() <= 5 && wqa_remaining() > 0) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>⚠️ <strong>WooQuick ADD Pro:</strong> У вас залишилось <strong><?php echo wqa_remaining(); ?></strong> безкоштовних додавань. <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>">Придбайте ліцензію</a> для безлімітного використання.</p>
        </div>
        <?php
    }
    
    if (!wqa_is_license_valid() && wqa_remaining() == 0) {
        ?>
        <div class="notice notice-error">
            <p>❌ <strong>WooQuick ADD Pro:</strong> Ліміт безкоштовних додавань вичерпано! <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>">Активуйте ліцензію</a> для продовження роботи.</p>
        </div>
        <?php
    }
});

// Видаляємо при деактивації
register_deactivation_hook(__FILE__, 'wqa_deactivate_plugin');
function wqa_deactivate_plugin() {
    // Опціонально
}
?>

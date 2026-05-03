<?php

/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 4.2
 * Description: Голосове додавання + Експорт товарів для WooCommerce
 * Author: UAServer
 * GitHub Plugin URI: https://github.com/portallcomua/woo-quick-add-pro
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '4.2');
define('WQA_FREE_LIMIT', 25);
define('WQA_PRICE', 599); // Ціна в гривнях
define('WQA_GITHUB_REPO', 'portallcomua/woo-quick-add-pro');

// ==================== МОНЕТИЗАЦІЯ (як у Woo Impex) ====================
function wqa_has_pro() {
    $key = get_option('wqa_license_key', '');
    if (empty($key)) return false;
    $lics = get_option('wqa_licenses', []);
    // Перевіряємо чи ключ активний і не прострочений
    if (isset($lics[$key]) && $lics[$key]['status'] === 'active') {
        // Перевіряємо термін дії (якщо є)
        if (isset($lics[$key]['expires']) && strtotime($lics[$key]['expires']) < time()) {
            return false;
        }
        return true;
    }
    return false;
}

function wqa_get_count() { return (int) get_option('wqa_operations', 0); }
function wqa_inc() { update_option('wqa_operations', wqa_get_count() + 1); }
function wqa_can() { return wqa_has_pro() ? true : wqa_get_count() < WQA_FREE_LIMIT; }
function wqa_remaining() { return max(0, WQA_FREE_LIMIT - wqa_get_count()); }

// Генерація унікального ключа
function wqa_generate_key() {
    return 'WQA-' . strtoupper(uniqid()) . '-' . substr(md5(rand()), 0, 8);
}

// ==================== АВТОМАТИЧНЕ СТВОРЕННЯ СТОРІНОК ====================
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
                <h2>💰 Придбання PRO версії</h2>
                <p>Ціна: <strong>' . WQA_PRICE . ' грн</strong></p>
                <p>Реквізити для оплати: <strong>' . get_option('wqa_card_number', 'Введіть номер карти в налаштуваннях') . '</strong></p>
                <p>Після оплати надішліть чек на email: ' . get_option('admin_email') . ' та отримайте ліцензійний ключ</p>
            ',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ]);
    }
    
    // Встановлюємо дефолтні налаштування
    if (!get_option('wqa_card_number')) {
        update_option('wqa_card_number', '0000 0000 0000 0000'); // Замініть на свою картку
    }
    if (!get_option('wqa_operations')) update_option('wqa_operations', 0);
    if (!get_option('wqa_licenses')) update_option('wqa_licenses', []);
}

// ==================== АДМІН МЕНЮ ====================
add_action('admin_menu', function() {
    add_menu_page('WooQuick ADD', 'WooQuick ADD', 'manage_woocommerce', 'wqa_main', 'wqa_render_page', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wqa_license', 'wqa_license_page');
    add_submenu_page('wqa_main', '⚙️ Налаштування', '⚙️ Налаштування', 'manage_woocommerce', 'wqa_settings', 'wqa_settings_page');
    add_submenu_page('wqa_main', '📤 Експорт', '📤 Експорт', 'manage_woocommerce', 'wqa_export_page', 'wqa_export_admin_page');
});

// Сторінка налаштувань (тут вводимо номер карти)
function wqa_settings_page() {
    // Зберігаємо налаштування
    if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_settings')) {
        update_option('wqa_card_number', sanitize_text_field($_POST['card_number']));
        update_option('wqa_free_limit', intval($_POST['free_limit']));
        update_option('wqa_price', intval($_POST['price']));
        echo '<div class="notice notice-success"><p>✅ Налаштування збережено!</p></div>';
    }
    
    $card_number = get_option('wqa_card_number', '');
    $free_limit = get_option('wqa_free_limit', WQA_FREE_LIMIT);
    $price = get_option('wqa_price', WQA_PRICE);
    ?>
    <div class="wrap">
        <h1>⚙️ Налаштування WooQuick ADD Pro</h1>
        <form method="post">
            <?php wp_nonce_field('wqa_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>💳 Номер карти для прийому платежів</th>
                    <td>
                        <input type="text" name="card_number" value="<?php echo esc_attr($card_number); ?>" style="width:300px" placeholder="0000 0000 0000 0000">
                        <p class="description">Введіть номер вашої карти ПриватБанку або Monobank</p>
                    </td>
                </tr>
                <tr>
                    <th>💰 Ціна PRO версії (грн)</th>
                    <td>
                        <input type="number" name="price" value="<?php echo $price; ?>" style="width:100px">
                        <p class="description">Вартість ліцензії в гривнях</p>
                    </td>
                </tr>
                <tr>
                    <th>📊 Ліміт безкоштовних додавань</th>
                    <td>
                        <input type="number" name="free_limit" value="<?php echo $free_limit; ?>" style="width:100px">
                        <p class="description">Кількість товарів, які можна додати без ліцензії</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Зберегти налаштування', 'primary', 'save_settings'); ?>
        </form>
        
        <div style="margin-top:30px; padding:15px; background:#f0f0f0; border-radius:10px;">
            <h3>📋 Шорткод для сторінки продажу:</h3>
            <code style="display:block; padding:10px; background:#fff;">[wqa_buy_form]</code>
            <p>Додайте цей шорткод на будь-яку сторінку для відображення форми покупки</p>
        </div>
    </div>
    <?php
}

// Шорткод для форми покупки
add_shortcode('wqa_buy_form', 'wqa_buy_form');
function wqa_buy_form() {
    $card_number = get_option('wqa_card_number', 'Номер карти не вказано');
    $price = get_option('wqa_price', WQA_PRICE);
    $admin_email = get_option('admin_email');
    
    ob_start();
    ?>
    <div style="max-width:500px; margin:20px auto; padding:20px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>💰 Придбати WooQuick ADD Pro</h2>
        <p><strong>Ціна:</strong> <?php echo $price; ?> грн (одноразова оплата)</p>
        
        <h3>💳 Реквізити для оплати:</h3>
        <p>Картка: <strong><?php echo esc_html($card_number); ?></strong></p>
        <p>Отримувач: ФОП (ваше ПІБ)</p>
        <p>Призначення: WooQuick ADD Pro ліцензія</p>
        
        <h3>📝 Інструкція:</h3>
        <ol>
            <li>Переведіть <?php echo $price; ?> грн на вказану картку</li>
            <li>Збережіть чек/скріншот оплати</li>
            <li>Надішліть чек та ваш домен сайту на email: <strong><?php echo $admin_email; ?></strong></li>
            <li>Отримайте ліцензійний ключ протягом 24 годин</li>
        </ol>
        
        <div style="background:#e8f0fe; padding:15px; border-radius:8px; margin-top:15px;">
            <p>📧 Лист має містити:<br>
            - Скріншот оплати<br>
            - Домен сайту (наприклад, mysite.com)</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Сторінка ліцензії з генерацією ключів для адміна
function wqa_license_page() {
    $can_generate = current_user_can('administrator');
    $licenses = get_option('wqa_licenses', []);
    
    // Обробка генерації ключа
    if (isset($_POST['generate_key']) && $can_generate && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_gen')) {
        $new_key = wqa_generate_key();
        $licenses[$new_key] = [
            'status' => 'active',
            'created' => current_time('mysql'),
            'expires' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'domain' => sanitize_text_field($_POST['domain'] ?? '')
        ];
        update_option('wqa_licenses', $licenses);
        echo '<div class="notice notice-success"><p>✅ Згенеровано ключ: <code>' . $new_key . '</code></p></div>';
    }
    
    // Обробка деактивації ключа
    if (isset($_POST['deactivate_key']) && $can_generate && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_gen')) {
        $key_to_deactivate = $_POST['license_key'];
        if (isset($licenses[$key_to_deactivate])) {
            $licenses[$key_to_deactivate]['status'] = 'inactive';
            update_option('wqa_licenses', $licenses);
            echo '<div class="notice notice-warning"><p>🔄 Ключ деактивовано</p></div>';
        }
    }
    
    // Активація ліцензії користувачем
    if (isset($_POST['activate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_user')) {
        $entered_key = sanitize_text_field($_POST['license_key']);
        if (isset($licenses[$entered_key]) && $licenses[$entered_key]['status'] === 'active') {
            update_option('wqa_license_key', $entered_key);
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано! Дякуємо за покупку.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний або неактивний ключ</p></div>';
        }
    }
    
    $active_key = get_option('wqa_license_key', '');
    $is_pro = wqa_has_pro();
    ?>
    <div class="wrap">
        <h1>🔑 Ліцензія WooQuick ADD Pro</h1>
        
        <?php if ($is_pro): ?>
            <div class="notice notice-success">
                <p>✅ PRO версія активна! Ваш ключ: <code><?php echo esc_html($active_key); ?></code></p>
                <?php if (isset($licenses[$active_key]['expires'])): ?>
                    <p>📅 Дійсна до: <?php echo date_i18n(get_option('date_format'), strtotime($licenses[$active_key]['expires'])); ?></p>
                <?php endif; ?>
            </div>
            <form method="post">
                <?php wp_nonce_field('wqa_lic_user'); ?>
                <button type="submit" name="deactivate_license" class="button">🚫 Деактивувати ліцензію</button>
            </form>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>⚠️ Безкоштовна версія: залишилось <strong><?php echo wqa_remaining(); ?></strong> з <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?> додавань</p>
            </div>
            
            <div style="background:#f9f9f9; padding:20px; border-radius:10px; margin:20px 0;">
                <h3>Введіть ліцензійний ключ</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_lic_user'); ?>
                    <input type="text" name="license_key" placeholder="WQA-XXXXXXXX-XXXXXXXX" style="width:300px">
                    <button type="submit" name="activate_license" class="button button-primary">🔑 Активувати</button>
                </form>
                <p><a href="<?php echo home_url('/quick-add-instruction/'); ?>" class="button">💰 Придбати PRO (<?php echo get_option('wqa_price', WQA_PRICE); ?> грн)</a></p>
            </div>
            
            <?php if ($can_generate): ?>
            <div style="background:#e8f0fe; padding:20px; border-radius:10px; margin:20px 0;">
                <h3>🔧 Генерація ліцензійних ключів (тільки для адміністратора)</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_lic_gen'); ?>
                    <input type="text" name="domain" placeholder="Домен клієнта (необов'язково)" style="width:300px">
                    <button type="submit" name="generate_key" class="button">🔄 Згенерувати ключ</button>
                </form>
                
                <?php if (!empty($licenses)): ?>
                    <div style="margin-top:15px;">
                        <h4>Список ключів:</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr><th>Ключ</th><th>Домен</th><th>Статус</th><th>Створено</th><th>Діє до</th><th>Дія</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($licenses as $key => $lic): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($key); ?></code></td>
                                        <td><?php echo esc_html($lic['domain'] ?? '-'); ?></td>
                                        <td><?php echo $lic['status'] === 'active' ? '✅ Активний' : '❌ Неактивний'; ?></td>
                                        <td><?php echo $lic['created']; ?></td>
                                        <td><?php echo $lic['expires'] ?? '-'; ?></td>
                                        <td>
                                            <form method="post" style="margin:0">
                                                <?php wp_nonce_field('wqa_lic_gen'); ?>
                                                <input type="hidden" name="license_key" value="<?php echo esc_attr($key); ?>">
                                                <button type="submit" name="deactivate_key" class="button button-small">🚫 Деактивувати</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($_POST['deactivate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_user')): ?>
            <?php delete_option('wqa_license_key'); ?>
            <script>window.location.reload();</script>
        <?php endif; ?>
    </div>
    <?php
}

// ==================== КОРОТКИЙ КОД ДЛЯ ФОРМИ ЕКСПОРТУ ====================
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
            <button type="submit" name="wqa_full_export" class="button button-primary" style="font-size:16px; padding:10px 20px;">
                ⬇️ ЗАНТАЖИТИ CSV (ВСІ ТОВАРИ)
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ==================== ОБРОБКА ЕКСПОРТУ ====================
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
        if ($thumbnail_id) $image_urls[] = wp_get_attachment_url($thumbnail_id);
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gid) $image_urls[] = wp_get_attachment_url($gid);
        
        $attributes_data = [];
        foreach ($product->get_attributes() as $attr_name => $attr) {
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
            'Бренд' => implode(', ', wp_get_post_terms($pid, 'product_brand', ['fields' => 'names'])) ?: '',
            'Атрибути (JSON)' => json_encode($attributes_data, JSON_UNESCAPED_UNICODE),
            'Фото (URLs)' => implode('|', $image_urls),
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
    foreach ($csv_data as $row) fputcsv($output, $row);
    fclose($output);
    exit;
}

// ==================== АВТОМАТИЧНІ ОНОВЛЕННЯ ====================
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/" . WQA_GITHUB_REPO . "/releases/latest");
    if (is_wp_error($response)) return $transient;
    $release = json_decode(wp_remote_retrieve_body($response));
    if ($release && isset($release->tag_name)) {
        $latest = ltrim($release->tag_name, 'v');
        if (version_compare(WQA_VERSION, $latest, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest,
                'url' => $release->html_url,
                'package' => $release->zipball_url,
            ];
        }
    }
    return $transient;
});

// ==================== ГОЛОВНА СТОРІНКА (скорочена) ====================
function wqa_render_page() {
    ?>
    <div class="wrap" style="max-width:800px; margin:auto; padding:20px;">
        <h1>🎤 WooQuick ADD Pro v<?php echo WQA_VERSION; ?></h1>
        <div style="background:<?php echo wqa_has_pro() ? '#d4edda' : '#fff3cd'; ?>; padding:15px; border-radius:10px; margin:20px 0;">
            <?php if (wqa_has_pro()): ?>
                ✅ PRO версія активна - безлімітне додавання
            <?php else: ?>
                📊 Безкоштовно: <?php echo wqa_remaining(); ?> / <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?>
            <?php endif; ?>
        </div>
        <div><a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" class="button">🔑 Ліцензія</a>
        <a href="<?php echo admin_url('admin.php?page=wqa_export_page'); ?>" class="button">📤 Експорт</a></div>
    </div>
    <?php
}

function wqa_export_admin_page() {
    echo do_shortcode('[wqa_export_form]');
}

// AJAX збереження товару (скорочено)
add_action('wp_ajax_wqa_save_product', function() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) wp_send_json_error('Немає прав');
    if (!wqa_can()) wp_send_json_error('Ліміт вичерпано');
    
    // Базова обробка...
    wp_send_json_success(['id' => 1]);
});

register_deactivation_hook(__FILE__, function() {});
?>

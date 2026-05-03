<?php

/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 4.3
 * Description: Голосове додавання + Експорт товарів для WooCommerce
 * Author: UAServer
 * Author URI: https://uaserver.pp.ua/
 * GitHub Plugin URI: https://github.com/portallcomua/woo-quick-add-pro
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '4.3');
define('WQA_FREE_LIMIT', 25);
define('WQA_PRICE', 599);
define('WQA_GITHUB_REPO', 'portallcomua/woo-quick-add-pro');

// ==================== МОНЕТИЗАЦІЯ ====================
function wqa_has_pro() {
    $key = get_option('wqa_license_key', '');
    if (empty($key)) return false;
    $lics = get_option('wqa_licenses', []);
    if (isset($lics[$key]) && $lics[$key]['status'] === 'active') {
        if (isset($lics[$key]['expires']) && strtotime($lics[$key]['expires']) < time()) {
            return false;
        }
        return true;
    }
    return false;
}

function wqa_get_count() { return (int) get_option('wqa_operations', 0); }
function wqa_inc() { update_option('wqa_operations', wqa_get_count() + 1); }
function wqa_can() { return wqa_has_pro() ? true : wqa_get_count() < get_option('wqa_free_limit', WQA_FREE_LIMIT); }
function wqa_remaining() { return max(0, get_option('wqa_free_limit', WQA_FREE_LIMIT) - wqa_get_count()); }

function wqa_generate_key() {
    return 'WQA-' . strtoupper(uniqid()) . '-' . substr(md5(rand()), 0, 8);
}

// ==================== АВТОМАТИЧНЕ СТВОРЕННЯ СТОРІНОК ====================
register_activation_hook(__FILE__, 'wqa_activate_plugin');
function wqa_activate_plugin() {
    if (!get_page_by_path('export-products')) {
        wp_insert_post([
            'post_title'   => 'Експорт товарів',
            'post_name'    => 'export-products',
            'post_content' => '[wqa_export_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);
    }
    
    if (!get_page_by_path('quick-add-instruction')) {
        wp_insert_post([
            'post_title'   => 'Інструкція WooQuick Add',
            'post_name'    => 'quick-add-instruction',
            'post_content' => '
                <h2>🎤 Голосове додавання товарів</h2>
                <p>1. Натисніть кнопку мікрофона</p>
                <p>2. Скажіть: "назва футболка", "ціна 250", "опис крутий товар"</p>
                <p>3. Натисніть "Опублікувати"</p>
                <h2>💰 Придбання PRO версії</h2>
                <p>Ціна: <strong>' . get_option('wqa_price', WQA_PRICE) . ' грн</strong></p>
                <p>Картка: <strong>' . get_option('wqa_card_number', 'Введіть номер карти в налаштуваннях') . '</strong></p>
                <p>Після оплати надішліть чек на email: ' . get_option('admin_email') . '</p>
                <p>📞 Підтримка: <a href="https://uaserver.pp.ua/">UAServer</a></p>
            ',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ]);
    }
    
    if (!get_option('wqa_card_number')) update_option('wqa_card_number', '0000 0000 0000 0000');
    if (!get_option('wqa_free_limit')) update_option('wqa_free_limit', WQA_FREE_LIMIT);
    if (!get_option('wqa_price')) update_option('wqa_price', WQA_PRICE);
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

// Сторінка налаштувань
function wqa_settings_page() {
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
                        <input type="text" name="card_number" value="<?php echo esc_attr($card_number); ?>" style="width:300px">
                        <p class="description">Введіть номер вашої карти ПриватБанку або Monobank</p>
                      </td>
                  </tr>
                  <tr>
                    <th>💰 Ціна PRO версії (грн)</th>
                    <td>
                        <input type="number" name="price" value="<?php echo $price; ?>" style="width:100px">
                      </td>
                  </tr>
                  <tr>
                    <th>📊 Ліміт безкоштовних додавань</th>
                    <td>
                        <input type="number" name="free_limit" value="<?php echo $free_limit; ?>" style="width:100px">
                      </td>
                  </tr>
              </table>
            <?php submit_button('Зберегти налаштування', 'primary', 'save_settings'); ?>
        </form>
    </div>
    <?php
}

// Сторінка ліцензії
function wqa_license_page() {
    $can_generate = current_user_can('administrator');
    $licenses = get_option('wqa_licenses', []);
    
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
    
    if (isset($_POST['deactivate_key']) && $can_generate && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_gen')) {
        $key_to_deactivate = $_POST['license_key'];
        if (isset($licenses[$key_to_deactivate])) {
            $licenses[$key_to_deactivate]['status'] = 'inactive';
            update_option('wqa_licenses', $licenses);
            echo '<div class="notice notice-warning"><p>🔄 Ключ деактивовано</p></div>';
        }
    }
    
    if (isset($_POST['activate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_user')) {
        $entered_key = sanitize_text_field($_POST['license_key']);
        if (isset($licenses[$entered_key]) && $licenses[$entered_key]['status'] === 'active') {
            update_option('wqa_license_key', $entered_key);
            echo '<div class="notice notice-success"><p>✅ Ліцензію активовано!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Невірний або неактивний ключ</p></div>';
        }
    }
    
    if (isset($_POST['deactivate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'wqa_lic_user')) {
        delete_option('wqa_license_key');
        echo '<div class="notice notice-info"><p>🔄 Ліцензію деактивовано</p></div>';
    }
    
    $active_key = get_option('wqa_license_key', '');
    $is_pro = wqa_has_pro();
    ?>
    <div class="wrap">
        <h1>🔑 Ліцензія WooQuick ADD Pro</h1>
        
        <?php if ($is_pro): ?>
            <div class="notice notice-success">
                <p>✅ PRO версія активна! Ключ: <code><?php echo esc_html($active_key); ?></code></p>
            </div>
            <form method="post">
                <?php wp_nonce_field('wqa_lic_user'); ?>
                <button type="submit" name="deactivate_license" class="button">🚫 Деактивувати ліцензію</button>
            </form>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>⚠️ Безкоштовно: <strong><?php echo wqa_remaining(); ?></strong> / <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?></p>
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
            <div style="background:#e8f0fe; padding:20px; border-radius:10px;">
                <h3>🔧 Генерація ключів (тільки для адміна)</h3>
                <form method="post">
                    <?php wp_nonce_field('wqa_lic_gen'); ?>
                    <input type="text" name="domain" placeholder="Домен клієнта" style="width:300px">
                    <button type="submit" name="generate_key" class="button">🔄 Згенерувати ключ</button>
                </form>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ==================== ГОЛОВНА СТОРІНКА (ПОВНА ВЕРСІЯ) ====================
function wqa_render_page() {
    ?>
    <div class="wrap" style="max-width:800px; margin:auto; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>🎤 WooQuick ADD Pro v<?php echo WQA_VERSION; ?></h1>
            <span style="background:#2271b1; color:#fff; padding:4px 12px; border-radius:20px;">by UAServer</span>
        </div>

        <div style="background:<?php echo wqa_has_pro() ? '#d4edda' : '#fff3cd'; ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if (wqa_has_pro()): ?>
                ✅ PRO версія активна - безлімітне додавання
            <?php else: ?>
                📊 Безкоштовно: <strong><?php echo wqa_remaining(); ?></strong> / <?php echo get_option('wqa_free_limit', WQA_FREE_LIMIT); ?>
                <?php if (wqa_remaining() == 0): ?>
                    <br><a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" style="color:#d9534f;">💰 Придбати ліцензію →</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:15px;">
            <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" class="button">🔑 Ліцензія</a>
            <a href="<?php echo admin_url('admin.php?page=wqa_export_page'); ?>" class="button">📤 Експорт товарів</a>
            <a href="https://uaserver.pp.ua/" target="_blank" class="button">🌐 Сайт розробника</a>
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

// ==================== ЕКСПОРТ ====================
add_shortcode('wqa_export_form', 'wqa_export_form_shortcode');
function wqa_export_form_shortcode() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) {
        return '<p>Немає прав для експорту</p>';
    }
    
    ob_start();
    ?>
    <div style="max-width:600px; margin:20px auto; padding:20px; background:#fff; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <h2>📤 Експорт товарів у CSV</h2>
        <form method="post" action="">
            <?php wp_nonce_field('wqa_export', 'wqa_export_nonce'); ?>
            <button type="submit" name="wqa_full_export" class="button button-primary" style="font-size:16px; padding:10px 20px;">⬇️ ЗАНТАЖИТИ CSV</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function wqa_export_admin_page() {
    echo do_shortcode('[wqa_export_form]');
}

add_action('init', 'wqa_handle_export');
function wqa_handle_export() {
    if (!isset($_POST['wqa_full_export'])) return;
    if (!wp_verify_nonce($_POST['wqa_export_nonce'], 'wqa_export')) wp_die('Security check');
    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_products')) wp_die('No permission');
    
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    $csv_data = [];
    $headers = ['ID', 'SKU', 'Назва', 'Опис', 'Ціна', 'Категорії', 'Фото'];
    
    foreach ($products as $product) {
        $pid = $product->get_id();
        $image_urls = [];
        $thumbnail_id = $product->get_image_id();
        if ($thumbnail_id) $image_urls[] = wp_get_attachment_url($thumbnail_id);
        foreach ($product->get_gallery_image_ids() as $gid) $image_urls[] = wp_get_attachment_url($gid);
        
        $csv_data[] = [
            'ID' => $pid,
            'SKU' => $product->get_sku(),
            'Назва' => $product->get_name(),
            'Опис' => $product->get_description(),
            'Ціна' => $product->get_regular_price(),
            'Категорії' => implode(', ', wp_get_post_terms($pid, 'product_cat', ['fields' => 'names'])),
            'Фото' => implode('|', $image_urls),
        ];
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

// ==================== AJAX ЗБЕРЕЖЕННЯ ====================
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

register_deactivation_hook(__FILE__, function() {});
?>

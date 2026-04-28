<?php
/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 3.0
 * Description: Швидке додавання товарів з атрибутами та тегами через голос
 * Author: WooQuick
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '3.0');
define('WQA_FREE_LIMIT', 25);

// Функції ліцензії та ліміту
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

// Адмін меню
add_action('admin_menu', function() {
    add_menu_page('WooQuick ADD Pro', 'WooQuick ADD Pro', 'manage_options', 'wqa_main', 'wqa_render_gui', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_options', 'wqa_license', 'wqa_render_license_page');
});

// Сторінка ліцензії
function wqa_render_license_page() {
    ?>
    <div class="wrap" style="max-width:600px; margin:auto; padding:20px;">
        <h2>🔑 WooQuick ADD Pro - Ліцензія</h2>
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
            echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
        }
    }
    
    if (isset($_POST['wqa_request_payment']) && isset($_POST['buyer_email']) && wp_verify_nonce($_POST['wqa_request_nonce'], 'wqa_request_action')) {
        $buyer_email = sanitize_email($_POST['buyer_email']);
        wp_mail(get_option('admin_email'), 'Запит на ліцензію WooQuick ADD', "Email: $buyer_email");
        wp_mail($buyer_email, 'Інструкція з оплати', "Оплатіть 599 грн на картку...");
        echo '<div class="notice notice-success"><p>✅ Запит надіслано!</p></div>';
    }
});

// ========== ГОЛОВНА СТОРІНКА З МІКРОФОНОМ ТА ФОТО ==========
function wqa_render_gui() {
    $total_products = wqa_get_product_count();
    $remaining = wqa_get_remaining_free();
    $license_active = wqa_is_license_active();
    ?>
    <div class="wrap" style="max-width: 700px; margin: auto; padding: 20px; font-family: sans-serif;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #2271b1; margin-bottom: 20px;">
            <h1 style="color:#2271b1; margin:0;">🎤 WooQuick ADD Pro</h1>
            <span style="background:#2271b1; color:white; padding:4px 12px; border-radius:20px;">v<?php echo WQA_VERSION; ?></span>
        </div>
        
        <!-- Лічильник ліміту -->
        <div style="background: <?php echo $license_active ? '#d4edda' : ($remaining > 0 ? '#fff3cd' : '#f8d7da'); ?>; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
            <?php if ($license_active): ?>
                ✅ <strong>PRO ВЕРСІЯ</strong> - необмежено товарів<br>
                <span style="font-size:12px;">Додано товарів: <?php echo $total_products; ?></span>
            <?php elseif ($remaining > 0): ?>
                📊 <strong>Безкоштовна версія</strong><br>
                Додано: <?php echo $total_products; ?> з <?php echo WQA_FREE_LIMIT; ?> товарів<br>
                Залишилось: <strong><?php echo $remaining; ?></strong>
            <?php else: ?>
                🚫 <strong>Ліміт вичерпано!</strong><br>
                <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" style="color:#d9534f;">Придбати ліцензію →</a>
            <?php endif; ?>
        </div>
        
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <button id="btn_instructions" style="flex:1; background:#f0f0f0; border:1px solid #ccc; border-radius:10px; padding:10px;">📖 ІНСТРУКЦІЯ</button>
            <a href="<?php echo admin_url('admin.php?page=wqa_license'); ?>" style="flex:1; background:#4CAF50; color:#fff; border:none; border-radius:10px; padding:10px; text-align:center; text-decoration:none;">🔑 ЛІЦЕНЗІЯ</a>
        </div>
        
        <div id="message" style="display:none; background:#d4edda; padding:15px; border-radius:12px; margin-bottom:10px; color:#155724;"></div>
        <div id="error_msg" style="display:none; background:#f8d7da; padding:15px; border-radius:12px; margin-bottom:10px; color:#721c24;"></div>
        
        <!-- ВІДЕО ДЛЯ ФОТО -->
        <video id="video" width="100%" autoplay playsinline muted style="border-radius:15px; background:#000; margin-bottom:10px; transform: scaleX(-1);"></video>
        <div id="gallery" style="display:flex; gap:5px; padding:5px; overflow-x:auto; min-height: 80px;"></div>
        
        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <button id="take_photo" style="flex:2; background:#0073aa; color:#fff; border:none; border-radius:15px; padding:15px;">📷 ЗРОБИТИ ФОТО</button>
            <button id="clear_photos" style="flex:1; border-radius:15px; border:1px solid #ccc; background:#fff;">❌</button>
        </div>

        <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; border:none; border-radius:15px; padding:20px;">🎤 ДИКТУВАТИ ГОЛОСОМ</button>
        <textarea id="voice_text" style="width:100%; height:150px; border:2px solid #1877F2; border-radius:12px; padding:12px; margin-top:10px;"></textarea>
        <button id="save_btn" style="width:100%; background:#00a32a; color:#fff; border:none; border-radius:15px; font-size:18px; font-weight:bold; padding:20px; margin-top:15px;">➕ ОПУБЛІКУВАТИ</button>

        <div style="margin-top:15px; padding:10px; background:#f0f0f0; border-radius:10px; font-size:12px;">
            <strong>📝 Як диктувати:</strong><br>
            "назва велосипед"<br>
            "ціна 3500"<br>
            "категорія велосипеди"<br>
            "бренд UkrBike"<br>
            "мітка гірський"<br>
            "атрибут колір чорний"
        </div>

        <style>
            .photo-preview { width: 80px; height: 80px; object-fit: cover; border-radius: 10px; margin: 2px; border: 2px solid #ddd; cursor: pointer; }
        </style>

        <script>
        // ========== ЗМІННІ ==========
        const video = document.getElementById('video');
        const gallery = document.getElementById('gallery');
        const voiceText = document.getElementById('voice_text');
        let photos = [];
        let stream = null;
        let listening = false;
        
        // ========== КАМЕРА ==========
        async function startCamera() {
            try {
                if (stream) stream.getTracks().forEach(t => t.stop());
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                video.srcObject = stream;
            } catch(e) { console.log('Camera error:', e); }
        }
        
        startCamera();
        
        // Зробити фото
        document.getElementById('take_photo').onclick = () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const imgData = canvas.toDataURL('image/jpeg', 0.8);
            photos.push(imgData);
            
            const img = document.createElement('img');
            img.src = imgData;
            img.classList.add('photo-preview');
            gallery.prepend(img);
        };
        
        // Очистити фото
        document.getElementById('clear_photos').onclick = () => {
            photos = [];
            gallery.innerHTML = '';
        };
        
        // ========== ГОЛОСОВЕ ВВЕДЕННЯ ==========
        if (window.SpeechRecognition || window.webkitSpeechRecognition) {
            const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
            recognition.lang = 'uk-UA';
            recognition.continuous = true;
            
            document.getElementById('mic_btn').onclick = () => {
                if (!listening) {
                    recognition.start();
                    listening = true;
                    document.getElementById('mic_btn').style.background = "#ff4b4b";
                    document.getElementById('mic_btn').innerText = "🛑 ЗУПИНИТИ";
                } else {
                    recognition.stop();
                    listening = false;
                    document.getElementById('mic_btn').style.background = "#1877F2";
                    document.getElementById('mic_btn').innerText = "🎤 ДИКТУВАТИ ГОЛОСОМ";
                }
            };
            
            recognition.onresult = (e) => {
                const text = e.results[e.results.length-1][0].transcript;
                voiceText.value += (voiceText.value ? "\n" : "") + text;
                voiceText.scrollTop = voiceText.scrollHeight;
            };
            
            recognition.onend = () => {
                if (listening) recognition.start();
            };
        } else {
            document.getElementById('mic_btn').disabled = true;
            document.getElementById('mic_btn').innerText = "❌ ГОЛОС НЕ ПІДТРИМУЄТЬСЯ";
        }
        
        // ========== ЗБЕРЕЖЕННЯ ТОВАРУ ==========
        document.getElementById('save_btn').onclick = async function() {
            const btn = this;
            btn.disabled = true;
            btn.innerText = "⏳ ЗБЕРІГАЮ...";
            
            const formData = new FormData();
            formData.append('action', 'wqa_save_all');
            formData.append('text', voiceText.value);
            formData.append('images', JSON.stringify(photos));
            
            try {
                const response = await fetch(ajaxurl, { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('message').innerHTML = '✅ ТОВАР ДОДАНО! ID: ' + result.data.id;
                    document.getElementById('message').style.display = 'block';
                    document.getElementById('error_msg').style.display = 'none';
                    btn.innerText = "➕ ДОДАТИ ЩЕ";
                    btn.style.background = "#ff9800";
                    photos = [];
                    gallery.innerHTML = '';
                    voiceText.value = '';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    document.getElementById('error_msg').innerHTML = '❌ ' + result.data.message;
                    document.getElementById('error_msg').style.display = 'block';
                    btn.disabled = false;
                    btn.innerText = "➕ ОПУБЛІКУВАТИ";
                }
            } catch(e) {
                document.getElementById('error_msg').innerHTML = '❌ Помилка з\'єднання';
                document.getElementById('error_msg').style.display = 'block';
                btn.disabled = false;
                btn.innerText = "➕ ОПУБЛІКУВАТИ";
            }
        };
        
        document.getElementById('btn_instructions').onclick = () => {
            window.open('https://uaserver.pp.ua/readme_woo-quick-add', '_blank');
        };
        </script>
    </div>
    <?php
}

// ========== ЗБЕРЕЖЕННЯ ТОВАРУ ==========
add_action('wp_ajax_wqa_save_all', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Немає прав');
        return;
    }
    
    if (!wqa_can_add_product()) {
        wp_send_json_error('Ліміт безкоштовної версії вичерпано. <a href="' . admin_url('admin.php?page=wqa_license') . '">Придбайте ліцензію</a>');
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
    if (isset($data['вага'])) $product->set_weight(str_replace(',', '.', preg_replace('/[^0-9,]/', '', $data['вага'])));
    
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
    
    // Фото
    $imgs = json_decode(stripslashes($_POST['images']), true);
    if (!empty($imgs)) {
        $gallery_ids = [];
        foreach ($imgs as $idx => $base64) {
            if (strpos($base64, 'data:image') === 0) {
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
                $filename = 'wqa_product_' . $pid . '_' . time() . '_' . $idx . '.jpg';
                $upload = wp_upload_bits($filename, null, $imgData);
                if (!$upload['error']) {
                    $attach_id = wp_insert_attachment([
                        'post_mime_type' => 'image/jpeg',
                        'post_title' => 'Product Image',
                        'post_status' => 'inherit'
                    ], $upload['file'], $pid);
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));
                    if ($idx === 0) {
                        set_post_thumbnail($pid, $attach_id);
                    } else {
                        $gallery_ids[] = $attach_id;
                    }
                }
            }
        }
        if (!empty($gallery_ids)) update_post_meta($pid, '_product_image_gallery', implode(',', $gallery_ids));
    }
    
    wp_send_json_success(['id' => $pid]);
});
?>
<?php
/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 1.0
 * Description: Голосове додавання товарів для WooCommerce
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/woo-quick-add-pro
 */

if (!defined('ABSPATH')) exit;

define('WQA_VERSION', '1.0');
define('WQA_FREE_LIMIT', 25);
define('WQA_SHOP_URL', 'https://uaserver.pp.ua/product/wooquick-add-pro/');

add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/portallcomua/woo-quick-add-pro/releases/latest");
    if (is_wp_error($response)) return $transient;
    $release = json_decode(wp_remote_retrieve_body($response));
    if (isset($release->tag_name)) {
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

function wqa_get_count() { return (int) get_option('wqa_operations', 0); }
function wqa_inc() { update_option('wqa_operations', wqa_get_count() + 1); }
function wqa_can() { return get_option('wqa_license') ? true : wqa_get_count() < WQA_FREE_LIMIT; }
function wqa_remaining() { return max(0, WQA_FREE_LIMIT - wqa_get_count()); }

add_action('admin_menu', function() {
    add_menu_page('WooQuick ADD', 'WooQuick ADD', 'manage_woocommerce', 'wqa_main', 'wqa_page', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wqa_license', 'wqa_license_page');
});

function wqa_page() { echo '<div class="wrap"><h1>🎤 WooQuick ADD Pro</h1><p>Голосове додавання товарів. Ліміт: ' . wqa_remaining() . ' / ' . WQA_FREE_LIMIT . '</p></div>'; }

function wqa_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія WooQuick ADD Pro</h1>
    <?php if (get_option('wqa_license')): ?>
        <div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?>
        <div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo wqa_remaining(); ?> / <?php echo WQA_FREE_LIMIT; ?></p>
        <form method="post"><?php wp_nonce_field('wqa_lic'); ?>
            <input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button>
        </form>
        <p><a href="<?php echo WQA_SHOP_URL; ?>" target="_blank">💰 Придбати PRO (599 грн / $29)</a></p>
    <?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['wqa_lic'], 'wqa_lic')) {
        if (strlen(sanitize_text_field($_POST['license_key'])) >= 16) update_option('wqa_license', true);
        else echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
    }
});
?>
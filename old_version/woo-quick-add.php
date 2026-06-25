<?php
/**
 * Plugin Name: Woo Quick ADD
 * Plugin URI:  https://uaserver.pp.ua/woo-quick-add/
 * Description: Голосове додавання товарів у WooCommerce. Голос, фото, QR-код, атрибути, мітки, варіативні, CSV.
 * Version:     1.5.0
 * Author:      UAServer
 * Author URI:  https://uaserver.pp.ua/
 * License:     GPL v2 or later
 * Text Domain: woo-quick-add
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WQA_VERSION',    '1.5.0' );
define( 'WQA_FREE_LIMIT', 25 );
define( 'WQA_FILE',       __FILE__ );
define( 'WQA_GITHUB',     'portallcomua/woo-quick-add' );

// ── WooCommerce HPOS сумісність ────────────────────────────────────────
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WQA_FILE, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WQA_FILE, true );
    }
} );

// =====================================================================
// АВТООНОВЛЕННЯ З GITHUB
// =====================================================================
class WQA_Updater {
    private $file, $slug, $repo;
    public function __construct( $file, $repo ) {
        $this->file = $file;
        $this->slug = dirname( plugin_basename( $file ) );
        $this->repo = $repo;
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check' ] );
        add_filter( 'plugins_api', [ $this, 'info' ], 10, 3 );
    }
    public function check( $t ) {
        if ( empty( $t->checked ) ) return $t;
        $r = $this->remote();
        if ( $r && version_compare( WQA_VERSION, $r->version, '<' ) ) {
            $t->response[ plugin_basename( $this->file ) ] = (object)[
                'slug'        => $this->slug,
                'new_version' => $r->version,
                'package'     => $r->zip,
                'url'         => $r->url,
                'tested'      => '6.8',
            ];
        }
        return $t;
    }
    public function info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== $this->slug ) return $res;
        $r = $this->remote();
        if ( ! $r ) return $res;
        return (object)[ 'name' => 'Woo Quick ADD', 'slug' => $this->slug,
            'version' => $r->version, 'download_link' => $r->zip,
            'tested' => '6.8', 'requires_php' => '7.4',
            'sections' => [ 'description' => 'Голосове додавання товарів у WooCommerce.' ] ];
    }
    private function remote() {
        $resp = wp_remote_get( "https://api.github.com/repos/{$this->repo}/releases/latest",
            [ 'headers' => [ 'Accept' => 'application/json' ], 'timeout' => 5 ] );
        if ( is_wp_error( $resp ) ) return null;
        $d = json_decode( wp_remote_retrieve_body( $resp ) );
        if ( ! $d || ! isset( $d->tag_name ) ) return null;
        return (object)[ 'version' => ltrim( $d->tag_name, 'v' ), 'zip' => $d->zipball_url, 'url' => $d->html_url ];
    }
}
new WQA_Updater( WQA_FILE, WQA_GITHUB );

// =====================================================================
// ХЕЛПЕРИ
// =====================================================================
function wqa_en()    { return in_array( get_locale(), [ 'en_US','en_GB','en_CA','en_AU','en' ] ); }
function wqa_t($ua,$en) { return wqa_en() ? $en : $ua; }
function wqa_count() { return (int) get_option('wqa_ops', 0); }
function wqa_inc()   { update_option('wqa_ops', wqa_count() + 1); }
function wqa_limit() { return (int) get_option('wqa_free_limit', WQA_FREE_LIMIT); }
function wqa_left()  { return max(0, wqa_limit() - wqa_count()); }
function wqa_can()   { return wqa_pro() || wqa_count() < wqa_limit(); }
function wqa_sysinfo() {
    global $wp_version;
    return "WP:{$wp_version} WC:".(class_exists('WooCommerce')?WC()->version:'n/a')
          ." PHP:".PHP_VERSION." Plugin:".WQA_VERSION
          ." Lic:".(wqa_pro()?'PRO':'Free '.wqa_count().'/'.wqa_limit())
          ." ".home_url();
}

// =====================================================================
// ЛІЦЕНЗІЯ — LEMON SQUEEZY
// =====================================================================
function wqa_pro() {
    $key = get_option('wqa_license_key','');
    if (!$key) return false;
    $c = get_transient('wqa_lic_'.md5($key));
    if ($c !== false) return $c === '1';
    $ok = wqa_ls_validate($key);
    set_transient('wqa_lic_'.md5($key), $ok?'1':'0', 12*HOUR_IN_SECONDS);
    return $ok;
}
function wqa_ls_validate($key) {
    $api = get_option('wqa_ls_api','');
    if (!$api) {
        $m = get_option('wqa_manual_lic',[]);
        return isset($m[$key]) && $m[$key]==='active';
    }
    $r = wp_remote_post('https://api.lemonsqueezy.com/v1/licenses/validate',[
        'headers'=>['Authorization'=>'Bearer '.$api,'Accept'=>'application/json','Content-Type'=>'application/json'],
        'body'=>json_encode(['license_key'=>$key]), 'timeout'=>8,
    ]);
    if (is_wp_error($r)) return false;
    $b = json_decode(wp_remote_retrieve_body($r),true);
    return !empty($b['valid']);
}
function wqa_ls_activate($key) {
    $api = get_option('wqa_ls_api','');
    if (!$api) {
        $m = get_option('wqa_manual_lic',[]);
        if (isset($m[$key]) && $m[$key]==='active') {
            update_option('wqa_license_key',$key);
            delete_transient('wqa_lic_'.md5($key));
            return ['ok'=>true];
        }
        return ['ok'=>false,'err'=>wqa_t('Невірний ключ','Invalid key')];
    }
    $r = wp_remote_post('https://api.lemonsqueezy.com/v1/licenses/activate',[
        'headers'=>['Authorization'=>'Bearer '.$api,'Accept'=>'application/json','Content-Type'=>'application/json'],
        'body'=>json_encode(['license_key'=>$key,'instance_name'=>home_url()]), 'timeout'=>8,
    ]);
    if (is_wp_error($r)) return ['ok'=>false,'err'=>'Connection error'];
    $b = json_decode(wp_remote_retrieve_body($r),true);
    if (!empty($b['activated'])) {
        update_option('wqa_license_key',$key);
        update_option('wqa_license_iid',$b['instance']['id']??'');
        delete_transient('wqa_lic_'.md5($key));
        return ['ok'=>true];
    }
    return ['ok'=>false,'err'=>$b['error']??wqa_t('Помилка','Error')];
}
function wqa_ls_deactivate($key) {
    $api = get_option('wqa_ls_api','');
    $iid = get_option('wqa_license_iid','');
    if ($api && $iid) {
        wp_remote_post('https://api.lemonsqueezy.com/v1/licenses/deactivate',[
            'headers'=>['Authorization'=>'Bearer '.$api,'Accept'=>'application/json','Content-Type'=>'application/json'],
            'body'=>json_encode(['license_key'=>$key,'instance_id'=>$iid]), 'timeout'=>8,
        ]);
    }
    delete_option('wqa_license_key'); delete_option('wqa_license_iid');
    delete_transient('wqa_lic_'.md5($key));
}

register_activation_hook(__FILE__, function(){
    add_option('wqa_free_limit', WQA_FREE_LIMIT);
    add_option('wqa_ops', 0);
    add_option('wqa_manual_lic', []);
});

// =====================================================================
// МЕНЮ
// =====================================================================
add_action('admin_menu', function(){
    add_menu_page('Woo Quick ADD','Woo Quick ADD','manage_woocommerce','wqa_main','wqa_page_main','dashicons-microphone',30);
    add_submenu_page('wqa_main',wqa_t('Ліцензія','License'),'🔑 '.wqa_t('Ліцензія','License'),'manage_woocommerce','wqa_license','wqa_page_license');
    add_submenu_page('wqa_main',wqa_t('Налаштування','Settings'),'⚙️ '.wqa_t('Налаштування','Settings'),'manage_woocommerce','wqa_settings','wqa_page_settings');
    add_submenu_page('wqa_main',wqa_t('Підтримка','Support'),'📞 '.wqa_t('Підтримка','Support'),'manage_woocommerce','wqa_support','wqa_page_support');
});

// =====================================================================
// НАЛАШТУВАННЯ
// =====================================================================
function wqa_page_settings(){
    if (isset($_POST['wqa_save']) && check_admin_referer('wqa_cfg')) {
        update_option('wqa_free_limit', max(1,(int)$_POST['free_limit']));
        update_option('wqa_ls_api',  sanitize_text_field($_POST['ls_api']));
        update_option('wqa_ls_vid',  sanitize_text_field($_POST['ls_vid']));
        update_option('wqa_rbg_api', sanitize_text_field($_POST['rbg_api']));
        echo '<div class="notice notice-success"><p>✅ '.wqa_t('Збережено! Оновіть сторінку.','Saved! Refresh the page.').'</p></div>';
    }
    if (isset($_POST['wqa_gen_key']) && current_user_can('administrator') && check_admin_referer('wqa_cfg')) {
        $key = 'WQA-'.strtoupper(substr(md5(uniqid(mt_rand(),true)),0,8)).'-'.strtoupper(substr(md5(rand()),0,8));
        $m = get_option('wqa_manual_lic',[]); $m[$key]='active'; update_option('wqa_manual_lic',$m);
        echo '<div class="notice notice-success"><p>✅ '.wqa_t('Ключ:','Key:').' <code>'.$key.'</code></p></div>';
    }
    $manual = get_option('wqa_manual_lic',[]);
    $rbg    = get_option('wqa_rbg_api','');
?>
<div class="wrap">
<h1>⚙️ <?php echo wqa_t('Налаштування Woo Quick ADD','Woo Quick ADD Settings')?></h1>
<form method="post"><?php wp_nonce_field('wqa_cfg')?>
<h2><?php echo wqa_t('Загальні','General')?></h2>
<table class="form-table">
    <tr><th><?php echo wqa_t('Безкоштовний ліміт','Free limit')?></th>
        <td><input type="number" name="free_limit" value="<?php echo esc_attr(get_option('wqa_free_limit',WQA_FREE_LIMIT))?>" style="width:100px"> <?php echo wqa_t('товарів','products')?></td></tr>
</table>
<h2>🍋 Lemon Squeezy</h2>
<p><?php echo wqa_t('Реєстрація:','Register:')?> <a href="https://lemonsqueezy.com" target="_blank">lemonsqueezy.com</a> — <?php echo wqa_t('безкоштовно, 5%+$0.5 комісія','free, 5%+$0.5 commission')?></p>
<table class="form-table">
    <tr><th>API Key</th>
        <td><input type="password" name="ls_api" value="<?php echo esc_attr(get_option('wqa_ls_api',''))?>" style="width:420px" placeholder="eyJ...">
        <p class="description"><?php echo wqa_t('Settings → API → "+" → скопіюй ключ (одноразово!)','Settings → API → "+" → copy key (one time!)')?></p></td></tr>
    <tr><th>Variant ID</th>
        <td><input type="text" name="ls_vid" value="<?php echo esc_attr(get_option('wqa_ls_vid',''))?>" style="width:200px">
        <p class="description"><?php echo wqa_t('Продукт → Variants → ID варіанту','Product → Variants → variant ID')?></p></td></tr>
</table>
<h2>🖼️ Remove.bg</h2>
<div style="background:#f0f8ff;padding:12px 16px;border-radius:8px;margin-bottom:12px;font-size:13px;line-height:1.9">
    <b><?php echo wqa_t('Як отримати безкоштовний ключ (50 фото/міс):','How to get free key (50 photos/month):')?></b><br>
    1. <?php echo wqa_t('Зареєструйтесь на','Register at')?> <a href="https://www.remove.bg/api" target="_blank"><b>remove.bg/api</b></a><br>
    2. <?php echo wqa_t('Перейдіть','Go to')?> <b>API Keys → New API Key</b><br>
    3. <?php echo wqa_t('Скопіюйте ключ і вставте нижче, збережіть — і вибір фону з\'явиться на головній сторінці','Copy key, paste below, save — background selector will appear on main page')?>
</div>
<table class="form-table">
    <tr><th>Remove.bg API Key</th>
        <td><input type="<?php echo $rbg?'password':'text'?>" name="rbg_api" value="<?php echo esc_attr($rbg)?>" style="width:340px" placeholder="tSZ2c5cR...">
        <?php if($rbg): ?><span style="color:#198754;margin-left:8px">✅ <?php echo wqa_t('Активно','Active')?></span>
        <?php else: ?><span style="color:#888;margin-left:8px"><?php echo wqa_t('Не введено','Not set')?></span><?php endif?>
        </td></tr>
</table>
<input type="hidden" name="wqa_save" value="1">
<?php submit_button(wqa_t('Зберегти','Save'))?>
</form>
<hr>
<h2><?php echo wqa_t('🔑 Ручні ліцензії','🔑 Manual licenses')?></h2>
<form method="post"><?php wp_nonce_field('wqa_cfg')?>
    <button type="submit" name="wqa_gen_key" class="button">🔄 <?php echo wqa_t('Згенерувати ключ','Generate key')?></button>
</form>
<?php if($manual): ?>
<table class="widefat" style="margin-top:12px;max-width:620px">
    <thead><tr><th><?php echo wqa_t('Ключ','Key')?></th><th>Status</th></tr></thead>
    <tbody><?php foreach($manual as $k=>$s): ?>
        <tr><td><code><?php echo esc_html($k)?></code></td><td><?php echo esc_html($s)?></td></tr>
    <?php endforeach?></tbody>
</table>
<?php endif?>
</div>
<?php }

// =====================================================================
// ПІДТРИМКА
// =====================================================================
function wqa_page_support(){
    if (isset($_POST['wqa_ticket']) && check_admin_referer('wqa_tkt')) {
        wp_mail('support@uaserver.pp.ua','[WooQuickADD] '.sanitize_text_field($_POST['subject']),
            "From: ".sanitize_email($_POST['email'])."\n\n".sanitize_textarea_field($_POST['message'])."\n\n---\n".wqa_sysinfo());
        echo '<div class="notice notice-success"><p>✅ '.wqa_t('Надіслано!','Sent!').'</p></div>';
    }
?>
<div class="wrap"><h1>📞 <?php echo wqa_t('Підтримка','Support')?></h1>
<div style="background:#f0f8ff;padding:14px;border-radius:10px;margin-bottom:20px">
    <h3><?php echo wqa_t('Системна інформація','System info')?></h3>
    <pre id="wsi" style="background:#fff;padding:10px;overflow:auto"><?php echo esc_html(wqa_sysinfo())?></pre>
    <button onclick="navigator.clipboard.writeText(document.getElementById('wsi').innerText);alert('OK')" class="button">📋 Copy</button>
</div>
<form method="post"><?php wp_nonce_field('wqa_tkt')?>
<table class="form-table">
    <tr><th>Email</th><td><input type="email" name="email" required style="width:100%"></td></tr>
    <tr><th><?php echo wqa_t('Тема','Subject')?></th><td><input type="text" name="subject" required style="width:100%"></td></tr>
    <tr><th><?php echo wqa_t('Повідомлення','Message')?></th><td><textarea name="message" rows="5" required style="width:100%"></textarea></td></tr>
</table>
<input type="hidden" name="wqa_ticket" value="1">
<?php submit_button(wqa_t('📧 Надіслати','📧 Send'),'primary')?>
</form></div>
<?php }

// =====================================================================
// ЛІЦЕНЗІЯ
// =====================================================================
function wqa_page_license(){
    $pro = wqa_pro();
    if (isset($_POST['wqa_activate']) && check_admin_referer('wqa_lic_u')) {
        $r = wqa_ls_activate(sanitize_text_field($_POST['lic_key']));
        if ($r['ok']) { $pro=true; echo '<div class="notice notice-success"><p>✅ '.wqa_t('Ліцензію активовано!','License activated!').'</p></div>'; }
        else           echo '<div class="notice notice-error"><p>❌ '.esc_html($r['err']).'</p></div>';
    }
    if (isset($_POST['wqa_deactivate']) && check_admin_referer('wqa_lic_u')) {
        wqa_ls_deactivate(get_option('wqa_license_key',''));
        $pro=false;
        echo '<div class="notice notice-info"><p>'.wqa_t('Деактивовано','Deactivated').'</p></div>';
    }
    $vid = get_option('wqa_ls_vid','');
    $buy = $vid ? "https://store.lemonsqueezy.com/checkout/buy/{$vid}" : '';
?>
<div class="wrap"><h1>🔑 <?php echo wqa_t('Ліцензія Woo Quick ADD','Woo Quick ADD License')?></h1>
<?php if($pro): ?>
    <div class="notice notice-success"><p>✅ PRO <?php echo wqa_t('— безлімітне додавання','— unlimited')?></p></div>
    <form method="post"><?php wp_nonce_field('wqa_lic_u')?>
    <button type="submit" name="wqa_deactivate" class="button">🚫 <?php echo wqa_t('Деактивувати','Deactivate')?></button></form>
<?php else: ?>
    <div class="notice notice-warning"><p>⚠️ <?php echo wqa_t('Залишилось:','Remaining:')?> <strong><?php echo wqa_left()?></strong>/<?php echo wqa_limit()?></p></div>
    <div style="background:#f9f9f9;padding:20px;border-radius:10px;margin:16px 0;max-width:500px">
        <h3><?php echo wqa_t('Введіть ліцензійний ключ','Enter license key')?></h3>
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap"><?php wp_nonce_field('wqa_lic_u')?>
        <input type="text" name="lic_key" placeholder="WQA-XXXXXXXX-XXXXXXXX" style="width:260px;padding:7px 10px">
        <button type="submit" name="wqa_activate" class="button button-primary">🔑 <?php echo wqa_t('Активувати','Activate')?></button>
        </form>
    </div>
    <div style="background:#e8f4fd;padding:20px;border-radius:10px;max-width:500px">
        <h3>💰 PRO — $29 / 599 <?php echo wqa_t('грн','UAH')?></h3>
        <ul style="margin:8px 0 14px 18px">
            <li><?php echo wqa_t('Необмежена кількість товарів','Unlimited products')?></li>
            <li><?php echo wqa_t('Варіативні товари з різними цінами','Variable products with different prices')?></li>
            <li><?php echo wqa_t('Довічна ліцензія + оновлення','Lifetime + updates')?></li>
        </ul>
        <?php if($buy): ?>
        <a href="<?php echo esc_url($buy)?>" target="_blank"
           style="display:inline-block;background:#ff6b35;color:#fff;padding:13px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px">
           🛒 <?php echo wqa_t('Придбати зараз','Buy now')?></a>
        <?php else: ?>
        <p class="description"><?php echo wqa_t('Вставте Variant ID у ⚙️ Налаштуваннях','Set Variant ID in ⚙️ Settings')?></p>
        <?php endif?>
    </div>
<?php endif?>
</div>
<?php }

// =====================================================================
// ГОЛОВНА СТОРІНКА
// =====================================================================
function wqa_page_main(){
    $en     = wqa_en();
    $pro    = wqa_pro();
    $has_rbg = (bool) get_option('wqa_rbg_api','');

    $fields = $en
        ? ['NAME','BRAND','PRICE','SALE','SKU','WEIGHT','CATEGORY','TAGS','DESCRIPTION']
        : ['НАЗВА','БРЕНД','ЦІНА','АКЦІЯ','АРТИКУЛ','ВАГА','КАТЕГОРІЯ','МІТКИ','ОПИС'];

    // Маяки для textarea
    $template = implode("\n", array_map(fn($f) => $f.': ', $fields));

    // voiceMap — передаємо в JS як JSON
    $vm_ua = [
        'назва'=>['field'=>'НАЗВА','type'=>'simple'],
        'найменування'=>['field'=>'НАЗВА','type'=>'simple'],
        'бренд'=>['field'=>'БРЕНД','type'=>'simple'],
        'марка'=>['field'=>'БРЕНД','type'=>'simple'],
        'ціна'=>['field'=>'ЦІНА','type'=>'simple'],
        'вартість'=>['field'=>'ЦІНА','type'=>'simple'],
        'акція'=>['field'=>'АКЦІЯ','type'=>'simple'],
        'знижка'=>['field'=>'АКЦІЯ','type'=>'simple'],
        'артикул'=>['field'=>'АРТИКУЛ','type'=>'simple'],
        'вага'=>['field'=>'ВАГА','type'=>'simple'],
        'категорія'=>['field'=>'КАТЕГОРІЯ','type'=>'simple'],
        'опис'=>['field'=>'ОПИС','type'=>'simple'],
        'описую'=>['field'=>'ОПИС','type'=>'simple'],
        'мітка'=>['field'=>'МІТКИ','type'=>'append'],
        'мітки'=>['field'=>'МІТКИ','type'=>'append'],
        'тег'=>['field'=>'МІТКИ','type'=>'append'],
        'теги'=>['field'=>'МІТКИ','type'=>'append'],
        'атрибут'=>['field'=>null,'type'=>'attr'],
        'варіація'=>['field'=>null,'type'=>'variation'],
    ];
    $vm_en = [
        'name'=>['field'=>'NAME','type'=>'simple'],
        'brand'=>['field'=>'BRAND','type'=>'simple'],
        'price'=>['field'=>'PRICE','type'=>'simple'],
        'sale'=>['field'=>'SALE','type'=>'simple'],
        'discount'=>['field'=>'SALE','type'=>'simple'],
        'sku'=>['field'=>'SKU','type'=>'simple'],
        'weight'=>['field'=>'WEIGHT','type'=>'simple'],
        'category'=>['field'=>'CATEGORY','type'=>'simple'],
        'description'=>['field'=>'DESCRIPTION','type'=>'simple'],
        'desc'=>['field'=>'DESCRIPTION','type'=>'simple'],
        'tag'=>['field'=>'TAGS','type'=>'append'],
        'tags'=>['field'=>'TAGS','type'=>'append'],
        'attribute'=>['field'=>null,'type'=>'attr'],
        'attr'=>['field'=>null,'type'=>'attr'],
        'variation'=>['field'=>null,'type'=>'variation'],
    ];
    $vm = $en ? $vm_en : $vm_ua;
?>
<style>
@keyframes wqa_pulse{0%,100%{opacity:1}50%{opacity:.5}}
#wqa_w *{box-sizing:border-box}
.wqa_btn{cursor:pointer;border:none;color:#fff;font-weight:bold;border-radius:8px;padding:12px 14px}
</style>

<div id="wqa_w" style="max-width:800px;margin:auto;padding:20px 14px">

<!-- ШАПКА -->
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px">
    <h1 style="margin:0">🎤 Woo Quick ADD <sup style="font-size:11px;color:#aaa;font-weight:400">v<?php echo WQA_VERSION?></sup></h1>
    <div style="display:flex;gap:5px;flex-wrap:wrap">
        <a href="<?php echo admin_url('admin.php?page=wqa_license')?>" class="button">🔑</a>
        <a href="<?php echo admin_url('admin.php?page=wqa_settings')?>" class="button">⚙️</a>
        <a href="https://uaserver.pp.ua/woo-quick-add/" target="_blank" class="button">📖</a>
        <button id="btn_export" class="wqa_btn" style="background:#0d6efd;font-size:13px">📤 CSV</button>
    </div>
</div>

<!-- БЕЙДЖ -->
<div style="background:<?php echo $pro?'#d1e7dd':'#fff3cd'?>;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:14px">
    <?php if($pro): ?>✅ PRO <?php echo wqa_t('— безлімітно','— unlimited')?>
    <?php else: ?>📊 <?php echo wqa_t('Залишилось:','Left:')?> <strong><?php echo wqa_left()?></strong>/<?php echo wqa_limit()?>
    <?php if(!wqa_can()): ?> &nbsp;<a href="<?php echo admin_url('admin.php?page=wqa_license')?>" style="color:#c00;font-weight:bold">💰 <?php echo wqa_t('Купити →','Buy →')?></a><?php endif?>
    <?php endif?>
</div>

<!-- КАМЕРА -->
<video id="wqa_cam" autoplay playsinline muted style="width:100%;background:#111;border-radius:10px;margin-bottom:6px;max-height:280px;object-fit:cover"></video>
<div id="wqa_gal" style="display:flex;gap:4px;flex-wrap:wrap;min-height:2px;margin-bottom:8px"></div>

<!-- ВИБІР ФОНУ (тільки якщо є remove.bg ключ) -->
<?php if($has_rbg): ?>
<div id="wqa_bg_wrap" style="background:#f8f9fa;border-radius:10px;padding:10px 14px;margin-bottom:10px;display:none">
    <div style="font-size:13px;font-weight:bold;margin-bottom:8px">🖼️ <?php echo wqa_t('Фон після видалення:','Background after removal:')?></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="original" checked> <?php echo wqa_t('Оригінал','Original')?></label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="transparent">
            <span style="width:18px;height:18px;background:repeating-conic-gradient(#ccc 0% 25%,#fff 0% 50%) 0/8px 8px;border:1px solid #aaa;border-radius:3px;display:inline-block"></span>
            <?php echo wqa_t('Прозорий','Transparent')?></label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="ffffff"><span style="width:18px;height:18px;background:#fff;border:1px solid #aaa;border-radius:3px;display:inline-block"></span><?php echo wqa_t('Білий','White')?></label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="000000"><span style="width:18px;height:18px;background:#000;border:1px solid #aaa;border-radius:3px;display:inline-block"></span><?php echo wqa_t('Чорний','Black')?></label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="cccccc"><span style="width:18px;height:18px;background:#ccc;border:1px solid #aaa;border-radius:3px;display:inline-block"></span><?php echo wqa_t('Сірий','Gray')?></label>
        <label style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:13px"><input type="radio" name="wqa_bg" value="custom"><?php echo wqa_t('Колір:','Color:')?> <input type="color" id="wqa_cc" value="#ff0000" style="width:32px;height:22px;padding:0;border:1px solid #ccc;margin-left:3px"></label>
    </div>
    <div id="wqa_bg_st" style="margin-top:6px;font-size:12px;color:#666;display:none"></div>
</div>
<?php endif?>

<!-- КНОПКИ ФОТО -->
<div style="display:flex;gap:8px;margin-bottom:12px">
    <button id="btn_photo" class="wqa_btn" style="flex:3;background:#2271b1;font-size:15px">📷 <?php echo wqa_t('ФОТО','PHOTO')?></button>
    <button id="btn_clr" class="wqa_btn" style="flex:1;background:#6c757d;font-size:14px">❌</button>
</div>

<!-- СТАТУС ГОЛОСУ -->
<div id="wqa_bar" style="display:none;padding:12px 16px;border-radius:9px;font-size:15px;font-weight:bold;text-align:center;color:#fff;margin-bottom:8px"></div>

<!-- TEXTAREA З МАЯКАМИ (вшиті в PHP) -->
<label style="font-weight:bold;display:block;margin-bottom:5px">
    📝 <?php echo wqa_t('ДАНІ ТОВАРУ — говоріть: маяк → значення','PRODUCT DATA — say: beacon → value')?>
</label>
<textarea id="wqa_ta" rows="<?php echo count($fields)?>"
    style="width:100%;font-family:monospace;font-size:14px;padding:10px 12px;border:2px solid #2271b1;border-radius:10px;line-height:1.8;resize:vertical"><?php echo esc_textarea($template)?></textarea>

<!-- АТРИБУТИ -->
<div style="margin-top:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
        <label style="font-weight:bold">🏷️ <?php echo wqa_t('АТРИБУТИ','ATTRIBUTES')?></label>
        <button id="btn_add_attr" class="wqa_btn" style="background:#6f42c1;font-size:13px;padding:7px 13px">+ <?php echo wqa_t('Додати','Add')?></button>
    </div>
    <p style="margin:0 0 6px;font-size:12px;color:#777"><?php echo wqa_t(
        'Голос: «атрибут колір червоний» — додає. «атрибут колір синій» — допише синій до Кольору.',
        'Voice: "attribute color red" adds. "attribute color blue" appends blue to Color.'
    )?></p>
    <div id="wqa_attrs"></div>
</div>

<!-- КНОПКИ ДІЙ -->
<div style="display:flex;gap:8px;margin-top:14px">
    <button id="btn_mic" class="wqa_btn" style="flex:2;background:#1877F2;font-size:16px;padding:14px">🎤 <?php echo wqa_t('ДИКТУВАТИ','VOICE INPUT')?></button>
    <button id="btn_qr"  class="wqa_btn" style="flex:1;background:#6f42c1;font-size:15px;padding:14px">📷 QR</button>
</div>

<!-- ВАРІАТИВНІ PRO -->
<?php if($pro): ?>
<div style="margin-top:12px;padding:14px;background:#f8f0ff;border-radius:10px;border:2px solid #6f42c1">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <label style="font-weight:bold"><?php echo wqa_t('Тип товару:','Product type:')?></label>
        <select id="wqa_ptype" style="padding:7px 12px;border-radius:7px;border:2px solid #6f42c1;font-size:14px">
            <option value="simple"><?php echo wqa_t('Простий','Simple')?></option>
            <option value="variable"><?php echo wqa_t('Варіативний (PRO)','Variable (PRO)')?></option>
        </select>
    </div>
    <div id="wqa_vwrap" style="display:none">
        <label style="font-weight:bold;display:block;margin-bottom:5px">
            🔀 <?php echo wqa_t('Варіації та ціни:','Variations and prices:')?>
        </label>
        <div id="wqa_var_rows"></div>
        <button id="btn_add_var" class="wqa_btn" style="background:#6f42c1;font-size:13px;padding:7px 13px;margin-top:6px">+ <?php echo wqa_t('Додати варіацію','Add variation')?></button>
        <p style="font-size:12px;color:#777;margin:6px 0 0"><?php echo wqa_t(
            'Кожна варіація — це наприклад Колір або Розмір. Для кожного значення можна вказати свою ціну.',
            'Each variation is e.g. Color or Size. Each value can have its own price.'
        )?></p>
        <p style="font-size:12px;color:#777;margin:4px 0 0"><?php echo wqa_t(
            'Голос: «варіація колір червоний синій» або «варіація розмір S M L».',
            'Voice: "variation color red blue" or "variation size S M L".'
        )?></p>
    </div>
</div>
<?php endif?>

<!-- ОПУБЛІКУВАТИ -->
<button id="btn_save" class="wqa_btn" style="width:100%;background:#00a32a;font-size:18px;padding:16px;margin-top:14px">
    ➕ <?php echo wqa_t('ОПУБЛІКУВАТИ','PUBLISH')?>
</button>

<!-- ПОВІДОМЛЕННЯ -->
<div id="wqa_msg" style="display:none;margin-top:12px;padding:12px 14px;border-radius:8px;font-size:14px"></div>

<!-- ІНСТРУКЦІЯ -->
<details style="margin-top:18px;background:#f8f9fa;border-radius:10px;padding:12px 16px">
<summary style="cursor:pointer;font-weight:bold;font-size:14px">📋 <?php echo wqa_t('Як диктувати — інструкція','How to dictate — instructions')?></summary>
<div style="margin-top:10px;font-size:13px;line-height:1.9">
<?php if(!$en): ?>
<b>ПРАВИЛО:</b> Натисніть 🎤 → скажіть <b>маяк</b> → <b>пауза</b> → скажіть <b>значення</b>. Або одразу: «назва штани».<br><br>
<table style="border-collapse:collapse;width:100%;font-size:12px">
<tr style="background:#dee2e6"><th style="padding:4px 8px;text-align:left">Маяк</th><th style="padding:4px 8px;text-align:left">Приклад</th><th style="padding:4px 8px;text-align:left">Результат</th></tr>
<?php foreach([
['«назва»','«назва Штани»','НАЗВА: Штани'],
['«ціна»','«ціна 850»','ЦІНА: 850'],
['«акція»','«акція 600»','АКЦІЯ: 600'],
['«бренд»','«бренд Nike»','БРЕНД: Nike'],
['«категорія»','«категорія Одяг»','КАТЕГОРІЯ: Одяг'],
['«мітка»','«мітка новинка» → «мітка хіт»','МІТКИ: новинка, хіт'],
['«атрибут назва значення»','«атрибут колір червоний»','Атрибут Колір: червоний'],
['«варіація назва знач»','«варіація розмір S M L»','Варіація Розмір: S, M, L'],
['«опис»','«опис Якісний матеріал»','ОПИС: ...'],
] as $r): ?>
<tr style="border-bottom:1px solid #eee">
    <td style="padding:4px 8px;font-family:monospace"><?php echo $r[0]?></td>
    <td style="padding:4px 8px;color:#0d6efd"><?php echo $r[1]?></td>
    <td style="padding:4px 8px;color:#198754"><?php echo $r[2]?></td>
</tr>
<?php endforeach?>
</table>
<br><b>QR для тесту:</b> відкрийте <a href="https://qr.io" target="_blank">qr.io</a> на ПК, вставте текст: <code>НАЗВА: Штани&#10;ЦІНА: 850&#10;БРЕНД: Nike</code> — і скануйте телефоном.
<?php else: ?>
<b>RULE:</b> Tap 🎤 → say <b>beacon</b> → <b>pause</b> → say <b>value</b>. Or at once: «name jeans».<br>
Beacons: name, price, sale, brand, sku, weight, category, tags, description<br>
Attributes: «attribute color red» / «attribute color» → pause → «red blue»<br>
Variations (Pro): «variation color red blue» / «variation size S M L»<br>
Tags: «tag new» → «tag sale» → TAGS: new, sale
<?php endif?>
</div>
</details>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
'use strict';

// ── КОНФІГ ────────────────────────────────────────────────────────────
const EN      = <?php echo $en?'true':'false'?>;
const PRO     = <?php echo $pro?'true':'false'?>;
const HAS_RBG = <?php echo $has_rbg?'true':'false'?>;
const AJAX    = <?php echo json_encode(admin_url('admin-ajax.php'))?>;
const NS      = <?php echo json_encode(wp_create_nonce('wqa_save'))?>;
const NE      = <?php echo json_encode(wp_create_nonce('wqa_export'))?>;
const TPL     = <?php echo json_encode($template)?>;
const VM      = <?php echo json_encode($vm)?>;

// ── STATE ─────────────────────────────────────────────────────────────
let photos=[], cam=null, listening=false;
let vs='idle', vf=null, va=null; // voice state

// ── ПОВІДОМЛЕННЯ ──────────────────────────────────────────────────────
function msg(text,type){
    const d=document.getElementById('wqa_msg');
    d.innerHTML=text;
    const [bg,cl]=type==='err'?['#f8d7da','#58151c']:type==='ok'?['#d1e7dd','#0a3622']:['#cfe2ff','#052c65'];
    d.style.cssText=`display:block;padding:12px 14px;border-radius:8px;background:${bg};color:${cl};margin-top:12px`;
    clearTimeout(d._t); d._t=setTimeout(()=>d.style.display='none',5000);
}
function bar(text,bg){
    const b=document.getElementById('wqa_bar');
    b.textContent=text;
    b.style.cssText=`display:block;padding:12px;border-radius:9px;font-size:15px;font-weight:bold;text-align:center;color:#fff;margin-bottom:8px;background:${bg}`;
}
function barOff(){ document.getElementById('wqa_bar').style.display='none'; }
function barReady(){ if(listening) bar(EN?'🎤 Say beacon: name, price, brand…':'🎤 Маяк: назва, ціна, бренд…','#1877F2'); }

// ── TEXTAREA ──────────────────────────────────────────────────────────
function taReset(){ document.getElementById('wqa_ta').value=TPL; }
function taSet(field,value){
    const ta=document.getElementById('wqa_ta');
    let found=false;
    const out=ta.value.split('\n').map(l=>{
        if(l.toLowerCase().startsWith(field.toLowerCase()+':')){found=true;return field+': '+value;}
        return l;
    });
    if(!found) out.push(field+': '+value);
    ta.value=out.join('\n');
}
function taAppend(field,value){
    const ta=document.getElementById('wqa_ta');
    let found=false;
    const out=ta.value.split('\n').map(l=>{
        if(l.toLowerCase().startsWith(field.toLowerCase()+':')){
            found=true;
            const cur=l.substring(l.indexOf(':')+1).trim();
            return field+': '+(cur?cur+', '+value:value);
        }
        return l;
    });
    if(!found) out.push(field+': '+value);
    ta.value=out.join('\n');
}
function taFocus(field){
    const ta=document.getElementById('wqa_ta');
    let pos=0;
    for(const [i,l] of ta.value.split('\n').entries()){
        if(l.toLowerCase().startsWith(field.toLowerCase()+':')){
            const ac=pos+l.indexOf(':')+2, le=pos+l.length;
            ta.focus(); ta.setSelectionRange(ac,le);
            ta.scrollTop=Math.max(0,i*(parseInt(getComputedStyle(ta).lineHeight)||20)-50);
            return;
        }
        pos+=l.length+1;
    }
}

// ── АТРИБУТИ ──────────────────────────────────────────────────────────
function attrUpsert(name,value){
    for(const row of document.querySelectorAll('.wqa_ar')){
        const [i0,i1]=[...row.querySelectorAll('input')];
        if(i0.value.toLowerCase()===name.toLowerCase()){
            const cur=i1.value.trim();
            i1.value=cur?cur+', '+value:value;
            i1.style.borderColor='#198754'; setTimeout(()=>i1.style.borderColor='',1400);
            return;
        }
    }
    attrAdd(name,value);
}
function attrAdd(name,value){
    const row=document.createElement('div');
    row.className='wqa_ar';
    row.style.cssText='display:flex;gap:6px;align-items:center;margin-bottom:5px';
    const mk=(v,ph,fl)=>{
        const i=document.createElement('input');
        i.type='text'; i.value=v||''; i.placeholder=ph;
        i.style.cssText=`flex:${fl};padding:7px 9px;border:1px solid #ccc;border-radius:6px;font-size:14px;transition:border-color .3s`;
        return i;
    };
    const del=document.createElement('button');
    del.textContent='✕'; del.className='wqa_btn';
    del.style.cssText='background:#dc3545;padding:7px 10px;font-size:13px';
    del.onclick=()=>row.remove();
    row.append(mk(name,EN?'Attribute':'Назва',1), mk(value,EN?'Values (comma)':'Через кому',2), del);
    document.getElementById('wqa_attrs').appendChild(row);
}
document.getElementById('btn_add_attr').addEventListener('click',()=>attrAdd('',''));

// ── ВАРІАТИВНІ (PRO) ──────────────────────────────────────────────────
<?php if($pro): ?>
document.getElementById('wqa_ptype').addEventListener('change',function(){
    document.getElementById('wqa_vwrap').style.display=this.value==='variable'?'block':'none';
});

function varAddRow(name,values,prices){
    const row=document.createElement('div');
    row.className='wqa_vr';
    row.style.cssText='background:#fff;border:1px solid #d4b0ff;border-radius:8px;padding:10px;margin-bottom:8px';

    // Назва варіації
    const title=document.createElement('div');
    title.style.cssText='display:flex;gap:6px;align-items:center;margin-bottom:8px';
    const nameInp=document.createElement('input');
    nameInp.type='text'; nameInp.value=name||'';
    nameInp.placeholder=EN?'Variation name (e.g. Color)':'Назва (напр. Колір)';
    nameInp.style.cssText='flex:1;padding:7px 9px;border:1.5px solid #6f42c1;border-radius:6px;font-size:14px;font-weight:bold';
    const del=document.createElement('button');
    del.textContent='✕'; del.className='wqa_btn';
    del.style.cssText='background:#dc3545;padding:6px 10px;font-size:13px';
    del.onclick=()=>row.remove();
    title.append(nameInp,del);
    row.appendChild(title);

    // Значення з цінами
    const valList=document.createElement('div');
    valList.className='wqa_vvals';
    row.appendChild(valList);

    function addValRow(val,price){
        const vrow=document.createElement('div');
        vrow.style.cssText='display:flex;gap:6px;align-items:center;margin-bottom:5px';
        const vInp=document.createElement('input');
        vInp.type='text'; vInp.value=val||'';
        vInp.placeholder=EN?'Value (e.g. Red)':'Значення (напр. Червоний)';
        vInp.style.cssText='flex:2;padding:6px 9px;border:1px solid #ccc;border-radius:6px;font-size:13px';
        const pInp=document.createElement('input');
        pInp.type='number'; pInp.value=price||''; pInp.min='0'; pInp.step='0.01';
        pInp.placeholder=EN?'Price (optional)':'Ціна (необов.)';
        pInp.style.cssText='flex:1;padding:6px 9px;border:1px solid #ccc;border-radius:6px;font-size:13px';
        const vdel=document.createElement('button');
        vdel.textContent='✕'; vdel.className='wqa_btn';
        vdel.style.cssText='background:#6c757d;padding:5px 9px;font-size:12px';
        vdel.onclick=()=>vrow.remove();
        vrow.append(vInp,pInp,vdel);
        valList.appendChild(vrow);
    }

    // Додаємо початкові значення
    const vArr = values||[];
    const pArr = prices||[];
    if(vArr.length) vArr.forEach((v,i)=>addValRow(v,pArr[i]||''));
    else addValRow('','');

    // Кнопка додати значення
    const addVal=document.createElement('button');
    addVal.textContent='+ '+(EN?'Add value':'Додати значення');
    addVal.className='wqa_btn';
    addVal.style.cssText='background:#6f42c1;font-size:12px;padding:5px 10px;margin-top:2px';
    addVal.onclick=()=>addValRow('','');
    row.appendChild(addVal);

    document.getElementById('wqa_var_rows').appendChild(row);
}
document.getElementById('btn_add_var').addEventListener('click',()=>varAddRow('','',''));

// Збираємо варіації для збереження
function collectVariations(){
    const result=[];
    document.querySelectorAll('.wqa_vr').forEach(row=>{
        const name=row.querySelector('input').value.trim();
        if(!name) return;
        const vals=[],prices=[];
        row.querySelectorAll('.wqa_vvals > div').forEach(vrow=>{
            const inputs=vrow.querySelectorAll('input');
            const v=inputs[0].value.trim(), p=inputs[1].value.trim();
            if(v){vals.push(v);prices.push(p);}
        });
        if(vals.length) result.push({name,values:vals,prices});
    });
    return result;
}
<?php endif?>

// ── КАМЕРА ────────────────────────────────────────────────────────────
async function startCam(){
    try{
        if(cam) cam.getTracks().forEach(t=>t.stop());
        cam=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
        document.getElementById('wqa_cam').srcObject=cam;
    }catch(e){ msg((EN?'❌ Camera: ':'❌ Камера: ')+e.message,'err'); }
}
startCam();

document.getElementById('btn_photo').addEventListener('click',()=>{
    const v=document.getElementById('wqa_cam');
    if(!v.videoWidth){msg(EN?'Enable camera first':'Спочатку увімкніть камеру','err');return;}
    const c=document.createElement('canvas');
    c.width=v.videoWidth; c.height=v.videoHeight;
    c.getContext('2d').drawImage(v,0,0);
    const d=c.toDataURL('image/jpeg',.82); photos.push(d);
    const img=document.createElement('img'); img.src=d;
    img.style.cssText='width:66px;height:66px;object-fit:cover;border-radius:7px;border:2px solid #2271b1;cursor:pointer';
    img.onclick=()=>{photos.splice([...document.getElementById('wqa_gal').children].indexOf(img),1);img.remove();};
    document.getElementById('wqa_gal').prepend(img);
    msg((EN?'✅ Photo ':'✅ Фото ')+photos.length,'ok');
    if(HAS_RBG){ const w=document.getElementById('wqa_bg_wrap'); if(w) w.style.display='block'; }
});

document.getElementById('btn_clr').addEventListener('click',()=>{
    photos=[]; document.getElementById('wqa_gal').innerHTML='';
    const w=document.getElementById('wqa_bg_wrap'); if(w) w.style.display='none';
    msg(EN?'Photos cleared':'Фото очищено','info');
});

// ── QR ────────────────────────────────────────────────────────────────
document.getElementById('btn_qr').addEventListener('click', async()=>{
    bar(EN?'📷 Point at QR…':'📷 Наведіть на QR…','#6f42c1');
    let qs,scanning=true;
    try{
        qs=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
        const qv=document.createElement('video'); qv.srcObject=qs; qv.setAttribute('playsinline',''); qv.muted=true;
        await qv.play();
        const modal=document.createElement('div');
        modal.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.93);z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px';
        const vc=document.createElement('div'); vc.style.cssText='width:82%;max-width:360px;border-radius:14px;overflow:hidden;border:3px solid #6f42c1'; vc.appendChild(qv);
        const inf=document.createElement('p'); inf.style.cssText='color:#fff;margin:0;font-size:16px;text-align:center'; inf.textContent=EN?'🔍 Scanning…':'🔍 Сканування…';
        const cbtn=document.createElement('button'); cbtn.textContent=EN?'❌ CANCEL':'❌ СКАСУВАТИ';
        cbtn.className='wqa_btn'; cbtn.style.cssText='background:#dc3545;padding:12px 26px;font-size:15px';
        modal.append(vc,inf,cbtn); document.body.appendChild(modal);
        const canvas=document.createElement('canvas'), ctx=canvas.getContext('2d');
        let att=0;
        async function scan(){
            if(!scanning) return;
            if(qv.videoWidth&&qv.videoHeight){
                att++; canvas.width=qv.videoWidth; canvas.height=qv.videoHeight;
                ctx.drawImage(qv,0,0,canvas.width,canvas.height);
                const fd=new FormData(); fd.append('action','wqa_scan_qr'); fd.append('image',canvas.toDataURL('image/jpeg',.85));
                try{
                    const r=await fetch(AJAX,{method:'POST',body:fd});
                    const d=await r.json();
                    if(d.success&&d.data&&d.data.text&&d.data.text!=='null'){
                        scanning=false; qs.getTracks().forEach(t=>t.stop()); modal.remove(); applyQR(d.data.text);
                    } else { inf.textContent=(EN?'🔍 Attempt ':'🔍 Спроба ')+att; if(scanning) setTimeout(scan,700); }
                }catch(){ if(scanning) setTimeout(scan,700); }
            } else setTimeout(scan,250);
        }
        setTimeout(scan,700);
        cbtn.addEventListener('click',()=>{scanning=false;qs.getTracks().forEach(t=>t.stop());modal.remove();barOff();});
    }catch(e){ msg((EN?'❌ Camera: ':'❌ Камера: ')+e.message,'err'); barOff(); }
});

function applyQR(text){
    const MAP=EN
        ?{NAME:'NAME',PRICE:'PRICE',BRAND:'BRAND',SKU:'SKU',WEIGHT:'WEIGHT',CATEGORY:'CATEGORY',DESCRIPTION:'DESCRIPTION',TAGS:'TAGS',SALE:'SALE'}
        :{НАЗВА:'НАЗВА',ЦІНА:'ЦІНА',БРЕНД:'БРЕНД',АРТИКУЛ:'АРТИКУЛ',ВАГА:'ВАГА',КАТЕГОРІЯ:'КАТЕГОРІЯ',ОПИС:'ОПИС',МІТКИ:'МІТКИ',АКЦІЯ:'АКЦІЯ'};
    let n=0;
    text.split('\n').forEach(line=>{
        const s=line.indexOf(':'); if(s<0) return;
        const k=line.substring(0,s).trim().toUpperCase(), v=line.substring(s+1).trim(); if(!v) return;
        if(MAP[k]){taSet(MAP[k],v);n++;} else{attrUpsert(line.substring(0,s).trim(),v);n++;}
    });
    bar((EN?'✅ QR: ':'✅ QR: ')+n+(EN?' fields':' полів'),'#198754');
    setTimeout(barOff,3000);
    msg(EN?'✅ QR data applied':'✅ QR дані застосовано','ok');
}

// ── REMOVE.BG ─────────────────────────────────────────────────────────
function getBg(){
    for(const r of document.querySelectorAll('input[name="wqa_bg"]')) if(r.checked) return r.value;
    return 'original';
}
async function processPhotos(list){
    if(!HAS_RBG||getBg()==='original') return list;
    const bg=getBg()==='custom'?(document.getElementById('wqa_cc')?.value||'#ffffff').replace('#',''):getBg();
    const st=document.getElementById('wqa_bg_st');
    if(st){st.style.display='block';}
    const res=[];
    for(let i=0;i<list.length;i++){
        if(st) st.textContent=(EN?`⏳ Photo ${i+1}/${list.length}…`:`⏳ Фото ${i+1}/${list.length}…`);
        try{
            const fd=new FormData();
            fd.append('action','wqa_remove_bg'); fd.append('nonce',NS);
            fd.append('image',list[i]); fd.append('bg',bg);
            const r=await fetch(AJAX,{method:'POST',body:fd});
            const d=await r.json();
            res.push(d.success&&d.data?.image?d.data.image:list[i]);
            if(d.success&&d.data?.image){
                const imgs=[...document.getElementById('wqa_gal').querySelectorAll('img')];
                const idx=list.length-1-i; if(imgs[idx]) imgs[idx].src=d.data.image;
            }
        }catch(){ res.push(list[i]); }
    }
    if(st){st.textContent=EN?'✅ Done!':'✅ Готово!'; setTimeout(()=>st.style.display='none',2000);}
    return res;
}

// ── ГОЛОС ─────────────────────────────────────────────────────────────
if(window.SpeechRecognition||window.webkitSpeechRecognition){
    const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    const rec=new SR();
    rec.lang=EN?'en-US':'uk-UA'; rec.continuous=true; rec.interimResults=false; rec.maxAlternatives=3;
    const mb=document.getElementById('btn_mic');

    mb.addEventListener('click',()=>{
        if(!listening){
            try{
                rec.start(); listening=true; vs='idle'; vf=null; va=null;
                mb.style.cssText='flex:2;background:#dc3545;color:#fff;border:none;padding:14px;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;animation:wqa_pulse 1s infinite';
                mb.textContent=EN?'🛑 STOP':'🛑 ЗУПИНИТИ';
                bar(EN?'🎤 Say beacon: name, price…':'🎤 Маяк: назва, ціна, бренд…','#1877F2');
            }catch(e){msg('❌ '+e.message,'err');}
        } else stopVoice();
    });

    function stopVoice(){
        listening=false; vs='idle'; vf=null; va=null; rec.stop();
        mb.style.cssText='flex:2;background:#1877F2;color:#fff;border:none;padding:14px;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer';
        mb.textContent=EN?'🎤 VOICE INPUT':'🎤 ДИКТУВАТИ';
        barOff();
    }

    rec.onresult=e=>{
        let spoken='';
        const res=e.results[e.results.length-1];
        for(let i=0;i<res.length;i++){spoken=res[i].transcript.trim();if(spoken)break;}
        const low=spoken.toLowerCase().replace(/[.,!?;]$/g,'').trim();

        // Стани очікування
        if(vs==='s_val'&&vf){taSet(vf,spoken);taFocus(vf);bar('✅ '+vf+': '+spoken,'#198754');vs='idle';vf=null;setTimeout(barReady,2200);return;}
        if(vs==='s_tag'&&vf){spoken.split(/[\s,]+/).filter(Boolean).forEach(v=>taAppend(vf,v));bar('✅ '+(EN?'Tags: ':'Мітки: ')+spoken,'#198754');vs='idle';vf=null;setTimeout(barReady,2200);return;}
        if(vs==='s_an'){va=spoken;vs='s_av';bar('⏳ '+spoken+' → '+(EN?'values…':'значення…'),'#fd7e14');return;}
        if(vs==='s_av'&&va){spoken.split(/[\s,]+/).filter(Boolean).forEach(v=>attrUpsert(va,v));bar('✅ '+va+': '+spoken,'#198754');vs='idle';va=null;setTimeout(barReady,2200);return;}
        if(vs==='s_vn'){va=spoken;vs='s_vv';bar('⏳ '+spoken+' → '+(EN?'values…':'значення…'),'#fd7e14');return;}
        if(vs==='s_vv'&&va){
            const vals=spoken.split(/[\s,]+/).filter(Boolean);
            <?php if($pro): ?>varAddRow(va,vals,[]);<?php endif?>
            bar('✅ '+va+': '+vals.join(', '),'#198754');vs='idle';va=null;setTimeout(barReady,2200);return;
        }

        // Розпізнавання маяка (від довшого до коротшого)
        const sorted=Object.entries(VM).sort((a,b)=>b[0].length-a[0].length);
        let found=null,inline=null;
        for(const [trig,cfg] of sorted){
            if(low===trig){found=cfg;inline=null;break;}
            if(low.startsWith(trig+' ')){found=cfg;inline=spoken.substring(trig.length).trim();break;}
        }

        if(!found){bar(EN?'💡 name / price / brand / attribute color red…':'💡 назва / ціна / бренд / атрибут колір червоний…','#6c757d');setTimeout(barReady,3500);return;}

        if(found.type==='simple'){
            if(inline){taSet(found.field,inline);taFocus(found.field);bar('✅ '+found.field+': '+inline,'#198754');setTimeout(barReady,2200);}
            else{vf=found.field;vs='s_val';taFocus(found.field);bar('⏳ '+found.field+' → '+(EN?'value…':'значення…'),'#fd7e14');}
        }
        else if(found.type==='append'){
            if(inline){inline.split(/[\s,]+/).filter(Boolean).forEach(v=>taAppend(found.field,v));bar('✅ '+(EN?'Tags: ':'Мітки: ')+inline,'#198754');setTimeout(barReady,2200);}
            else{vf=found.field;vs='s_tag';bar('⏳ '+(EN?'say tags…':'мітки…'),'#fd7e14');}
        }
        else if(found.type==='attr'){
            if(!inline){vs='s_an';bar(EN?'⏳ Attribute name…':'⏳ Назва атрибуту…','#fd7e14');}
            else{
                const parts=inline.split(/\s+/);
                if(parts.length===1){va=inline;vs='s_av';bar('⏳ '+inline+' → '+(EN?'values…':'значення…'),'#fd7e14');}
                else{parts.slice(1).forEach(v=>attrUpsert(parts[0],v));bar('✅ '+parts[0]+': '+parts.slice(1).join(', '),'#198754');setTimeout(barReady,2200);}
            }
        }
        else if(found.type==='variation'){
            if(!inline){vs='s_vn';bar(EN?'⏳ Variation name…':'⏳ Назва варіації…','#fd7e14');}
            else{
                const parts=inline.split(/\s+/);
                if(parts.length===1){va=inline;vs='s_vv';bar('⏳ '+inline+' → '+(EN?'values…':'значення…'),'#fd7e14');}
                else{
                    <?php if($pro): ?>
                    varAddRow(parts[0],parts.slice(1),[]);
                    document.getElementById('wqa_ptype').value='variable';
                    document.getElementById('wqa_vwrap').style.display='block';
                    <?php endif?>
                    bar('✅ '+parts[0]+': '+parts.slice(1).join(', '),'#198754');setTimeout(barReady,2200);
                }
            }
        }
    };

    rec.onerror=e=>{
        if(e.error==='no-speech') return;
        if(e.error==='not-allowed'){
            bar(EN?'❌ Mic blocked! Chrome: tap 🔒 → Permissions → Microphone → Allow'
                  :'❌ Мікрофон заблоковано! Chrome: тапніть 🔒 → Дозволи → Мікрофон → Дозволити','#dc3545');
            stopVoice(); return;
        }
        msg((EN?'❌ Voice: ':'❌ Голос: ')+e.error,'err');
    };
    rec.onend=()=>{ if(listening) setTimeout(()=>{try{rec.start();}catch(x){}},120); };
} else {
    const mb=document.getElementById('btn_mic');
    mb.disabled=true; mb.textContent=EN?'❌ NOT SUPPORTED':'❌ НЕ ПІДТРИМУЄТЬСЯ';
}

// ── ЗБЕРЕЖЕННЯ ────────────────────────────────────────────────────────
document.getElementById('btn_save').addEventListener('click', async function(){
    <?php if(!wqa_can()): ?>
    msg(EN?'❌ Free limit! Buy license':'❌ Ліміт! Купіть ліцензію','err'); return;
    <?php endif?>

    const attrs=[...document.querySelectorAll('.wqa_ar')].map(r=>{
        const [k,v]=[...r.querySelectorAll('input')].map(i=>i.value.trim());
        return k&&v?{name:k,value:v}:null;
    }).filter(Boolean);

    let ptype='simple', variations=[];
    <?php if($pro): ?>
    ptype=document.getElementById('wqa_ptype').value;
    if(ptype==='variable') variations=collectVariations();
    <?php endif?>

    this.disabled=true; this.textContent=EN?'⏳ SAVING…':'⏳ ЗБЕРІГАЮ…';

    // Обробка фото (remove.bg)
    let finalPhotos=photos;
    if(HAS_RBG&&getBg()!=='original'&&photos.length>0) finalPhotos=await processPhotos([...photos]);

    const fd=new FormData();
    fd.append('action','wqa_save'); fd.append('nonce',NS);
    fd.append('text',document.getElementById('wqa_ta').value);
    fd.append('images',JSON.stringify(finalPhotos));
    fd.append('attrs',JSON.stringify(attrs));
    fd.append('type',ptype);
    fd.append('variations',JSON.stringify(variations));

    try{
        const r=await fetch(AJAX,{method:'POST',body:fd});
        const d=await r.json();
        if(d.success){
            msg((EN?'✅ Published! ID: ':'✅ Опубліковано! ID: ')+d.data.id,'ok');
            photos=[]; document.getElementById('wqa_gal').innerHTML='';
            document.getElementById('wqa_attrs').innerHTML='';
            const w=document.getElementById('wqa_bg_wrap'); if(w) w.style.display='none';
            <?php if($pro): ?>document.getElementById('wqa_var_rows').innerHTML='';<?php endif?>
            taReset();
            this.style.background='#e67e00'; this.textContent=EN?'➕ ADD NEXT':'➕ ДОДАТИ ЩЕ';
            setTimeout(()=>location.reload(),1800);
        } else {
            msg('❌ '+(d.data?.message||d.data||'Error'),'err');
            this.disabled=false; this.textContent=EN?'➕ PUBLISH':'➕ ОПУБЛІКУВАТИ';
        }
    }catch(ex){
        msg(EN?'❌ Connection error':'❌ Помилка з\'єднання','err');
        this.disabled=false; this.textContent=EN?'➕ PUBLISH':'➕ ОПУБЛІКУВАТИ';
    }
});

// ── ЕКСПОРТ ───────────────────────────────────────────────────────────
document.getElementById('btn_export').addEventListener('click', async function(){
    this.textContent='⏳'; this.disabled=true;
    const fd=new FormData(); fd.append('action','wqa_export'); fd.append('nonce',NE);
    try{
        const r=await fetch(AJAX,{method:'POST',body:fd});
        const b=await r.blob();
        const u=URL.createObjectURL(b);
        const a=document.createElement('a'); a.href=u;
        a.download='wc-products-'+new Date().toISOString().split('T')[0]+'.csv';
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(u);
    }catch(e){msg(EN?'❌ Export error':'❌ Помилка','err');}
    this.textContent='📤 CSV'; this.disabled=false;
});

}); // END DOMContentLoaded
</script>
<?php }

// =====================================================================
// AJAX: QR СКАНУВАННЯ
// =====================================================================
add_action('wp_ajax_wqa_scan_qr', function(){
    $raw=$_POST['image']??'';
    if(!$raw) wp_send_json_error('No image');
    $bin=base64_decode(preg_replace('#^data:image/\w+;base64,#i','',str_replace(' ','+',$raw)));
    $tmp=tempnam(sys_get_temp_dir(),'wqa_qr_');
    file_put_contents($tmp,$bin);
    $result=null;
    if(function_exists('curl_init')){
        $ch=curl_init();
        curl_setopt_array($ch,[CURLOPT_URL=>'https://api.qrserver.com/v1/read-qr-code/',CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>['file'=>new CURLFile($tmp,'image/jpeg')],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
        $resp=curl_exec($ch); curl_close($ch);
        if($resp){$d=json_decode($resp,true);$t=$d[0]['symbol'][0]['data']??null;if($t&&$t!=='null')$result=$t;}
    }
    unlink($tmp);
    if($result) wp_send_json_success(['text'=>$result]);
    else        wp_send_json_error('QR not detected');
});

// =====================================================================
// AJAX: ЗБЕРЕЖЕННЯ ТОВАРУ
// =====================================================================
add_action('wp_ajax_wqa_save', function(){
    if(!check_ajax_referer('wqa_save','nonce',false)) wp_send_json_error('Nonce');
    if(!current_user_can('manage_woocommerce')&&!current_user_can('edit_products')) wp_send_json_error('Permission');
    if(!wqa_can()) wp_send_json_error(['message'=>wqa_t('Ліміт вичерпано','Limit exceeded')]);

    $raw   =sanitize_textarea_field($_POST['text']??'');
    $imgs  =json_decode(stripslashes($_POST['images']??'[]'),true)?:[];
    $attrs =json_decode(stripslashes($_POST['attrs'] ??'[]'),true)?:[];
    $ptype =sanitize_text_field($_POST['type']??'simple');
    $vars  =json_decode(stripslashes($_POST['variations']??'[]'),true)?:[];

    $data=[];
    foreach(explode("\n",$raw) as $l){
        if(strpos($l,':')!==false){
            [$k,$v]=array_map('trim',explode(':',$l,2));
            if($k&&$v) $data[strtoupper($k)]=$v;
        }
    }
    $name =$data['NAME']        ??$data['НАЗВА']     ??'';
    $price=preg_replace('/[^0-9.]/','',$data['PRICE']??$data['ЦІНА']  ??'');
    $sale =preg_replace('/[^0-9.]/','',$data['SALE'] ??$data['АКЦІЯ'] ??'');
    $sku  =$data['SKU']         ??$data['АРТИКУЛ']   ??'';
    $desc =$data['DESCRIPTION'] ??$data['ОПИС']      ??'';
    $cat  =$data['CATEGORY']    ??$data['КАТЕГОРІЯ'] ??'';
    $brand=$data['BRAND']       ??$data['БРЕНД']     ??'';
    $tags =$data['TAGS']        ??$data['МІТКИ']     ??'';
    $wt   =preg_replace('/[^0-9.]/','',$data['WEIGHT']??$data['ВАГА'] ??'');

    $product=($ptype==='variable'&&wqa_pro())?new WC_Product_Variable():new WC_Product_Simple();
    $product->set_status('publish');
    $product->set_name($name?:wqa_t('Товар','Product').' '.date('H:i:s'));
    if($price) $product->set_regular_price($price);
    if($sale)  $product->set_sale_price($sale);
    if($sku)   try{$product->set_sku($sku);}catch(Exception $e){}
    if($desc)  $product->set_description($desc);
    if($wt)    $product->set_weight($wt);
    $pid=$product->save();
    if(!$pid) wp_send_json_error(['message'=>'Could not save']);

    // Категорія
    if($cat){$t=term_exists($cat,'product_cat')?:wp_insert_term($cat,'product_cat');if(!is_wp_error($t))wp_set_object_terms($pid,(int)$t['term_id'],'product_cat');}
    // Бренд
    if($brand){foreach(['pwb-brand','product_brand','pa_brand'] as $tax){if(taxonomy_exists($tax)){$t=term_exists($brand,$tax)?:wp_insert_term($brand,$tax);if(!is_wp_error($t)){wp_set_object_terms($pid,(int)$t['term_id'],$tax);break;}}}}
    // Мітки
    if($tags){$ids=[];foreach(array_filter(array_map('trim',explode(',',$tags))) as $tn){$t=term_exists($tn,'product_tag')?:wp_insert_term($tn,'product_tag');if(!is_wp_error($t))$ids[]=(int)$t['term_id'];}if($ids)wp_set_object_terms($pid,$ids,'product_tag');}

    // Атрибути
    $wca=[];
    foreach($attrs as $a){
        if(empty($a['name'])||empty($a['value'])) continue;
        $attr=new WC_Product_Attribute();
        $attr->set_name(wc_clean($a['name']));
        $attr->set_options(array_filter(array_map('trim',explode(',',$a['value']))));
        $attr->set_visible(true); $attr->set_variation(false);
        $wca[]=$attr;
    }

    // Варіативні (PRO) — з різними цінами
    if($ptype==='variable'&&wqa_pro()&&!empty($vars)){
        foreach($vars as $var){
            $vn=$var['name']??''; $vvals=$var['values']??[]; $vprices=$var['prices']??[];
            if(!$vn||empty($vvals)) continue;
            $va=new WC_Product_Attribute();
            $va->set_name(wc_clean($vn));
            $va->set_options(array_filter(array_map('trim',$vvals)));
            $va->set_visible(true); $va->set_variation(true);
            $wca[]=$va;
        }
        if($wca){$product->set_attributes($wca);$product->save();}

        // Генерація варіацій — декартовий добуток
        $varmap=[];
        foreach($vars as $var){
            $vn=$var['name']??''; $vvals=$var['values']??[];
            if(!$vn||empty($vvals)) continue;
            $varmap[$vn]=array_values(array_filter(array_map('trim',$vvals)));
        }
        // Будуємо price_map: name|value => price
        $price_map=[];
        foreach($vars as $var){
            $vn=$var['name']??''; $vvals=$var['values']??[]; $vprices=$var['prices']??[];
            foreach($vvals as $i=>$vv){
                $vv=trim($vv); if(!$vv) continue;
                $p=trim($vprices[$i]??'');
                if($p) $price_map[$vn.'|'.$vv]=$p;
            }
        }

        $combos=[[]];
        foreach($varmap as $vn=>$opts){
            $new=[];
            foreach($combos as $c) foreach($opts as $o) $new[]=array_merge($c,[$vn=>$o]);
            $combos=$new;
        }
        foreach($combos as $combo){
            $v=new WC_Product_Variation();
            $v->set_parent_id($pid); $v->set_status('publish');
            // Ціна варіації — шукаємо конкретну ціну, або базову
            $v_price=$price;
            foreach($combo as $cn=>$cv){
                $key=$cn.'|'.$cv;
                if(isset($price_map[$key])){ $v_price=$price_map[$key]; break; }
            }
            if($v_price) $v->set_regular_price($v_price);
            $va2=[];
            foreach($combo as $n=>$val) $va2[wc_attribute_taxonomy_name($n)]=$val;
            $v->set_attributes($va2); $v->save();
        }
    } else {
        if($wca){$product->set_attributes($wca);$product->save();}
    }

    // Фото
    $gallery=[];
    require_once ABSPATH.'wp-admin/includes/image.php';
    foreach($imgs as $i=>$b64){
        $is_png=strpos($b64,'data:image/png')!==false;
        $img=base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$b64));
        $ext=$is_png?'png':'jpg'; $mime=$is_png?'image/png':'image/jpeg';
        $file=wp_upload_bits('wqa_'.$pid.'_'.time().'_'.$i.'.'.$ext,null,$img);
        if($file['error']) continue;
        $aid=wp_insert_attachment(['post_mime_type'=>$mime,'post_status'=>'inherit'],$file['file'],$pid);
        wp_update_attachment_metadata($aid,wp_generate_attachment_metadata($aid,$file['file']));
        if($i===0) set_post_thumbnail($pid,$aid); else $gallery[]=$aid;
    }
    if($gallery) update_post_meta($pid,'_product_image_gallery',implode(',',$gallery));

    wqa_inc();
    wp_send_json_success(['id'=>$pid]);
});

// =====================================================================
// AJAX: REMOVE.BG
// =====================================================================
add_action('wp_ajax_wqa_remove_bg', function(){
    if(!check_ajax_referer('wqa_save','nonce',false)) wp_send_json_error('Nonce');
    $api=get_option('wqa_rbg_api','');
    if(!$api) wp_send_json_error('No API key');
    $b64=$_POST['image']??''; $bg=sanitize_text_field($_POST['bg']??'transparent');
    if(!$b64) wp_send_json_error('No image');
    $img_data=base64_decode(preg_replace('#^data:image/\w+;base64,#i','',str_replace(' ','+',$b64)));
    $r=wp_remote_post('https://api.remove.bg/v1.0/removebg',[
        'headers'=>['X-Api-Key'=>$api],
        'body'=>['image_file_b64'=>base64_encode($img_data),'size'=>'auto','format'=>'png','bg_color'=>($bg==='transparent'?'':$bg)],
        'timeout'=>30,
    ]);
    if(is_wp_error($r)) wp_send_json_error($r->get_error_message());
    $code=wp_remote_retrieve_response_code($r);
    if($code!==200){
        $b=json_decode(wp_remote_retrieve_body($r),true);
        wp_send_json_error($b['errors'][0]['title']??'HTTP '.$code);
    }
    wp_send_json_success(['image'=>'data:image/png;base64,'.base64_encode(wp_remote_retrieve_body($r))]);
});

// =====================================================================
// AJAX: ЕКСПОРТ CSV
// =====================================================================
add_action('wp_ajax_wqa_export', function(){
    if(!check_ajax_referer('wqa_export','nonce',false)) wp_die('Nonce');
    if(!current_user_can('manage_woocommerce')) wp_die('Permission');
    $products=wc_get_products(['limit'=>-1,'status'=>'publish','return'=>'objects']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wc-products-'.date('Y-m-d').'.csv"');
    header('Cache-Control: no-cache,no-store,must-revalidate');
    $out=fopen('php://output','w');
    fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Type','SKU','Name','Published','Is featured?','Visibility in catalog',
        'Short description','Description','Date sale price starts','Date sale price ends',
        'Tax status','Tax class','In stock?','Stock','Low stock amount','Backorders allowed?',
        'Sold individually?','Weight (kg)','Length (cm)','Width (cm)','Height (cm)',
        'Allow customer reviews?','Purchase note','Sale price','Regular price',
        'Categories','Tags','Shipping class','Images','Download limit','Download expiry',
        'Parent','Grouped products','Upsells','Cross-sells','External URL','Button text','Position',
        'Attribute 1 name','Attribute 1 value(s)','Attribute 1 visible','Attribute 1 global',
        'Attribute 2 name','Attribute 2 value(s)','Attribute 2 visible','Attribute 2 global',
        'Attribute 3 name','Attribute 3 value(s)','Attribute 3 visible','Attribute 3 global']);
    foreach($products as $p){
        $pid=$p->get_id();
        $cats=implode(', ',wp_get_object_terms($pid,'product_cat',['fields'=>'names']));
        $tgs =implode(', ',wp_get_object_terms($pid,'product_tag',['fields'=>'names']));
        $imgs=[];
        if($p->get_image_id()) $imgs[]=wp_get_attachment_url($p->get_image_id());
        foreach($p->get_gallery_image_ids() as $gi) if($gi) $imgs[]=wp_get_attachment_url($gi);
        $as=array_values($p->get_attributes());
        $an=[['','',1,0],['','',1,0],['','',1,0]];
        for($i=0;$i<min(3,count($as));$i++) $an[$i]=[$as[$i]->get_name(),implode(', ',$as[$i]->get_options()),1,0];
        $row=[$pid,$p->get_type(),$p->get_sku(),$p->get_name(),1,0,'visible',
            $p->get_short_description(),$p->get_description(),'','','taxable','',
            $p->is_in_stock()?1:0,$p->get_stock_quantity()??'','',0,0,
            $p->get_weight(),$p->get_length(),$p->get_width(),$p->get_height(),
            1,'',$p->get_sale_price(),$p->get_regular_price(),
            $cats,$tgs,'',implode(', ',$imgs),'','','','','','','','',0];
        foreach($an as $a) array_push($row,...$a);
        fputcsv($out,$row);
    }
    fclose($out); exit;
});

register_deactivation_hook(__FILE__,'__return_false');

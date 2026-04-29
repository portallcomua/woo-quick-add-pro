<?php
/**
 * Plugin Name: WooQuick ADD Pro
 * Version: 3.0
 * Description: Голосове додавання товарів в WooCommerce (українські маяки)
 */

if (!defined('ABSPATH')) exit;
define('WQA_VERSION', '3.0');
define('WQA_FREE_LIMIT', 25);

function wqa_is_pro() { return get_option('wqa_license_valid', false); }
function wqa_remaining() { return max(0, WQA_FREE_LIMIT - wp_count_posts('product')->publish); }

add_action('admin_menu', function() {
    add_menu_page('WooQuick ADD', 'WooQuick ADD', 'manage_woocommerce', 'wqa_main', 'wqa_render_gui', 'dashicons-microphone', 30);
    add_submenu_page('wqa_main', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wqa_license', 'wqa_license_page');
});

function wqa_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія</h1>
    <?php if(wqa_is_pro()): ?><div class="notice notice-success"><p>✅ Активна</p></div>
    <?php else: ?><div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo wqa_remaining(); ?> / <?php echo WQA_FREE_LIMIT; ?></p>
    <form method="post"><?php wp_nonce_field('wqa_lic'); ?><input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button></form>
    <p><a href="https://ua-server.pp.ua/product/wooquick-add-pro/">💰 Придбати PRO (599 грн / $29)</a></p><?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if(isset($_POST['activate_lic']) && strlen($_POST['license_key'])>=16) update_option('wqa_license_valid', true);
});

function wqa_render_gui() { ?>
    <div class="wrap"><h1>🎤 WooQuick ADD Pro</h1>
    <div style="background:<?php echo wqa_is_pro()?'#d4edda':'#fff3cd';?>; padding:10px; margin-bottom:15px;"><?php echo wqa_is_pro()?'✅ PRO':'📊 Безкоштовно: '.wqa_remaining().' товарів'; ?></div>
    <div style="margin-bottom:10px"><textarea id="voice_text" rows="6" style="width:100%; font-family:monospace; padding:10px;" placeholder="Диктуйте: назва велосипед ціна 3500 категорія велосипеди атрибут колір чорний"></textarea></div>
    <div style="margin-bottom:10px"><video id="video" width="100%" autoplay playsinline muted style="background:#000; border-radius:10px; transform:scaleX(-1);"></video></div>
    <div id="gallery"></div>
    <div><button id="take_photo" class="button">📷 Фото</button> <button id="clear_photos" class="button">❌</button></div>
    <button id="mic_btn" style="width:100%; background:#1877F2; color:#fff; padding:15px; margin:15px 0;">🎤 Диктувати</button>
    <button id="save_btn" class="button button-primary button-large">➕ Опублікувати</button>
    <div id="message"></div>
    <script>let photos=[],stream=null,listening=false;
    async function start(){try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:"environment"}});document.getElementById('video').srcObject=stream;}catch(e){}}
    start();
    document.getElementById('take_photo').onclick=()=>{let v=document.getElementById('video'),c=document.createElement('canvas');c.width=v.videoWidth;c.height=v.videoHeight;c.getContext('2d').drawImage(v,0,0);let img=c.toDataURL();photos.push(img);let el=document.createElement('img');el.src=img;el.style.width='80px';document.getElementById('gallery').prepend(el);};
    document.getElementById('clear_photos').onclick=()=>{photos=[];document.getElementById('gallery').innerHTML='';};
    if(window.SpeechRecognition){let r=new (window.SpeechRecognition||window.webkitSpeechRecognition)();r.lang='uk-UA';r.continuous=true;
    document.getElementById('mic_btn').onclick=()=>{if(!listening){r.start();listening=true;micBtn.innerText='🛑 Стоп';}else{r.stop();listening=false;micBtn.innerText='🎤 Диктувати';}};
    r.onresult=(e)=>{let t=e.results[e.results.length-1][0].transcript;document.getElementById('voice_text').value+=(document.getElementById('voice_text').value?"\n":"")+t;};r.onend=()=>{if(listening)r.start();};}
    document.getElementById('save_btn').onclick=async function(){this.disabled=true;let fd=new FormData();fd.append('action','wqa_save');fd.append('text',document.getElementById('voice_text').value);fd.append('images',JSON.stringify(photos));let r=await fetch(ajaxurl,{method:'POST',body:fd});let res=await r.json();if(res.success){alert('✅ Товар додано! ID:'+res.data.id);location.reload();}else{alert('❌ '+res.data.message);this.disabled=false;}};
    </script>
    </div><?php
}

add_action('wp_ajax_wqa_save', function() {
    if(!current_user_can('manage_woocommerce') || (!wqa_is_pro() && wp_count_posts('product')->publish >= WQA_FREE_LIMIT)) wp_send_json_error('Ліміт');
    $lines=explode("\n",$_POST['text']); $data=[]; $attrs=[]; $tags=[];
    foreach($lines as $line){ if(strpos($line,':')===false)continue; list($k,$v)=explode(':',$line,2); $k=trim(mb_strtolower($k)); $v=trim($v);
        if(in_array($k,['тег','мітка'])) $tags[]=$v;
        elseif($k==='атрибут'){ $parts=explode(' ',$v,2); if(count($parts)==2) $attrs[$parts[0]][]=$parts[1]; }
        else $data[$k]=$v;
    }
    $p=new WC_Product_Simple(); $p->set_status('publish'); $p->set_name($data['назва']??'Товар'); if(isset($data['ціна'])) $p->set_regular_price(preg_replace('/[^0-9.]/','',$data['ціна'])); if(isset($data['акція'])) $p->set_sale_price(preg_replace('/[^0-9.]/','',$data['акція'])); $pid=$p->save();
    if(isset($data['категорія'])){ $t=term_exists($data['категорія'],'product_cat'); if(!$t)$t=wp_insert_term($data['категорія'],'product_cat'); if(!is_wp_error($t)) wp_set_object_terms($pid,(int)$t['term_id'],'product_cat'); }
    if($tags){ $tag_ids=[]; foreach($tags as $t){ $term=term_exists($t,'product_tag'); if(!$term)$term=wp_insert_term($t,'product_tag'); if(!is_wp_error($term)) $tag_ids[]=(int)$term['term_id']; } wp_set_object_terms($pid,$tag_ids,'product_tag'); }
    foreach($attrs as $name=>$vals){ $slug=sanitize_title($name); if(!wc_attribute_taxonomy_id_by_name($name)) wc_create_attribute(['name'=>$name,'slug'=>$slug,'type'=>'select']); $tax='pa_'.$slug; if(!taxonomy_exists($tax)) register_taxonomy($tax,'product'); $term_ids=[]; foreach($vals as $val){ $term=term_exists($val,$tax); if(!$term)$term=wp_insert_term($val,$tax); if(!is_wp_error($term)) $term_ids[]=(int)$term['term_id']; } wp_set_object_terms($pid,$term_ids,$tax); }
    $imgs=json_decode(stripslashes($_POST['images']),true); if($imgs) foreach($imgs as $i=>$b64){ $img=base64_decode(preg_replace('#^data:image/\w+;base64,#i','',$b64)); $file=wp_upload_bits('wqa_'.$pid.'_'.$i.'.jpg',null,$img); if(!$file['error']){ $aid=wp_insert_attachment(['post_mime_type'=>'image/jpeg','post_status'=>'inherit'],$file['file'],$pid); require_once(ABSPATH.'wp-admin/includes/image.php'); wp_update_attachment_metadata($aid,wp_generate_attachment_metadata($aid,$file['file'])); if($i===0) set_post_thumbnail($pid,$aid); } }
    wp_send_json_success(['id'=>$pid]);
});
?>
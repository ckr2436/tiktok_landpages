<?php
/** @var array $config */
/** @var string $configJson */
/** @var \Magento\Framework\Escaper $escaper */

$page = $config['page'];
$content = $config['content'];
$pixel = $config['pixel'];
$tiktok = $config['tiktok'];
$hasTikTokTarget = !empty($tiktok['raw_pdp_url']) && !empty($tiktok['product_id']);
$metaTitle = $page['meta_title'] ?: $page['title'];
$metaDescription = $page['meta_description'] ?: '';

$heroImageUrl = $content['resolved_hero_image_url'] ?? ($content['hero_image_url'] ?? null);
$ctaBgImageUrl = $content['resolved_cta_bg_image_url'] ?? ($content['cta_bg_image_url'] ?? null);
$promoImageUrl = $content['resolved_promo_image_url'] ?? ($content['promo_image_url'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= $escaper->escapeHtml($metaTitle) ?></title>
  <meta name="description" content="<?= $escaper->escapeHtmlAttr($metaDescription) ?>" />
  <meta name="robots" content="noindex,nofollow" />
  <link rel="canonical" href="<?= $escaper->escapeHtmlAttr($page['frontend_url']) ?>" />
<?php if ($pixel['enabled'] && !empty($pixel['pixel_code'])): ?>
  <link rel="preconnect" href="https://analytics.tiktok.com">
  <link rel="dns-prefetch" href="https://analytics.tiktok.com">
  <script>
    !function (w, d, t) {
      w.TiktokAnalyticsObject = t;
      var ttq = w[t] = w[t] || [];
      ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"];
      ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))) } };
      for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
      ttq.load = function (e, n) {
        var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
        ttq._i = ttq._i || {};
        ttq._i[e] = [];
        ttq._i[e]._u = i;
        ttq._t = ttq._t || {};
        ttq._t[e] = +new Date;
        ttq._o = ttq._o || {};
        ttq._o[e] = n || {};
        var o = document.createElement("script");
        o.type = "text/javascript";
        o.async = !0;
        o.src = i + "?sdkid=" + e + "&lib=" + t;
        var a = document.getElementsByTagName("script")[0];
        a.parentNode.insertBefore(o, a)
      };
      ttq.load('<?= $escaper->escapeJs($pixel['pixel_code']) ?>');
      <?php if (!$pixel['tp_cookie_enabled']): ?>ttq.disableCookie();<?php else: ?>ttq.enableCookie();<?php endif; ?>
      ttq.page();
    }(window, document, 'ttq');
  </script>
<?php endif; ?>
  <?php if ($hasTikTokTarget): ?>
  <script>
  window.PY_TKLP_CONFIG = <?= $configJson ?>;
  (function () {
    var cfg = window.PY_TKLP_CONFIG || {};
    var behavior = cfg.behavior || {};
    var env = cfg.env || {};
    var tiktok = cfg.tiktok || {};
    var passthrough = Array.isArray(behavior.passthrough_params) ? behavior.passthrough_params : [];
    var storagePrefix = 'pynarae_lp_';

    function qs(k) {
      var m = location.search.match(new RegExp('[?&]' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^&]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    }
    function persistParams() {
      try { passthrough.forEach(function (k) { var v = qs(k); if (v) localStorage.setItem(storagePrefix + k, v); }); } catch (_) {}
    }
    function getPersistedOrQueryParam(k) {
      try { return localStorage.getItem(storagePrefix + k) || qs(k) || ''; } catch (_) { return qs(k) || ''; }
    }
    function buildDeepLink() {
      var nowMs = Date.now();
      var paramsUrl = encodeURIComponent(String(tiktok.raw_pdp_url || ''));
      var requestParams = encodeURIComponent(JSON.stringify({ product_id: [String(tiktok.product_id || '')] }));
      var trackParams = encodeURIComponent(JSON.stringify({ source_page_type: 'anchor', enable_shop_tab_popup: 1 }));
      var trafficDiversionInfo = encodeURIComponent(JSON.stringify({ traffic_out_source: 'affiliate_links', page_name: 'product_detail' }));
      var mallExtraInfo = encodeURIComponent(JSON.stringify({ mall_landing_page: 'product_detail', mall_homepage_visited_type: 2 }));
      var url = 'snssdk1233://ec/pdp'
        + '?biz_type=0'
        + '&gd_label=click_product_detail_s_anchor_e__f_anchor_fp__fps_affiliate_links_rf_tt_video'
        + '&need_mall=1'
        + '&needlaunchlog=1'
        + '&page_name=reflow_pdp'
        + '&params_url=' + paramsUrl
        + '&requestParams=' + requestParams
        + '&trackParams=' + trackParams
        + '&ug_medium=fe_component'
        + '&jump_time=' + nowMs
        + '&is_commerce=1'
        + '&page_name=product_detail'
        + '&media_source=channelshare'
        + '&previous_page=deeplink_product_detail_anchor'
        + '&ug_media_source=deeplink_product_detail_anchor'
        + '&traffic_diversion_info=' + trafficDiversionInfo
        + '&mall_extra_info=' + mallExtraInfo;
      passthrough.forEach(function (k) { var v = getPersistedOrQueryParam(k); if (v) url += '&' + k + '=' + encodeURIComponent(v); });
      return url;
    }
    function trackViewContent() {
      try {
        if (window.ttq) {
          ttq.track('ViewContent', {
            contents: [{ content_id: String(tiktok.product_id || ''), content_type: 'product', content_name: String(tiktok.content_name || '') }],
            event_id: String(getPersistedOrQueryParam('ttclid') || 'no_ttclid') + '_' + Date.now()
          });
        }
      } catch (_) {}
    }
    persistParams();
    if (!env.is_tiktok_webview || !behavior.head_jump_enabled) { return; }
    if (window.__PY_TKLP_HEAD_JUMP__) { return; }
    window.__PY_TKLP_HEAD_JUMP__ = true;
    trackViewContent();
    var deep = buildDeepLink();
    var jumped = false;
    var jumpNow = function () { if (jumped) return; jumped = true; try { location.href = deep; } catch (_) {} };
    try { if (window.ttq && ttq.ready) { ttq.ready(function () { setTimeout(jumpNow, 10); }); } } catch (_) {}
    setTimeout(jumpNow, Math.max(0, parseInt(behavior.head_jump_timeout_ms || 180, 10)));
  })();
  </script>
  <?php else: ?>
  <script>window.PY_TKLP_CONFIG = <?= $configJson ?>;</script>
  <?php endif; ?>
  <style>
    :root{--brand:#0b3a93;--text:#0f172a;--muted:#64748b;--line:#e5e7eb;--bg:#ffffff;--soft:#f8fafc}
    *{box-sizing:border-box}html,body{margin:0;padding:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text)}
    a{text-decoration:none}.wrap{max-width:780px;margin:0 auto;padding:0 16px 120px}.promo-bar{padding:10px 14px;background:#fde68a;text-align:center;font-size:13px;font-weight:700}
    .hero{margin-top:22px}.hero h1{font-size:34px;line-height:1.1;margin:0;text-align:center;color:var(--brand);font-weight:900}.hero p{margin:12px 0 0;text-align:center;color:var(--muted);font-size:16px;line-height:1.6}
    .card{border-radius:18px;overflow:hidden;box-shadow:0 20px 50px rgba(2,6,23,.18)}.promo{margin-top:20px}.promo img,.hero-image img,.cta-bg img{display:block;width:100%;height:auto}
    .hero-image{margin-top:20px;border-radius:18px;overflow:hidden;box-shadow:0 12px 30px rgba(2,6,23,.12)}
    .cta-panel{margin-top:20px}.cta-bg{position:relative;border-radius:18px;overflow:hidden;min-height:180px;background:linear-gradient(135deg,#0b3a93,#1d4ed8);display:flex;align-items:center;justify-content:center;padding:24px}
    .cta-overlay{position:absolute;inset:0;background:linear-gradient(to right,rgba(0,0,0,.34),rgba(0,0,0,.12))}.cta-inner{position:relative;z-index:1;text-align:center}.cta-kicker{color:#fff;font-size:15px;font-weight:700;letter-spacing:.02em;margin-bottom:10px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:16px 28px;border-radius:999px;background:#fff;color:var(--brand);font-weight:900;font-size:18px;border:0;cursor:pointer;box-shadow:0 10px 24px rgba(15,23,42,.18)}
    .countdown{margin-top:12px;text-align:center;color:var(--muted);font-size:12px}.sticky-cta{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--line);padding:10px 0;display:none;z-index:30}
    .sticky-cta .inner{max-width:780px;margin:0 auto;padding:0 12px}.sticky-cta .cta-bg{min-height:120px}.toast{position:fixed;left:50%;bottom:92px;transform:translateX(-50%);background:rgba(17,24,39,.92);color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;display:none;z-index:60;max-width:90vw;text-align:center}
    .note-card{margin-top:18px;background:var(--soft);border:1px solid var(--line);border-radius:16px;padding:14px 16px;color:var(--muted);font-size:14px;line-height:1.6}
    .heartbeat{animation:heartbeat 1.35s ease-in-out infinite}@keyframes heartbeat{0%{transform:scale(1)}15%{transform:scale(1.06)}30%{transform:scale(.98)}45%{transform:scale(1.07)}60%{transform:scale(1)}100%{transform:scale(1)}}
    @media (max-width:640px){.hero h1{font-size:30px}.hero p{font-size:15px}.btn{width:100%}}
  </style>
</head>
<body data-msg-fail="<?= $escaper->escapeHtmlAttr($content['fail_message']) ?>" data-msg-desktop="<?= $escaper->escapeHtmlAttr($content['desktop_message']) ?>">
<?php if (!empty($content['promo_bar_text'])): ?><div class="promo-bar"><?= $escaper->escapeHtml($content['promo_bar_text']) ?></div><?php endif; ?>
  <main class="wrap">
    <section class="hero">
      <h1><?= $escaper->escapeHtml($content['headline'] ?: $page['title']) ?></h1>
      <?php if (!empty($content['subheadline'])): ?><p><?= nl2br($escaper->escapeHtml($content['subheadline'])) ?></p><?php endif; ?>
      <?php if ($heroImageUrl): ?><div class="hero-image"><img src="<?= $escaper->escapeUrl($heroImageUrl) ?>" alt="<?= $escaper->escapeHtmlAttr($page['title']) ?>" loading="eager" decoding="async"></div><?php endif; ?>
      <div class="cta-panel" data-landing-cta>
        <div class="cta-bg card">
          <?php if ($ctaBgImageUrl): ?><img src="<?= $escaper->escapeUrl($ctaBgImageUrl) ?>" alt="<?= $escaper->escapeHtmlAttr($page['title']) ?>" loading="lazy" decoding="async"><?php endif; ?>
          <div class="cta-overlay"></div>
          <div class="cta-inner">
            <?php if (!empty($content['promo_bar_text'])): ?><div class="cta-kicker"><?= $escaper->escapeHtml($content['promo_bar_text']) ?></div><?php endif; ?>
            <?php if ($hasTikTokTarget && !empty($config['behavior']['manual_button_enabled'])): ?><button type="button" class="btn heartbeat" data-cta="buy"><?= $escaper->escapeHtml($content['cta_text']) ?></button><?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($hasTikTokTarget): ?><p class="countdown"><span data-count-label>Loading...</span> <b data-count-num></b></p><?php endif; ?>
      <?php if ($promoImageUrl): ?><div class="promo card"><img src="<?= $escaper->escapeUrl($promoImageUrl) ?>" alt="<?= $escaper->escapeHtmlAttr($page['title']) ?>" loading="lazy" decoding="async"></div><?php endif; ?>
      <div class="note-card" data-desktop-note><?= nl2br($escaper->escapeHtml($content['desktop_message'])) ?></div>
    </section>
  </main>
  <?php if ($hasTikTokTarget): ?><div class="sticky-cta" data-sticky-cta><div class="inner"><div class="cta-bg card"><?php if ($ctaBgImageUrl): ?><img src="<?= $escaper->escapeUrl($ctaBgImageUrl) ?>" alt="<?= $escaper->escapeHtmlAttr($page['title']) ?>" loading="lazy" decoding="async"><?php endif; ?><div class="cta-overlay"></div><div class="cta-inner"><?php if (!empty($config['behavior']['manual_button_enabled'])): ?><button type="button" class="btn heartbeat" data-cta="buy"><?= $escaper->escapeHtml($content['cta_text']) ?></button><?php endif; ?></div></div></div></div><?php endif; ?>
  <div id="pynarae-tiktok-toast" class="toast"></div>
  <?php if ($hasTikTokTarget): ?>
  <script>
  (function () {
    var cfg = window.PY_TKLP_CONFIG || {};
    var env = cfg.env || {};
    var behavior = cfg.behavior || {};
    var tiktok = cfg.tiktok || {};
    var passthrough = Array.isArray(behavior.passthrough_params) ? behavior.passthrough_params : [];
    var storagePrefix = 'pynarae_lp_';
    var didLeavePage = false;
    var toast = document.getElementById('pynarae-tiktok-toast');
    var countNum = document.querySelector('[data-count-num]');
    var countLabel = document.querySelector('[data-count-label]');
    var sticky = document.querySelector('[data-sticky-cta]');
    var buttons = document.querySelectorAll('[data-cta="buy"]');
    function qs(k){var m=location.search.match(new RegExp('[?&]'+k.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'=([^&]*)'));return m?decodeURIComponent(m[1]):'';}
    function persistParams(){try{passthrough.forEach(function(k){var v=qs(k);if(v)localStorage.setItem(storagePrefix+k,v);});}catch(_){}}
    function getPersistedOrQueryParam(k){try{return localStorage.getItem(storagePrefix+k)||qs(k)||'';}catch(_){return qs(k)||'';}}
    function markLeave(){didLeavePage=true;} document.addEventListener('visibilitychange',function(){if(document.hidden)markLeave();}); window.addEventListener('pagehide',markLeave); window.addEventListener('blur',markLeave);
    function buildDeepLink(){var nowMs=Date.now();var paramsUrl=encodeURIComponent(String(tiktok.raw_pdp_url||''));var requestParams=encodeURIComponent(JSON.stringify({product_id:[String(tiktok.product_id||'')]}));var trackParams=encodeURIComponent(JSON.stringify({source_page_type:'anchor',enable_shop_tab_popup:1}));var trafficDiversionInfo=encodeURIComponent(JSON.stringify({traffic_out_source:'affiliate_links',page_name:'product_detail'}));var mallExtraInfo=encodeURIComponent(JSON.stringify({mall_landing_page:'product_detail',mall_homepage_visited_type:2}));var url='snssdk1233://ec/pdp'+'?biz_type=0'+'&gd_label=click_product_detail_s_anchor_e__f_anchor_fp__fps_affiliate_links_rf_tt_video'+'&need_mall=1'+'&needlaunchlog=1'+'&page_name=reflow_pdp'+'&params_url='+paramsUrl+'&requestParams='+requestParams+'&trackParams='+trackParams+'&ug_medium=fe_component'+'&jump_time='+nowMs+'&is_commerce=1'+'&page_name=product_detail'+'&media_source=channelshare'+'&previous_page=deeplink_product_detail_anchor'+'&ug_media_source=deeplink_product_detail_anchor'+'&traffic_diversion_info='+trafficDiversionInfo+'&mall_extra_info='+mallExtraInfo;passthrough.forEach(function(k){var v=getPersistedOrQueryParam(k);if(v)url+='&'+k+'='+encodeURIComponent(v);});return url;}
    function showToast(msg){if(!toast)return;toast.innerText=msg;toast.style.display='block';setTimeout(function(){toast.style.display='none';},2200);}
    function trackViewContent(){try{if(window.ttq){ttq.track('ViewContent',{contents:[{content_id:String(tiktok.product_id||''),content_type:'product',content_name:String(tiktok.content_name||'')}],event_id:String(getPersistedOrQueryParam('ttclid')||'no_ttclid')+'_'+Date.now()});}}catch(_){}}
    function openTarget(manual){didLeavePage=false;if(env.is_desktop){var desktopUrl=String(tiktok.pc_fallback_url||tiktok.mobile_fallback_url||'');if(desktopUrl){location.href=desktopUrl;}return;}var deep=buildDeepLink();trackViewContent();var fallbackUrl=String(tiktok.mobile_fallback_url||tiktok.pc_fallback_url||'');var timer=setTimeout(function(){if(!didLeavePage&&document.visibilityState==='visible'){showToast(document.body.getAttribute('data-msg-fail')||'Unable to open TikTok app.');if(fallbackUrl){setTimeout(function(){location.href=fallbackUrl;},700);}}},manual?900:1200);try{location.href=deep;}catch(_){}setTimeout(function(){clearTimeout(timer);},2500);}
    function startCountdown(){if(sticky){sticky.style.display=behavior.manual_button_enabled?'block':'none';}if(!countLabel||!countNum){return;}countLabel.textContent=env.is_desktop?(behavior.body_auto_jump_enabled?'Opening web fallback in':'Desktop fallback ready'):(behavior.body_auto_jump_enabled?'Opening in':'Ready');var seconds=parseInt(behavior.auto_jump_seconds||0,10);if(!behavior.body_auto_jump_enabled||seconds<=0){countNum.textContent='0s';return;}countNum.textContent=seconds+'s';var remain=seconds;var timer=setInterval(function(){remain-=1;if(remain<=0){clearInterval(timer);countNum.textContent='0s';openTarget(false);return;}countNum.textContent=remain+'s';},1000);}
    persistParams(); buttons.forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();openTarget(true);});});
    if(env.is_tiktok_webview&&behavior.head_jump_enabled){if(countLabel){countLabel.textContent='Opening TikTok...';}if(countNum){countNum.textContent='';}if(sticky){sticky.style.display='none';}}else{startCountdown();}
  })();
  </script>
  <?php endif; ?>
</body>
</html>

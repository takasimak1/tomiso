<?php
/**
 * sales_entry.php  レシート番号対応版
 * 登録フロー:
 *   1. sales_header_API に createRecord → recordId をレシート番号に使用
 *   2. 各明細を pos_API に createRecord（レシート番号を付与）
 *   3. sales_confirm.php へリダイレクト
 */
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['store_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id   = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];
$today      = date('m/d/Y');

$nebiki_ritsu_master = [10, 20, 30, 50];
$nebiki_gaku_master  = [50, 100, 200, 300];

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = json_decode($_POST['cart_items'] ?? '[]', true) ?? [];
    if (empty($items)) {
        $error_message = 'カートに商品がありません。';
    } else {
        $total = array_reduce($items, fn($s, $it) => $s + $it['subtotal'], 0);

        // ① sales_header_API にヘッダーを1件作成
        $fmHeader = new fmRESTor($host, $db, 'sales_header_API',
                                  $api_master_user, $api_master_pass, ['allowInsecure' => true]);
        $headerResult = $fmHeader->createRecord(['fieldData' => [
            '店舗No'  => $store_id,
            '店舗名'  => $store_name,
            '合計金額' => $total,
        ]]);

        if ($fmHeader->isError($headerResult)) {
            $error_message = 'レシート番号の発番に失敗しました。';
        } else {
            // ② recordId をレシート番号として取得
            $receipt_no = $headerResult['result']['response']['recordId'] ?? '';

            // ③ 各明細を pos_API に登録
            $fmPos = new fmRESTor($host, $db, $layout_pos,
                                   $api_master_user, $api_master_pass, ['allowInsecure' => true]);
            $fail = 0;
            foreach ($items as $item) {
                $honbai     = (int)($item['price']        ?? 0);
                $suryo      = (int)($item['qty']          ?? 1);
                $neb_gaku   = (int)($item['nebiki_gaku']  ?? 0);
                $neb_ritsu  = (float)($item['nebiki_ritsu'] ?? 0);
                $hanbai_kin = ($honbai - $neb_gaku) * $suryo;
                $result = $fmPos->createRecord(['fieldData' => [
                    '販売日時'   => $today,
                    '店舗No'    => $store_id,
                    '商品名'    => $item['name']  ?? '',
                    '部門'      => $item['bumon'] ?? '',
                    '販売単位'  => $item['tani']  ?? '',
                    '本体価格'  => $honbai,
                    '数量'      => $suryo,
                    '値引額'    => $neb_gaku,
                    '値引率'    => $neb_ritsu,
                    '販売金額'  => $hanbai_kin,
                    '明細金額'  => $hanbai_kin,
                    'レシート番号' => $receipt_no,
                ]]);
                if ($fmPos->isError($result)) $fail++;
            }

            if ($fail === 0) {
                $_SESSION['last_sale'] = [
                    'items'       => $items,
                    'store_name'  => $store_name,
                    'date'        => date('Y年m月d日 H:i'),
                    'receipt_no'  => $receipt_no,
                    'total'       => $total,
                ];
                header('Location: sales_confirm.php');
                exit();
            } else {
                $error_message = count($items) . '件中 ' . $fail . '件の明細登録に失敗しました。';
            }
        }
    }
}

$fm2  = new fmRESTor($host, $db, $layout_hanbai, $api_master_user, $api_master_pass, ['allowInsecure' => true]);
$res2 = $fm2->getRecords(['_limit' => 500]);
$products   = [];
$bumon_list = [];
foreach ($res2['result']['response']['data'] ?? [] as $row) {
    $f = $row['fieldData'];
    $b = trim($f['部門']     ?? '');
    $n = trim($f['商品名']   ?? '');
    $y = trim($f['よみがな'] ?? '');
    $t = trim($f['取扱店舗'] ?? '');
    if ($n === '') continue;

    // 取扱店舗で絞り込み（空欄=全店舗対象、値あり=該当店舗のみ）
    if ($t !== '' && $t !== $store_id) continue;

    $products[] = [
        'bumon'  => $b,
        'name'   => $n,
        'yomi'   => $y,
        'tani'   => trim($f['販売単位'] ?? ''),
        'price'  => (int)($f['本体価格'] ?? 0),
    ];
    if ($b !== '' && !in_array($b, $bumon_list, true)) $bumon_list[] = $b;
}

// よみがなで五十音順ソート
usort($products, fn($a, $b) => strcmp($a['yomi'], $b['yomi']));

// 部門リストもよみがな順の商品の出現順に並べる（自然な順序）
$bumon_list = [];
foreach ($products as $p) {
    if ($p['bumon'] !== '' && !in_array($p['bumon'], $bumon_list, true)) {
        $bumon_list[] = $p['bumon'];
    }
}

include __DIR__ . '/header.php';
echo '</div>';
?>
<style>
:root { --pos-font-size: 20px; }
body { overflow: hidden; }
.pos-wrap {
    font-size: var(--pos-font-size);
    display: grid;
    grid-template-rows: auto auto 1fr;
    height: calc(100vh - 56px);
    overflow: hidden;
}
.pos-panel {
    background: #fff;
    border-bottom: 2px solid #004d40;
    padding: 0.5em 0.75em;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.pos-bumon {
    padding: 0.4em 0.75em !important;
    background: #f0f4f0 !important;
    border-bottom: 1px solid #c8d8c8;
    display: flex !important;
    flex-wrap: nowrap !important;   /* 折り返しなし */
    gap: 0.4em;
    overflow-x: auto !important;   /* 横スクロール */
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.pos-bumon::-webkit-scrollbar { display: none; }
.pos-shohin {
    overflow-y: auto;
    padding: 0.3em 0.5em;
    background: #fafafa;
}
.bumon-btn {
    padding: 0.3em 0.8em;
    border: 2px solid #004d40;
    border-radius: 2em;
    background: #fff;
    color: #004d40;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    transition: all .15s;
    flex-shrink: 0;             /* 横スライド時に縮まない */
    white-space: nowrap;
}
.bumon-btn.active { background: #004d40; color: #fff; }
.shohin-item {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 0.35em 0.75em !important;
    margin-bottom: 0.2em;
    border: 2px solid #ddd;
    border-radius: 0.5em;
    background: #fff;
    cursor: pointer;
    font-size: 1em;
    text-align: left;
    width: 100%;
    box-sizing: border-box;
    transition: all .15s;
    min-height: 2.6em;
}
.shohin-item:hover  { border-color: #004d40; background: #e8f5e9; }
.shohin-item.active { border-color: #004d40; background: #004d40; color: #fff; }
.shohin-item .s-price { font-weight: bold; color: #c62828; white-space: nowrap; margin-left: 0.5em; flex-shrink: 0; }
.shohin-item.active .s-price { color: #a5d6a7; }
/* 規格：商品名の横に inline 表示 */
.shohin-item .s-tani {
    font-size: 0.75em !important;
    color: #888 !important;
    margin-left: 0.4em;
    white-space: nowrap;
    display: inline !important;  /* 必ず横に表示 */
}
.shohin-item.active .s-tani { color: #ccc !important; }
/* 旧 s-sub クラスが残っていても非表示 */
.shohin-item .s-sub { display: none !important; }
.panel-grid { display: flex; flex-wrap: wrap; align-items: center; gap: 0.4em 0.8em; }
.sel-info { flex: 1; min-width: 8em; }
.sel-info .s-name { font-size: 1em; font-weight: bold; }
.sel-info .s-hint { font-size: 0.7em; color: #888; }
.qty-row { display: flex; align-items: center; gap: 0.4em; }
.qty-btn {
    width: 1.8em; height: 1.8em; border-radius: 50%;
    border: 2px solid #004d40; background: #fff; color: #004d40;
    font-size: 1em; font-weight: bold; cursor: pointer; line-height: 1;
    display: flex; align-items: center; justify-content: center;
}
.qty-btn:hover { background: #004d40; color: #fff; }
.qty-num { font-size: 1.2em; font-weight: bold; min-width: 1.5em; text-align: center; }
.neb-tabs { display: flex; gap: 0.3em; margin-bottom: 0.3em; }
.neb-tab {
    padding: 0.2em 0.7em; border-radius: 1em;
    border: 2px solid #00897b; background: #fff; color: #00897b;
    font-size: 0.85em; font-weight: bold; cursor: pointer;
}
.neb-tab.active { background: #00897b; color: #fff; }
.neb-sel { border: 2px solid #00897b; border-radius: 0.4em; padding: 0.25em 0.5em; font-size: 0.9em; min-width: 7em; }
.unit-price { font-size: 1.1em; font-weight: bold; color: #c62828; white-space: nowrap; }
.add-btn {
    background: #00897b; color: #fff; border: none;
    border-radius: 0.5em; padding: 0.4em 0.9em;
    font-size: 1em; font-weight: bold; cursor: pointer; white-space: nowrap;
}
.add-btn:disabled { background: #bbb; cursor: not-allowed; }
.cart-row { display: flex; align-items: center; gap: 0.5em; margin-top: 0.4em; flex-wrap: wrap; }
.cart-table { font-size: 0.85em; flex: 1; min-width: 0; border-collapse: collapse; }
.cart-table td { padding: 0.15em 0.4em; white-space: nowrap; }
.del-btn { border: none; background: none; color: #e53935; cursor: pointer; font-size: 1em; padding: 0 0.2em; }
.cart-total { font-size: 1.1em; font-weight: bold; color: #c62828; white-space: nowrap; }
.register-btn {
    background: #004d40; color: #fff; border: none;
    border-radius: 0.5em; padding: 0.45em 1em;
    font-size: 1em; font-weight: bold; cursor: pointer; white-space: nowrap;
}
.register-btn:disabled { background: #bbb; cursor: not-allowed; }
</style>

<div class="pos-wrap">
  <div class="pos-panel">
    <?php if ($error_message): ?>
      <div class="alert alert-danger py-1 px-3 mb-1" style="font-size:0.85em;">
        <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>
    <div class="panel-grid">
      <div class="sel-info">
        <div class="s-name" id="sel-name" style="color:#999;">商品を選択してください</div>
        <div class="s-hint" id="sel-hint"></div>
      </div>
      <div class="qty-row">
        <button class="qty-btn" type="button" id="qty-minus">－</button>
        <span class="qty-num" id="qty-num">1</span>
        <button class="qty-btn" type="button" id="qty-plus">＋</button>
      </div>
      <div>
        <div class="neb-tabs">
          <button class="neb-tab active" data-mode="ritsu">率</button>
          <button class="neb-tab"        data-mode="gaku">額</button>
        </div>
        <div id="neb-ritsu">
          <select id="sel-ritsu" class="neb-sel">
            <option value="0">値引なし</option>
            <?php foreach ($nebiki_ritsu_master as $r): ?>
              <option value="<?= $r ?>"><?= $r ?>%</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="neb-gaku" style="display:none;">
          <select id="sel-gaku" class="neb-sel">
            <option value="0">値引なし</option>
            <?php foreach ($nebiki_gaku_master as $g): ?>
              <option value="<?= $g ?>">¥<?= number_format($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="unit-price" id="unit-price">¥0</div>
      <button class="add-btn" id="add-btn" disabled>＋ カートへ</button>
    </div>
    <div class="cart-row" id="cart-row" style="display:none;">
      <div style="flex:1; overflow-x:auto;">
        <table class="cart-table"><tbody id="cart-tbody"></tbody></table>
      </div>
      <div style="display:flex; align-items:center; gap:0.5em; flex-shrink:0;">
        <span class="cart-total">合計 <span id="cart-total">¥0</span></span>
        <form method="post" id="entry-form">
          <input type="hidden" name="cart_items" id="cart-json">
          <button type="submit" class="register-btn" id="reg-btn" disabled>登録する</button>
        </form>
      </div>
    </div>
    <div id="cart-empty" style="font-size:0.75em; color:#999; margin-top:0.3em;">カートは空です</div>
  </div>

  <div class="pos-bumon" id="bumon-area">
    <button class="bumon-btn active" data-bumon="">すべて</button>
    <?php foreach ($bumon_list as $b): ?>
      <button class="bumon-btn" data-bumon="<?= htmlspecialchars($b, ENT_QUOTES) ?>">
        <?= htmlspecialchars($b) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="pos-shohin" id="shohin-list">
    <?php foreach ($products as $p): ?>
      <button class="shohin-item"
              data-bumon="<?= htmlspecialchars($p['bumon'], ENT_QUOTES) ?>"
              data-name="<?= htmlspecialchars($p['name'],  ENT_QUOTES) ?>"
              data-tani="<?= htmlspecialchars($p['tani'],  ENT_QUOTES) ?>"
              data-price="<?= $p['price'] ?>">
        <span style="display:flex; align-items:baseline; gap:0; flex:1; min-width:0; overflow:hidden;">
          <span style="font-weight:bold; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?= htmlspecialchars($p['name']) ?>
          </span>
          <span class="s-tani"><?= htmlspecialchars($p['tani']) ?></span>
        </span>
        <span class="s-price">¥<?= number_format($p['price']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function() {
'use strict';
let sel=null,qty=1,cart=[],nebMode='ritsu';
const $=id=>document.getElementById(id);
const elSelName=$('sel-name'),elSelHint=$('sel-hint'),elQtyNum=$('qty-num'),elUnitPr=$('unit-price');
const elAddBtn=$('add-btn'),elSelRitsu=$('sel-ritsu'),elSelGaku=$('sel-gaku');
const elCartRow=$('cart-row'),elCartEmp=$('cart-empty'),elCartBody=$('cart-tbody');
const elCartTot=$('cart-total'),elRegBtn=$('reg-btn'),elCartJson=$('cart-json');

document.getElementById('bumon-area').addEventListener('click',function(e){
    const btn=e.target.closest('.bumon-btn');if(!btn)return;
    document.querySelectorAll('.bumon-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const bumon=btn.dataset.bumon;
    document.querySelectorAll('.shohin-item').forEach(el=>{
        el.style.display=(bumon===''||el.dataset.bumon===bumon)?'':'none';
    });clearSel();
});

document.getElementById('shohin-list').addEventListener('click',function(e){
    const btn=e.target.closest('.shohin-item');if(!btn)return;
    document.querySelectorAll('.shohin-item').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    sel={name:btn.dataset.name,bumon:btn.dataset.bumon,tani:btn.dataset.tani,price:parseInt(btn.dataset.price,10)};
    qty=1;elQtyNum.textContent=1;elSelRitsu.value=0;elSelGaku.value=0;
    elSelName.textContent=sel.name;elSelName.style.color='';
    elSelHint.textContent=sel.bumon+' ／ '+sel.tani+'　定価 ¥'+sel.price.toLocaleString();
    elAddBtn.disabled=false;calcUnit();
});

$('qty-minus').addEventListener('click',()=>{qty=Math.max(1,qty-1);elQtyNum.textContent=qty;calcUnit();});
$('qty-plus').addEventListener('click', ()=>{qty++;elQtyNum.textContent=qty;calcUnit();});

document.querySelectorAll('.neb-tab').forEach(btn=>{
    btn.addEventListener('click',function(){
        nebMode=this.dataset.mode;
        document.querySelectorAll('.neb-tab').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        $('neb-ritsu').style.display=nebMode==='ritsu'?'':'none';
        $('neb-gaku').style.display =nebMode==='gaku' ?'':'none';
        if(nebMode==='ritsu')elSelGaku.value=0;else elSelRitsu.value=0;
        calcUnit();
    });
});
elSelRitsu.addEventListener('change',calcUnit);
elSelGaku.addEventListener('change', calcUnit);

function getNeb(){
    if(!sel)return{gaku:0,ritsu:0};
    if(nebMode==='ritsu'){const r=parseFloat(elSelRitsu.value)||0;return{ritsu:r,gaku:Math.round(sel.price*r/100)};}
    const g=parseInt(elSelGaku.value,10)||0;
    return{gaku:g,ritsu:sel.price>0?Math.round(g/sel.price*1000)/10:0};
}
function calcUnit(){
    if(!sel)return;
    const{gaku}=getNeb();
    const unit=Math.max(0,sel.price-gaku),total=unit*qty;
    let str=gaku>0?'¥'+unit.toLocaleString()+'（値引後）':'¥'+sel.price.toLocaleString();
    if(qty>1)str+=' × '+qty+' ＝ ¥'+total.toLocaleString();
    elUnitPr.textContent=str;
}
elAddBtn.addEventListener('click',function(){
    if(!sel)return;
    const{gaku,ritsu}=getNeb();
    const unit=Math.max(0,sel.price-gaku);
    cart.push({name:sel.name,bumon:sel.bumon,tani:sel.tani,price:sel.price,qty,nebiki_gaku:gaku,nebiki_ritsu:ritsu,subtotal:unit*qty});
    renderCart();clearSel();
    qty=1;elQtyNum.textContent=1;elSelRitsu.value=0;elSelGaku.value=0;
});
function renderCart(){
    elCartBody.innerHTML='';
    cart.forEach((item,i)=>{
        let nStr='';
        if(item.nebiki_gaku >0)nStr=' <span style="color:#e53935">-¥'+item.nebiki_gaku+'</span>';
        if(item.nebiki_ritsu>0)nStr=' <span style="color:#e53935">-'+item.nebiki_ritsu+'%</span>';
        const tr=document.createElement('tr');
        tr.innerHTML='<td>'+esc(item.name)+nStr+'</td><td>×'+item.qty+'</td><td><b>¥'+item.subtotal.toLocaleString()+'</b></td><td><button type="button" class="del-btn" data-idx="'+i+'">✕</button></td>';
        elCartBody.appendChild(tr);
    });
    const total=cart.reduce((s,it)=>s+it.subtotal,0);
    elCartTot.textContent='¥'+total.toLocaleString();
    const has=cart.length>0;
    elCartRow.style.display=has?'':'none';
    elCartEmp.style.display=has?'none':'';
    elRegBtn.disabled=!has;
}
elCartBody.addEventListener('click',function(e){
    const btn=e.target.closest('.del-btn');if(!btn)return;
    cart.splice(parseInt(btn.dataset.idx,10),1);renderCart();
});
$('entry-form').addEventListener('submit',function(e){
    if(!cart.length){e.preventDefault();return;}
    elCartJson.value=JSON.stringify(cart);
});
function clearSel(){
    sel=null;
    document.querySelectorAll('.shohin-item').forEach(b=>b.classList.remove('active'));
    elSelName.textContent='商品を選択してください';elSelName.style.color='#999';
    elSelHint.textContent='';elUnitPr.textContent='¥0';elAddBtn.disabled=true;
}
function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>

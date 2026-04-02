<?php
/**
 * sales_edit.php  レシート訂正画面 v2
 * - 元レシートをカートに読み込んで直接編集
 * - 数量・値引きの変更、行削除、新商品追加
 */
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['store_id'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id   = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$raw     = $_GET['data'] ?? '';
$receipt = json_decode($raw, true);
if (!$receipt || empty($receipt['record_ids'])) {
    header('Location: sales_list.php');
    exit();
}

$receipt_no = $receipt['receipt_no'] ?? '';
$record_ids = $receipt['record_ids'] ?? [];
$items      = $receipt['items']      ?? [];
$date_raw   = $receipt['date']       ?? date('Y-m-d');
$date_fm    = (new DateTime($date_raw))->format('m/d/Y');
$date_jp    = (new DateTime($date_raw))->format('Y年n月j日');

$nebiki_ritsu_master = [10, 20, 30, 50];
$nebiki_gaku_master  = [50, 100, 200, 300];

$error_message = '';

// 販売商品マスタ取得（新商品追加用）
$fm2       = new fmRESTor($host, $db, $layout_hanbai, $api_master_user, $api_master_pass, ['allowInsecure' => true]);
$res2      = $fm2->getRecords(['_limit' => 500]);
$all_products = [];
foreach ($res2['result']['response']['data'] ?? [] as $row) {
    $f = $row['fieldData'];
    $toriatsukai = trim($f['取扱店舗'] ?? '');
    if ($toriatsukai !== '' && $toriatsukai !== $store_id) continue;
    $n = trim($f['商品名'] ?? '');
    if ($n === '') continue;
    $all_products[] = [
        'bumon' => trim($f['部門']     ?? ''),
        'name'  => $n,
        'tani'  => trim($f['販売単位'] ?? ''),
        'price' => (int)($f['本体価格'] ?? 0),
    ];
}
usort($all_products, fn($a,$b) => strcmp($a['yomi'] ?? $a['name'], $b['yomi'] ?? $b['name']));

// POST: 訂正登録
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_items = json_decode($_POST['cart_items'] ?? '[]', true) ?? [];
    $del_ids   = json_decode($_POST['record_ids']  ?? '[]', true) ?? [];

    if (empty($new_items)) {
        $error_message = '訂正内容が空です。';
    } else {
        $fm = new fmRESTor($host, $db, $layout_pos, $api_master_user, $api_master_pass, ['allowInsecure' => true]);

        // 既存明細を全件削除
        $del_fail = 0;
        foreach ($del_ids as $rid) {
            $r = $fm->deleteRecord((int)$rid);
            if ($fm->isError($r)) $del_fail++;
        }

        // 訂正内容を新規登録
        $reg_fail = 0;
        foreach ($new_items as $item) {
            $honbai     = (int)($item['price']         ?? 0);
            $suryo      = (int)($item['qty']           ?? 1);
            $neb_gaku   = (int)($item['nebiki_gaku']   ?? 0);
            $neb_ritsu  = (float)($item['nebiki_ritsu'] ?? 0);
            $hanbai_kin = ($honbai - $neb_gaku) * $suryo;
            $r = $fm->createRecord(['fieldData' => [
                '販売日時'    => $date_fm,
                '店舗No'     => $store_id,
                '商品名'     => $item['name']  ?? '',
                '部門'       => $item['bumon'] ?? '',
                '販売単位'   => $item['tani']  ?? '',
                '本体価格'   => $honbai,
                '数量'       => $suryo,
                '値引額'     => $neb_gaku,
                '値引率'     => $neb_ritsu,
                '販売金額'   => $hanbai_kin,
                '明細金額'   => $hanbai_kin,
                'レシート番号' => $receipt_no,
            ]]);
            if ($fm->isError($r)) $reg_fail++;
        }

        if ($del_fail === 0 && $reg_fail === 0) {
            header('Location: sales_list.php?date=' . $date_raw . '&edited=' . $receipt_no);
            exit();
        } else {
            $error_message = '訂正処理中にエラーが発生しました。'
                . ($del_fail > 0 ? "（削除失敗:{$del_fail}件）" : '')
                . ($reg_fail > 0 ? "（登録失敗:{$reg_fail}件）" : '');
        }
    }
}

// 元データをJS用に整形
$initial_cart = array_map(fn($it) => [
    'name'         => $it['name'],
    'bumon'        => $it['bumon'],
    'tani'         => $it['tani'],
    'price'        => $it['price'],
    'qty'          => $it['qty'],
    'nebiki_gaku'  => $it['nebiki_gaku'],
    'nebiki_ritsu' => $it['nebiki_ritsu'],
    'subtotal'     => $it['kingaku'],
], $items);

include __DIR__ . '/header.php';
echo '</div>';
?>
<style>
:root { --pos-font-size: 18px; }
.edit-wrap {
    font-size: var(--pos-font-size);
    max-width: 640px;
    margin: 0 auto;
    padding: 0.5em 0.75em 3em;
}

.edit-badge {
    background: #fff3cd; border: 2px solid #ffc107;
    border-radius: 0.6em; padding: 0.6em 1em; margin-bottom: 0.8em;
    font-size: 0.85em;
}
.edit-badge .title { font-weight: bold; color: #856404; }
.edit-badge .caution { color: #856404; margin-top: 0.2em; font-size: 0.9em; }

/* カートテーブル */
.cart-wrap {
    background: #fff; border: 2px solid #004d40;
    border-radius: 0.6em; overflow: hidden; margin-bottom: 0.8em;
}
.cart-wrap h3 {
    background: #004d40; color: #fff;
    font-size: 0.9em; font-weight: bold;
    margin: 0; padding: 0.4em 0.8em;
}
.cart-table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
.cart-table th {
    background: #f0f4f0; color: #555;
    padding: 0.3em 0.5em; text-align: left;
    font-size: 0.8em; font-weight: bold;
    border-bottom: 1px solid #ddd;
}
.cart-table td {
    padding: 0.4em 0.5em;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.cart-table tr:last-child td { border-bottom: none; }
.cart-table .td-qty { text-align: center; white-space: nowrap; }
.cart-table .td-neb { white-space: nowrap; }
.cart-table .td-price { text-align: right; font-weight: bold; white-space: nowrap; }

/* インライン数量変更 */
.inline-qty { display: flex; align-items: center; gap: 0.2em; justify-content: center; }
.iqty-btn {
    width: 1.4em; height: 1.4em; border-radius: 50%;
    border: 1.5px solid #004d40; background: #fff; color: #004d40;
    font-size: 0.9em; font-weight: bold; cursor: pointer; line-height: 1;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.iqty-btn:hover { background: #004d40; color: #fff; }
.iqty-num { font-weight: bold; min-width: 1.4em; text-align: center; font-size: 0.95em; }

/* インライン値引き */
.inline-neb select {
    border: 1.5px solid #00897b; border-radius: 0.3em;
    padding: 0.15em 0.3em; font-size: 0.8em; max-width: 6em;
}

/* 行削除ボタン */
.btn-del-row {
    border: none; background: none; color: #e53935;
    cursor: pointer; font-size: 1em; padding: 0 0.2em;
}

/* 合計行 */
.cart-total-row {
    display: flex; justify-content: flex-end;
    padding: 0.5em 0.8em;
    font-weight: bold; color: #c62828; font-size: 1em;
    border-top: 2px solid #004d40;
    background: #f8f8f8;
}

/* 新商品追加パネル */
.add-panel {
    background: #f0f4f0; border-radius: 0.5em;
    padding: 0.7em 0.8em; margin-bottom: 0.8em;
    border: 1px solid #c8d8c8;
}
.add-panel h4 { font-size: 0.85em; font-weight: bold; color: #004d40; margin: 0 0 0.5em; }
.add-row { display: flex; align-items: center; flex-wrap: wrap; gap: 0.4em; }
.item-select {
    border: 2px solid #004d40; border-radius: 0.4em;
    padding: 0.3em 0.5em; font-size: 0.85em;
    flex: 1; min-width: 140px; background: #fff; color: #333;
}
.add-qty-row { display: flex; align-items: center; gap: 0.25em; }
.aqty-btn {
    width: 1.6em; height: 1.6em; border-radius: 50%;
    border: 2px solid #004d40; background: #fff; color: #004d40;
    font-size: 0.9em; font-weight: bold; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.aqty-btn:hover { background: #004d40; color: #fff; }
.aqty-num { font-weight: bold; min-width: 1.4em; text-align: center; font-size: 0.95em; }

.neb-tabs { display: flex; gap: 0.2em; margin-bottom: 0.25em; }
.neb-tab {
    padding: 0.15em 0.55em; border-radius: 1em;
    border: 2px solid #00897b; background: #fff; color: #00897b;
    font-size: 0.78em; font-weight: bold; cursor: pointer;
}
.neb-tab.active { background: #00897b; color: #fff; }
.neb-sel {
    border: 2px solid #00897b; border-radius: 0.35em;
    padding: 0.2em 0.4em; font-size: 0.8em; max-width: 7em;
}
.btn-add-item {
    background: #004d40; color: #fff; border: none;
    border-radius: 0.4em; padding: 0.4em 0.9em;
    font-size: 0.85em; font-weight: bold; cursor: pointer; white-space: nowrap;
}
.btn-add-item:disabled { background: #bbb; cursor: not-allowed; }

/* アクションボタン */
.action-row { display: flex; gap: 0.5em; }
.btn-cancel {
    background: #fff; color: #555; border: 2px solid #ccc;
    border-radius: 0.5em; padding: 0.65em 1em;
    font-size: 0.9em; font-weight: bold; cursor: pointer;
    text-decoration: none; display: flex; align-items: center; white-space: nowrap;
}
.btn-register {
    flex: 1; background: #c62828; color: #fff; border: none;
    border-radius: 0.5em; padding: 0.65em;
    font-size: 1em; font-weight: bold; cursor: pointer;
}
.btn-register:hover { background: #b71c1c; }
.btn-register:disabled { background: #bbb; cursor: not-allowed; }
.cart-empty-msg { color: #aaa; font-size: 0.85em; padding: 0.8em; text-align: center; }
</style>

<div class="edit-wrap">

  <?php if ($error_message): ?>
    <div class="alert alert-danger py-2 mb-2" style="font-size:.85em;">
      <?= htmlspecialchars($error_message) ?>
    </div>
  <?php endif; ?>

  <div class="edit-badge">
    <div class="title">✏️ レシート No.<?= htmlspecialchars($receipt_no) ?> の訂正（<?= htmlspecialchars($date_jp) ?>）</div>
    <div class="caution">⚠️ 「訂正登録」を押すと元のレシートが削除されて再登録されます</div>
  </div>

  <!-- 訂正カート -->
  <div class="cart-wrap">
    <h3>訂正内容（元の内容を読み込み済み）</h3>
    <div id="cart-empty-msg" class="cart-empty-msg" style="display:none;">
      商品が0件です。↓から追加してください
    </div>
    <table class="cart-table" id="cart-table">
      <thead>
        <tr>
          <th>商品</th>
          <th style="text-align:center;">数量</th>
          <th>値引き</th>
          <th style="text-align:right;">金額</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="cart-tbody"></tbody>
    </table>
    <div class="cart-total-row">
      合計 <span id="cart-total" style="margin-left:.5em;">¥0</span>
    </div>
  </div>

  <!-- 新商品追加パネル -->
  <div class="add-panel">
    <h4>↓ 商品を追加</h4>
    <div class="add-row">
      <select id="item-select" class="item-select">
        <option value="">-- 商品を選択 --</option>
        <?php foreach ($all_products as $p): ?>
          <option value="<?= htmlspecialchars(json_encode([
            'name'  => $p['name'],
            'bumon' => $p['bumon'],
            'tani'  => $p['tani'],
            'price' => $p['price'],
          ]), ENT_QUOTES) ?>">
            <?= htmlspecialchars($p['bumon']) ?> <?= htmlspecialchars($p['name']) ?> ¥<?= number_format($p['price']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="add-qty-row">
        <button class="aqty-btn" id="aqty-minus">－</button>
        <span class="aqty-num" id="aqty-num">1</span>
        <button class="aqty-btn" id="aqty-plus">＋</button>
      </div>

      <div>
        <div class="neb-tabs" id="add-neb-tabs">
          <button class="neb-tab active" data-mode="ritsu">率</button>
          <button class="neb-tab"        data-mode="gaku">額</button>
        </div>
        <div id="add-neb-ritsu">
          <select id="add-sel-ritsu" class="neb-sel">
            <option value="0">値引なし</option>
            <?php foreach ($nebiki_ritsu_master as $r): ?>
              <option value="<?= $r ?>"><?= $r ?>%</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="add-neb-gaku" style="display:none;">
          <select id="add-sel-gaku" class="neb-sel">
            <option value="0">値引なし</option>
            <?php foreach ($nebiki_gaku_master as $g): ?>
              <option value="<?= $g ?>">¥<?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <button class="btn-add-item" id="btn-add-item" disabled>＋ 追加</button>
    </div>
  </div>

  <!-- 登録フォーム -->
  <form method="post" id="edit-form">
    <input type="hidden" name="cart_items" id="cart-json">
    <input type="hidden" name="record_ids" value="<?= htmlspecialchars(json_encode($record_ids)) ?>">
    <div class="action-row">
      <a href="sales_list.php?date=<?= htmlspecialchars($date_raw) ?>" class="btn-cancel">
        キャンセル
      </a>
      <button type="submit" class="btn-register" id="btn-register" disabled>
        🔄 訂正登録する
      </button>
    </div>
  </form>

</div>

<script>
// 元データをカートの初期値として設定
var cart = <?= json_encode($initial_cart, JSON_UNESCAPED_UNICODE) ?>;
var addQty    = 1;
var addNebMode = 'ritsu';

// 値引きマスター
var nebikiRitsuMaster = <?= json_encode($nebiki_ritsu_master) ?>;
var nebikiGakuMaster  = <?= json_encode($nebiki_gaku_master)  ?>;

// ---- 要素取得 ----
var elCartBody  = document.getElementById('cart-tbody');
var elCartTotal = document.getElementById('cart-total');
var elCartEmp   = document.getElementById('cart-empty-msg');
var elCartTbl   = document.getElementById('cart-table');
var elRegBtn    = document.getElementById('btn-register');
var elCartJson  = document.getElementById('cart-json');
var elSelect    = document.getElementById('item-select');
var elAddBtn    = document.getElementById('btn-add-item');
var elAqtyNum   = document.getElementById('aqty-num');

// ---- カート描画 ----
function renderCart() {
    elCartBody.innerHTML = '';

    cart.forEach(function(item, i) {
        var tr = document.createElement('tr');

        // 値引きオプション生成
        var ritsuOpts = '<option value="0">なし</option>';
        nebikiRitsuMaster.forEach(function(r) {
            var sel = (item.nebiki_ritsu === r && item.nebiki_gaku === 0) ? ' selected' : '';
            ritsuOpts += '<option value="' + r + '"' + sel + '>' + r + '%</option>';
        });
        var gakuOpts = '<option value="0">なし</option>';
        nebikiGakuMaster.forEach(function(g) {
            var sel = (item.nebiki_gaku === g) ? ' selected' : '';
            gakuOpts += '<option value="' + g + '"' + sel + '>¥' + g + '</option>';
        });

        var nebikiHtml = '';
        // 率タブ・額タブの現在状態判定
        var hasGaku  = item.nebiki_gaku  > 0;
        var hasRitsu = item.nebiki_ritsu > 0 && !hasGaku;
        var showRitsu = !hasGaku;

        nebikiHtml =
            '<div style="display:flex;flex-direction:column;gap:2px;">' +
            '<div style="display:flex;gap:3px;">' +
            '<button type="button" class="neb-tab ' + (showRitsu ? 'active' : '') + '" onclick="switchNeb(' + i + ',\'ritsu\',this)">率</button>' +
            '<button type="button" class="neb-tab ' + (!showRitsu ? 'active' : '') + '" onclick="switchNeb(' + i + ',\'gaku\',this)">額</button>' +
            '</div>' +
            '<div id="neb-ritsu-' + i + '" style="display:' + (showRitsu ? 'block':'none') + '">' +
            '<select class="neb-sel" onchange="updateNebRitsu(' + i + ',this.value)">' + ritsuOpts + '</select></div>' +
            '<div id="neb-gaku-' + i + '" style="display:' + (!showRitsu ? 'block':'none') + '">' +
            '<select class="neb-sel" onchange="updateNebGaku(' + i + ',this.value)">' + gakuOpts + '</select></div>' +
            '</div>';

        tr.innerHTML =
            '<td>' + esc(item.name) + '<br><small style="color:#888;">' + esc(item.tani) + '</small></td>' +
            '<td class="td-qty"><div class="inline-qty">' +
              '<button type="button" class="iqty-btn" onclick="changeQty(' + i + ',-1)">－</button>' +
              '<span class="iqty-num">' + item.qty + '</span>' +
              '<button type="button" class="iqty-btn" onclick="changeQty(' + i + ',1)">＋</button>' +
            '</div></td>' +
            '<td class="td-neb">' + nebikiHtml + '</td>' +
            '<td class="td-price">¥' + item.subtotal.toLocaleString() + '</td>' +
            '<td><button type="button" class="btn-del-row" onclick="delRow(' + i + ')">✕</button></td>';

        elCartBody.appendChild(tr);
    });

    var total = cart.reduce(function(s, it) { return s + it.subtotal; }, 0);
    elCartTotal.textContent = '¥' + total.toLocaleString();

    var has = cart.length > 0;
    elCartEmp.style.display = has ? 'none' : '';
    elRegBtn.disabled = !has;
}

// ---- 小計再計算 ----
function recalc(i) {
    var item = cart[i];
    var nGaku = item.nebiki_gaku > 0 ? item.nebiki_gaku
              : Math.round(item.price * item.nebiki_ritsu / 100);
    var unit = Math.max(0, item.price - nGaku);
    cart[i].subtotal = unit * item.qty;
    renderCart();
}

// ---- 行操作 ----
function changeQty(i, d) {
    cart[i].qty = Math.max(1, cart[i].qty + d);
    recalc(i);
}
function delRow(i) {
    cart.splice(i, 1);
    renderCart();
}
function switchNeb(i, mode, btn) {
    var isGaku = (mode === 'gaku');
    document.getElementById('neb-ritsu-' + i).style.display = isGaku ? 'none' : 'block';
    document.getElementById('neb-gaku-'  + i).style.display = isGaku ? 'block' : 'none';
    if (isGaku) { cart[i].nebiki_ritsu = 0; }
    else         { cart[i].nebiki_gaku  = 0; }
    recalc(i);
}
function updateNebRitsu(i, v) {
    cart[i].nebiki_ritsu = parseFloat(v) || 0;
    cart[i].nebiki_gaku  = 0;
    recalc(i);
}
function updateNebGaku(i, v) {
    cart[i].nebiki_gaku  = parseInt(v, 10) || 0;
    cart[i].nebiki_ritsu = 0;
    recalc(i);
}

// ---- 新商品追加 ----
elSelect.addEventListener('change', function() {
    elAddBtn.disabled = (this.value === '');
});
document.getElementById('aqty-minus').addEventListener('click', function() {
    addQty = Math.max(1, addQty - 1);
    elAqtyNum.textContent = addQty;
});
document.getElementById('aqty-plus').addEventListener('click', function() {
    addQty++;
    elAqtyNum.textContent = addQty;
});
document.getElementById('add-neb-tabs').addEventListener('click', function(e) {
    var btn = e.target.closest('.neb-tab');
    if (!btn) return;
    addNebMode = btn.dataset.mode;
    document.querySelectorAll('#add-neb-tabs .neb-tab').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('add-neb-ritsu').style.display = addNebMode === 'ritsu' ? '' : 'none';
    document.getElementById('add-neb-gaku').style.display  = addNebMode === 'gaku'  ? '' : 'none';
    if (addNebMode === 'ritsu') document.getElementById('add-sel-gaku').value  = 0;
    else                        document.getElementById('add-sel-ritsu').value = 0;
});
elAddBtn.addEventListener('click', function() {
    var val = elSelect.value;
    if (!val) return;
    var p  = JSON.parse(val);
    var nr = addNebMode === 'ritsu' ? (parseFloat(document.getElementById('add-sel-ritsu').value) || 0) : 0;
    var ng = addNebMode === 'gaku'  ? (parseInt(document.getElementById('add-sel-gaku').value, 10) || 0) : 0;
    var nGaku = ng > 0 ? ng : Math.round(p.price * nr / 100);
    var unit  = Math.max(0, p.price - nGaku);
    cart.push({
        name: p.name, bumon: p.bumon, tani: p.tani, price: p.price,
        qty: addQty, nebiki_gaku: ng, nebiki_ritsu: nr,
        subtotal: unit * addQty
    });
    renderCart();
    // リセット
    addQty = 1; elAqtyNum.textContent = 1;
    document.getElementById('add-sel-ritsu').value = 0;
    document.getElementById('add-sel-gaku').value  = 0;
    elSelect.value = ''; elAddBtn.disabled = true;
});

// ---- フォーム送信 ----
document.getElementById('edit-form').addEventListener('submit', function(e) {
    if (!cart.length) { e.preventDefault(); return; }
    elCartJson.value = JSON.stringify(cart);
});

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// 初期描画
renderCart();
</script>

<?php include __DIR__ . '/footer.php'; ?>

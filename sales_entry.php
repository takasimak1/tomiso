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
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX の場合は JSON body を読む
    if ($is_ajax) {
        $json_body = json_decode(file_get_contents('php://input'), true) ?? [];
        $items = $json_body['cart_items'] ?? [];
    } else {
        $items = json_decode($_POST['cart_items'] ?? '[]', true) ?? [];
    }

    if (empty($items)) {
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'カートに商品がありません。']); exit(); }
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
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'レシート番号の発番に失敗しました。']); exit(); }
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

            if ($is_ajax) {
                header('Content-Type: application/json');
                if ($fail === 0) {
                    echo json_encode(['ok'=>true,'receipt_no'=>$receipt_no,'total'=>$total,'count'=>count($items)]);
                } else {
                    echo json_encode(['ok'=>false,'error'=>count($items).'件中 '.$fail.'件の明細登録に失敗しました。']);
                }
                exit();
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

// --- カテゴリーマッピング（難波店ヒアリング: 4カテゴリーに集約） ---
// キー = FileMaker 部門名 → 値 = 表示カテゴリー名
$category_map = [
    '焼魚'    => '魚',
    '煮魚'    => '魚',
    '天ぷら'  => '天ぷら',
    '弁当'    => '惣菜',
    '冷惣菜'  => '惣菜',
    '真空商品' => '惣菜',
    'いか焼き' => '惣菜',
    'いか焼'  => '惣菜',
    '南蛮漬'  => '惣菜',
    '唐揚'    => '唐揚',
];
// 表示順（この順番でタブを並べる）
$category_order = ['魚', '天ぷら', '惣菜', '唐揚'];

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

    // 部門名をカテゴリーにマッピング（未定義の部門はそのまま表示）
    $cat = $category_map[$b] ?? $b;

    $products[] = [
        'bumon'  => $cat,
        'bumon_orig' => $b,
        'name'   => $n,
        'yomi'   => $y,
        'tani'   => trim($f['販売単位'] ?? ''),
        'price'  => (int)($f['本体価格'] ?? 0),
    ];
}

// カテゴリー順 → よみがな順でソート
$cat_pos = array_flip($category_order);
usort($products, function($a, $b) use ($cat_pos) {
    $pa = $cat_pos[$a['bumon']] ?? 999;
    $pb = $cat_pos[$b['bumon']] ?? 999;
    if ($pa !== $pb) return $pa - $pb;
    return strcmp($a['yomi'], $b['yomi']);
});

// 表示カテゴリーリスト（定義順を維持、存在するもののみ）
$active_cats = [];
foreach ($products as $p) {
    if ($p['bumon'] !== '' && !in_array($p['bumon'], $active_cats, true)) {
        $active_cats[] = $p['bumon'];
    }
}
// $category_order の順に並べ直し、未定義カテゴリーは末尾に追加
$bumon_list = [];
foreach ($category_order as $c) {
    if (in_array($c, $active_cats, true)) $bumon_list[] = $c;
}
foreach ($active_cats as $c) {
    if (!in_array($c, $bumon_list, true)) $bumon_list[] = $c;
}

include __DIR__ . '/header.php';
// header.phpの <div class="container mt-4"> を閉じ、余白をリセット
echo '</div>';
?>
<style>
/* === レイアウト === */
body > nav + div { margin-top: 0 !important; padding-top: 0 !important; }
:root { --pos-font-size: 16px; }
body { overflow: hidden; margin: 0; padding: 0; }
.pos-wrap {
    font-size: var(--pos-font-size);
    display: grid;
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr auto;  /* タブ → 商品 → カート */
    height: calc(100vh - 48px);
    overflow: hidden;
    margin-top: 0 !important;
}

/* === カテゴリータブ === */
.pos-bumon {
    padding: 0.4em 0.75em !important;
    background: #f0f4f0 !important;
    border-bottom: 1px solid #c8d8c8;
    display: flex !important;
    flex-wrap: nowrap !important;
    gap: 0.4em;
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.pos-bumon::-webkit-scrollbar { display: none; }
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
    flex-shrink: 0;
    white-space: nowrap;
}
.bumon-btn.active { background: #004d40; color: #fff; }
.bumon-btn[data-bumon="魚"]            { border-color: #1565c0; color: #1565c0; }
.bumon-btn[data-bumon="魚"].active     { background: #1565c0; color: #fff; border-color: #1565c0; }
.bumon-btn[data-bumon="天ぷら"]         { border-color: #f9a825; color: #f57f17; }
.bumon-btn[data-bumon="天ぷら"].active  { background: #f9a825; color: #fff; border-color: #f9a825; }
.bumon-btn[data-bumon="惣菜"]          { border-color: #2e7d32; color: #2e7d32; }
.bumon-btn[data-bumon="惣菜"].active   { background: #2e7d32; color: #fff; border-color: #2e7d32; }
.bumon-btn[data-bumon="唐揚"]          { border-color: #c62828; color: #c62828; }
.bumon-btn[data-bumon="唐揚"].active   { background: #c62828; color: #fff; border-color: #c62828; }

/* === 商品グリッド === */
.pos-shohin {
    overflow-y: auto;
    padding: 0.3em 0.5em;
    background: #fafafa;
    display: grid !important;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.3em;
    align-content: start;
}
.shohin-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0.4em 0.3em;
    border: 2px solid #ddd;
    border-radius: 0.5em;
    background: #fff;
    cursor: pointer;
    font-size: 0.9em;
    text-align: center;
    box-sizing: border-box;
    transition: all .12s;
    min-height: 3.2em;
    line-height: 1.3;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
.shohin-item.hidden { display: none !important; }
.shohin-item:hover  { filter: brightness(0.93); }
/* タップ時の一瞬フラッシュ */
.shohin-item.flash  { transform: scale(0.92); filter: brightness(0.8); }
.shohin-item .s-name { font-weight: bold; font-size: 0.95em; }
.shohin-item .s-price { font-weight: bold; color: #c62828; font-size: 0.85em; margin-top: 0.1em; }
.shohin-item .s-tani { display: none !important; }
.shohin-item .s-sub  { display: none !important; }
/* カテゴリー別カラー */
.shohin-item[data-bumon="魚"]    { background: #e3f2fd; border-color: #90caf9; }
.shohin-item[data-bumon="天ぷら"] { background: #fff8e1; border-color: #ffe082; }
.shohin-item[data-bumon="惣菜"]   { background: #e8f5e9; border-color: #a5d6a7; }
.shohin-item[data-bumon="唐揚"]   { background: #fce4ec; border-color: #f48fb1; }

/* === カートエリア（画面下部固定） === */
.pos-cart {
    background: #fff;
    border-top: 2px solid #004d40;
    box-shadow: 0 -2px 8px rgba(0,0,0,.1);
    display: flex;
    flex-direction: column;
    max-height: 40vh;
    overflow: hidden;
}
.cart-items {
    overflow-y: auto;
    padding: 0.3em 0.5em;
    flex: 1;
    min-height: 0;
}
.cart-empty {
    text-align: center;
    color: #aaa;
    font-size: 0.85em;
    padding: 0.6em;
}
/* カート各行 */
.ci-row {
    display: flex;
    align-items: center;
    gap: 0.3em;
    padding: 0.25em 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.85em;
}
.ci-row:last-child { border-bottom: none; }
.ci-name {
    flex: 1;
    min-width: 0;
    font-weight: bold;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.ci-qty {
    display: flex;
    align-items: center;
    gap: 0.2em;
}
.ci-qty-btn {
    width: 1.6em; height: 1.6em;
    border-radius: 50%;
    border: 1.5px solid #004d40;
    background: #fff;
    color: #004d40;
    font-size: 0.9em;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
}
.ci-qty-btn:hover { background: #004d40; color: #fff; }
.ci-qty-num {
    font-weight: bold;
    min-width: 1.3em;
    text-align: center;
    font-size: 1em;
}
.ci-neb {
    border: 1.5px solid #00897b;
    border-radius: 0.3em;
    padding: 0.1em 0.3em;
    font-size: 0.8em;
    min-width: 5em;
    color: #00897b;
    background: #fff;
}
.ci-subtotal {
    font-weight: bold;
    color: #c62828;
    white-space: nowrap;
    min-width: 3.5em;
    text-align: right;
}
.ci-del {
    border: none;
    background: none;
    color: #e53935;
    cursor: pointer;
    font-size: 1.1em;
    padding: 0 0.15em;
    line-height: 1;
}
/* カートフッター */
.cart-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4em 0.6em;
    border-top: 1px solid #e0e0e0;
    background: #f8f8f8;
}
.cart-total-label { font-size: 0.85em; color: #555; }
.cart-total-amount { font-size: 1.3em; font-weight: bold; color: #c62828; }
.register-btn {
    background: #004d40;
    color: #fff;
    border: none;
    border-radius: 0.5em;
    padding: 0.5em 1.2em;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
}
.register-btn:hover { background: #00695c; }
.register-btn:disabled { background: #bbb; cursor: not-allowed; }
.register-btn.loading { background: #666; pointer-events: none; }

/* === トースト通知 === */
.toast-ok {
    position: fixed;
    top: 60px; left: 50%; transform: translateX(-50%);
    background: #2e7d32;
    color: #fff;
    padding: 0.7em 1.5em;
    border-radius: 0.6em;
    font-size: 1.1em;
    font-weight: bold;
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    z-index: 9999;
    opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
}
.toast-ok.show { opacity: 1; }
.toast-err {
    position: fixed;
    top: 60px; left: 50%; transform: translateX(-50%);
    background: #c62828;
    color: #fff;
    padding: 0.7em 1.5em;
    border-radius: 0.6em;
    font-size: 1em;
    font-weight: bold;
    box-shadow: 0 4px 16px rgba(0,0,0,.25);
    z-index: 9999;
    opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
}
.toast-err.show { opacity: 1; }
</style>

<!-- トースト -->
<div class="toast-ok" id="toast-ok"></div>
<div class="toast-err" id="toast-err"></div>

<div class="pos-wrap">

  <!-- 1. カテゴリータブ -->
  <div class="pos-bumon" id="bumon-area">
    <button class="bumon-btn active" data-bumon="">すべて</button>
    <?php foreach ($bumon_list as $b): ?>
      <button class="bumon-btn" data-bumon="<?= htmlspecialchars($b, ENT_QUOTES) ?>">
        <?= htmlspecialchars($b) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- 2. 商品グリッド -->
  <div class="pos-shohin" id="shohin-list">
    <?php foreach ($products as $p): ?>
      <button class="shohin-item"
              data-bumon="<?= htmlspecialchars($p['bumon'], ENT_QUOTES) ?>"
              data-name="<?= htmlspecialchars($p['name'],  ENT_QUOTES) ?>"
              data-tani="<?= htmlspecialchars($p['tani'],  ENT_QUOTES) ?>"
              data-price="<?= $p['price'] ?>">
        <span class="s-name"><?= htmlspecialchars($p['name']) ?></span>
        <span class="s-price">¥<?= number_format($p['price']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- 3. カート（画面下部） -->
  <div class="pos-cart" id="pos-cart">
    <div class="cart-items" id="cart-items">
      <div class="cart-empty" id="cart-empty">商品をタップして追加</div>
    </div>
    <div class="cart-footer">
      <div>
        <span class="cart-total-label">合計</span>
        <span class="cart-total-amount" id="cart-total">¥0</span>
      </div>
      <button class="register-btn" id="reg-btn" disabled>登録する</button>
    </div>
  </div>

</div>

<script>
(function() {
'use strict';

/* --- 値引マスタ（PHP から引き継ぎ） --- */
const nebikiOptions = [
    {label:'値引なし', ritsu:0, gaku:0},
    <?php foreach ($nebiki_ritsu_master as $r): ?>
    {label:'<?= $r ?>%引', ritsu:<?= $r ?>, gaku:0},
    <?php endforeach; ?>
    <?php foreach ($nebiki_gaku_master as $g): ?>
    {label:'¥<?= number_format($g) ?>引', ritsu:0, gaku:<?= $g ?>},
    <?php endforeach; ?>
];

/* --- 状態 --- */
let cart = [];  // {name,bumon,tani,price,qty,nebikiIdx,nebiki_gaku,nebiki_ritsu,subtotal}

const $  = id => document.getElementById(id);
const elCartItems = $('cart-items');
const elCartEmpty = $('cart-empty');
const elCartTotal = $('cart-total');
const elRegBtn    = $('reg-btn');
const elToastOk   = $('toast-ok');
const elToastErr  = $('toast-err');

/* ===================== カテゴリータブ ===================== */
$('bumon-area').addEventListener('click', function(e) {
    const btn = e.target.closest('.bumon-btn');
    if (!btn) return;
    document.querySelectorAll('.bumon-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const bumon = btn.dataset.bumon;
    document.querySelectorAll('.shohin-item').forEach(el => {
        el.classList.toggle('hidden', bumon !== '' && el.dataset.bumon !== bumon);
    });
});

/* ===================== 商品タップ → 即カート追加 ===================== */
$('shohin-list').addEventListener('click', function(e) {
    const btn = e.target.closest('.shohin-item');
    if (!btn) return;

    /* タップフラッシュ */
    btn.classList.add('flash');
    setTimeout(() => btn.classList.remove('flash'), 150);

    const name  = btn.dataset.name;
    const bumon = btn.dataset.bumon;
    const tani  = btn.dataset.tani;
    const price = parseInt(btn.dataset.price, 10);

    /* 同一商品（同名・同価格）がカートにあれば数量+1 */
    const exist = cart.find(it => it.name === name && it.price === price);
    if (exist) {
        exist.qty++;
        recalcItem(exist);
    } else {
        const item = {
            name, bumon, tani, price,
            qty: 1,
            nebikiIdx: 0,        // nebikiOptions の index
            nebiki_gaku: 0,
            nebiki_ritsu: 0,
            subtotal: price
        };
        cart.push(item);
    }
    renderCart();
});

/* ===================== カート描画 ===================== */
function renderCart() {
    /* 既存の行を全削除（cart-empty は残す） */
    elCartItems.querySelectorAll('.ci-row').forEach(el => el.remove());
    elCartEmpty.style.display = cart.length ? 'none' : '';

    cart.forEach((item, i) => {
        const row = document.createElement('div');
        row.className = 'ci-row';
        row.dataset.idx = i;

        /* 値引セレクト */
        let nebOpts = '';
        nebikiOptions.forEach((opt, oi) => {
            nebOpts += '<option value="' + oi + '"' + (oi === item.nebikiIdx ? ' selected' : '') + '>' + esc(opt.label) + '</option>';
        });

        row.innerHTML =
            '<span class="ci-name">' + esc(item.name) + '</span>' +
            '<div class="ci-qty">' +
                '<button type="button" class="ci-qty-btn ci-minus">－</button>' +
                '<span class="ci-qty-num">' + item.qty + '</span>' +
                '<button type="button" class="ci-qty-btn ci-plus">＋</button>' +
            '</div>' +
            '<select class="ci-neb">' + nebOpts + '</select>' +
            '<span class="ci-subtotal">¥' + item.subtotal.toLocaleString() + '</span>' +
            '<button type="button" class="ci-del">✕</button>';

        elCartItems.appendChild(row);
    });

    /* 合計 */
    const total = cart.reduce((s, it) => s + it.subtotal, 0);
    elCartTotal.textContent = '¥' + total.toLocaleString();
    elRegBtn.disabled = cart.length === 0;
}

/* ===================== カート内操作（イベント委譲） ===================== */
elCartItems.addEventListener('click', function(e) {
    const row = e.target.closest('.ci-row');
    if (!row) return;
    const idx = parseInt(row.dataset.idx, 10);
    const item = cart[idx];
    if (!item) return;

    if (e.target.closest('.ci-plus')) {
        item.qty++;
        recalcItem(item);
        renderCart();
    } else if (e.target.closest('.ci-minus')) {
        item.qty = Math.max(1, item.qty - 1);
        recalcItem(item);
        renderCart();
    } else if (e.target.closest('.ci-del')) {
        cart.splice(idx, 1);
        renderCart();
    }
});

/* 値引セレクト変更 */
elCartItems.addEventListener('change', function(e) {
    if (!e.target.classList.contains('ci-neb')) return;
    const row = e.target.closest('.ci-row');
    if (!row) return;
    const idx = parseInt(row.dataset.idx, 10);
    const item = cart[idx];
    if (!item) return;

    const ni = parseInt(e.target.value, 10);
    const opt = nebikiOptions[ni];
    item.nebikiIdx = ni;
    if (opt.ritsu > 0) {
        item.nebiki_ritsu = opt.ritsu;
        item.nebiki_gaku  = Math.round(item.price * opt.ritsu / 100);
    } else {
        item.nebiki_ritsu = 0;
        item.nebiki_gaku  = opt.gaku;
    }
    recalcItem(item);
    renderCart();
});

/* 小計再計算 */
function recalcItem(item) {
    const unit = Math.max(0, item.price - item.nebiki_gaku);
    item.subtotal = unit * item.qty;
}

/* ===================== 登録（AJAX） ===================== */
elRegBtn.addEventListener('click', async function() {
    if (!cart.length) return;
    elRegBtn.disabled = true;
    elRegBtn.textContent = '送信中…';
    elRegBtn.classList.add('loading');

    try {
        const res = await fetch('sales_entry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ cart_items: cart })
        });
        const data = await res.json();

        if (data.ok) {
            showToast(elToastOk, '✓ 登録完了　' + data.count + '点　¥' + data.total.toLocaleString());
            cart = [];
            renderCart();
        } else {
            showToast(elToastErr, '✕ ' + (data.error || '登録に失敗しました'));
        }
    } catch (err) {
        showToast(elToastErr, '✕ 通信エラー: ' + err.message);
    }

    elRegBtn.textContent = '登録する';
    elRegBtn.classList.remove('loading');
    elRegBtn.disabled = cart.length === 0;
});

/* トースト表示 */
function showToast(el, msg) {
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 3000);
}

/* ユーティリティ */
function esc(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

})();
</script>
<?php include __DIR__ . '/footer.php'; ?>

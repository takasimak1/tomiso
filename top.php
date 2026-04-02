<?php
/**
 * top.php  店舗ダッシュボード
 */
use fmRESTor\fmRESTor;
session_start();
if (!isset($_SESSION['user'])) { header('Location: login.php'); exit(); }
require_once __DIR__ . '/src/fmRESTor.php';
require_once __DIR__ . '/fm_setting.php';

$store_id   = $_SESSION['store_id']   ?? '';
$store_name = $_SESSION['store_name'] ?? '';
$today_fm   = date('m/d/Y');
$today_jp   = date('Y年n月j日（D）', strtotime('today'));

// 曜日を日本語に
$week_en = ['Sun'=>'日','Mon'=>'月','Tue'=>'火','Wed'=>'水','Thu'=>'木','Fri'=>'金','Sat'=>'土'];
foreach ($week_en as $en => $ja) {
    $today_jp = str_replace($en, $ja, $today_jp);
}

// 本日売上取得
$today_total  = 0;
$today_count  = 0;
$receipt_count = 0;

$fm = new fmRESTor($host, $db, $layout_pos, $api_master_user, $api_master_pass, ['allowInsecure' => true]);
$query = [
    'query' => [[ '店舗No' => $store_id, '販売日時' => $today_fm ]],
    'limit' => 500,
];
$result  = $fm->findRecords($query);
$fm_code = $result['result']['messages'][0]['code'] ?? '0';
if ($fm_code !== '401') {
    $data = $result['result']['response']['data'] ?? [];
    $receipt_nos = [];
    foreach ($data as $row) {
        $f = $row['fieldData'];
        $today_total += (int)($f['販売金額'] ?? 0);
        $today_count++;
        $rno = $f['レシート番号'] ?? '';
        if ($rno !== '' && !in_array($rno, $receipt_nos)) $receipt_nos[] = $rno;
    }
    $receipt_count = count($receipt_nos);
}

include __DIR__ . '/header.php';
?>
<style>
:root { --pos-font-size: 18px; }
.top-wrap {
    font-size: var(--pos-font-size);
    max-width: 640px;
    margin: 0 auto;
    padding: 0 0.5em 2em;
}

/* 店舗・日付ヘッダー */
.store-header {
    text-align: center;
    padding: 1em 0 0.8em;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 1em;
}
.store-header .store-name {
    font-size: 1.2em;
    font-weight: bold;
    color: #004d40;
}
.store-header .today-date {
    font-size: 0.85em;
    color: #888;
    margin-top: 0.2em;
}

/* 売上サマリーカード */
.summary-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6em;
    margin-bottom: 1.2em;
}
.summary-card {
    background: #fff;
    border-radius: 0.8em;
    border: 2px solid #e0e0e0;
    padding: 0.8em 1em;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.summary-card.main {
    grid-column: 1 / -1;
    border-color: #004d40;
    background: linear-gradient(135deg, #004d40, #00695c);
    color: #fff;
}
.summary-card .label {
    font-size: 0.7em;
    opacity: .75;
    margin-bottom: 0.2em;
}
.summary-card.main .label { color: #fff; }
.summary-card .value {
    font-size: 1.8em;
    font-weight: bold;
    line-height: 1.1;
}
.summary-card.main .value { color: #fff; font-size: 2.2em; }
.summary-card .sub {
    font-size: 0.72em;
    color: #888;
    margin-top: 0.3em;
}
.summary-card.main .sub { color: rgba(255,255,255,.7); }

/* ナビボタン */
.nav-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.6em;
}
.nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.3em;
    padding: 1em 0.5em;
    border-radius: 0.8em;
    border: 2px solid #004d40;
    background: #fff;
    color: #004d40;
    font-size: 1em;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: all .15s;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.nav-btn:hover { background: #004d40; color: #fff; }
.nav-btn .nav-icon { font-size: 1.8em; line-height: 1; }
.nav-btn.primary {
    background: #004d40; color: #fff;
}
.nav-btn.primary:hover { background: #00695c; }
</style>

<div class="top-wrap">

  <!-- 店舗・日付 -->
  <div class="store-header">
    <div class="store-name">🐟 <?= htmlspecialchars($store_name) ?></div>
    <div class="today-date"><?= $today_jp ?></div>
  </div>

  <!-- 売上サマリー -->
  <div class="summary-cards">

    <!-- 本日売上合計（大） -->
    <div class="summary-card main">
      <div class="label">本日の売上合計</div>
      <div class="value">¥<?= number_format($today_total) ?></div>
      <div class="sub">
        レシート <?= $receipt_count ?> 枚 ／ 明細 <?= $today_count ?> 件
      </div>
    </div>

    <!-- レシート枚数 -->
    <div class="summary-card">
      <div class="label">レシート枚数</div>
      <div class="value" style="color:#004d40;"><?= $receipt_count ?></div>
      <div class="sub">枚</div>
    </div>

    <!-- 明細件数 -->
    <div class="summary-card">
      <div class="label">明細件数</div>
      <div class="value" style="color:#004d40;"><?= $today_count ?></div>
      <div class="sub">件</div>
    </div>

  </div>

  <!-- ナビゲーション -->
  <div class="nav-buttons">
    <a href="sales_entry.php" class="nav-btn primary">
      <span class="nav-icon">＋</span>
      売上登録
    </a>
    <a href="sales_list.php" class="nav-btn">
      <span class="nav-icon">≡</span>
      売上一覧
    </a>
  </div>

</div>

<?php include __DIR__ . '/footer.php'; ?>

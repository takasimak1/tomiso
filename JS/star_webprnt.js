/**
 * star_webprnt.js
 * StarWebPRNT Browser (Android Bluetooth) 対応レシート印刷ヘルパー
 * 対象機種: Star TSP100IV (TSP143IV-UEWB)
 *
 * ※ このファイルより前に StarWebPrintBuilder.js / StarWebPrintTrader.js を読み込むこと。
 *
 * 送信先 URL:
 *   公式 StarWebPrintTrader が Android UA を判定し、
 *   WebPRNTSupportHTTPS なし → http://localhost:8001 に自動切替。
 *   ここでは https:// をセットしておけばよい。
 */

'use strict';

/* ========================================================
   定数
   ======================================================== */
var STAR_BT_URL = (location.protocol === 'https:')
    ? 'https://localhost:8001/StarWebPRNT/SendMessage'
    : 'http://localhost:8001/StarWebPRNT/SendMessage';

/* ========================================================
   ブラウザ検出
   ======================================================== */
function isStarWebPRNTBrowser() {
    return /StarWebPRNT/i.test(navigator.userAgent);
}

/* ========================================================
   レシートXML構築
   ======================================================== */
function buildStarReceiptXml(storeName, dateStr, receiptNo, groups, catOrder, grandTotal, isKakunin, barCodes, count) {
    var b   = new StarWebPrintBuilder();
    var req = '';

    req += b.createInitializationElement();

    /* ── ヘッダー ── */
    req += b.createAlignmentElement({ position: 'center' });
    req += b.createTextElement({ codepage: 'utf8', width: 2, height: 2, emphasis: 'true',
                                  data: '株式会社 富惣\n' });
    req += b.createTextElement({ width: 1, height: 1 });
    req += b.createTextElement({ emphasis: 'false' });
    req += b.createTextElement({ codepage: 'utf8', emphasis: 'true', data: storeName + '\n' });
    req += b.createTextElement({ emphasis: 'false' });
    req += b.createTextElement({ codepage: 'utf8',
                                  data: dateStr + '  No.' + receiptNo
                                        + (isKakunin ? '（訂正）' : '') + '\n' });
    if (count) {
        var statusLabel = isKakunin
            ? '訂正完了（' + count + '点）\n'  /* 訂正完了（N点） */
            : '登録完了（' + count + '点）\n'; /* 登録完了（N点） */
        req += b.createTextElement({ codepage: 'utf8', data: statusLabel });
    }
    req += b.createRuledLineElement({ thickness: 'medium', width: 576 });
    req += b.createFeedElement({ line: 1 });

    /* ── 部門別明細 ── */
    catOrder.forEach(function (bumon) {
        var bItems = groups[bumon];
        if (!bItems || !bItems.length) return;
        var bTotal = bItems.reduce(function (s, it) { return s + it.subtotal; }, 0);
        var code   = barCodes ? (barCodes[bumon] || null) : null;

        req += b.createAlignmentElement({ position: 'left' });
        req += b.createTextElement({ codepage: 'utf8', emphasis: 'true',
                                      data: '■ ' + bumon + '\n' });  /* ■ */
        req += b.createTextElement({ emphasis: 'false' });
        /* 項目名ヘッダー */
        req += b.createTextElement({ codepage: 'utf8',
                                      data: '商品名  単価  数量  値引額  請求小計\n' });
                                           /* 商品名  単価  数量  値引額  請求小計 */
        req += b.createRuledLineElement({ thickness: 'thin', width: 576 });

        bItems.forEach(function (it) {
            /* 値引計算 */
            var nebStr = '';
            var nebAmt = 0;
            if (it.nebiki_ritsu > 0) {
                nebStr = ' (-' + it.nebiki_ritsu + '%)';
                nebAmt = it.nebiki_gaku || Math.round((it.price || 0) * it.nebiki_ritsu / 100);
            } else if (it.nebiki_gaku > 0) {
                nebStr = ' (-\xa5' + it.nebiki_gaku.toLocaleString() + ')';
                nebAmt = it.nebiki_gaku;
            }

            /* 商品行: 商品名（値引詳細） ×数量 | 右: ¥請求小計 */
            req += b.createAlignmentElement({ position: 'left' });
            req += b.createTextElement({ codepage: 'utf8',
                                          data: it.name + nebStr + '  \xd7' + it.qty + '\n' });
            req += b.createAlignmentElement({ position: 'right' });
            req += b.createTextElement({ codepage: 'utf8',
                                          data: '\xa5' + it.subtotal.toLocaleString() + '\n' });

            /* 単価・値引額 詳細行 */
            var detail = '  単価:\xa5' + (it.price || 0).toLocaleString();  /* 単価 */
            detail += '  値引額:' + (nebAmt > 0 ? '\xa5' + nebAmt.toLocaleString() : '-');  /* 値引額 */
            req += b.createAlignmentElement({ position: 'left' });
            req += b.createTextElement({ codepage: 'utf8', data: detail + '\n' });
        });

        req += b.createAlignmentElement({ position: 'right' });
        req += b.createTextElement({ codepage: 'utf8', emphasis: 'true',
                                      data: bumon + '小計  \xa5' + bTotal.toLocaleString() + '\n' }); /* 小計 */
        req += b.createTextElement({ emphasis: 'false' });

        /* バーコード（JAN-13） */
        if (code) {
            req += b.createAlignmentElement({ position: 'center' });
            try {
                req += b.createBarcodeElement({
                    symbology : 'JAN13',
                    width     : 'width2',
                    hri       : 'true',
                    height    : 60,
                    data      : code
                });
            } catch (e) { /* バーコード生成エラーは無視 */ }
            req += b.createFeedElement({ line: 1 });
        }

        req += b.createRuledLineElement({ thickness: 'medium', width: 576 });
        req += b.createFeedElement({ line: 1 });
    });

    /* ── 合計 ── */
    req += b.createFeedElement({ line: 1 });
    req += b.createAlignmentElement({ position: 'right' });
    req += b.createTextElement({ codepage: 'utf8', width: 2, height: 2, emphasis: 'true',
                                  data: '合計  \xa5' + grandTotal.toLocaleString() + '\n' }); /* 合計 */
    req += b.createTextElement({ width: 1, height: 1 });
    req += b.createTextElement({ emphasis: 'false' });
    req += b.createAlignmentElement({ position: 'right' });
    req += b.createTextElement({ codepage: 'utf8', data: '(税込)\n' }); /* (税込) */
    req += b.createFeedElement({ line: 2 });

    /* ── フッター ── */
    req += b.createAlignmentElement({ position: 'center' });
    req += b.createTextElement({ codepage: 'utf8', data: 'ありがとうございました\n' }); /* ありがとうございました */
    req += b.createFeedElement({ line: 1 });
    req += b.createTextElement({ codepage: 'utf8', data: '登録番号 T4120101005337\n' }); /* 登録番号 */
    req += b.createTextElement({ codepage: 'utf8', data: '株式会社富惣\n' });   /* 株式会社富惣 */
    req += b.createTextElement({ codepage: 'utf8',
                                  data: '大阪府堺市堺区遠里小野町３丁４番１号\n' });
                                       /* 大阪府堺市堺区遠里小野町３丁４番１号 */
    req += b.createTextElement({ codepage: 'utf8',
                                  data: 'TEL 072-229-8800 / FAX 072-229-4700\n' });
    req += b.createTextElement({ codepage: 'utf8',
                                  data: 'お問合せ 0120-014-868\n' }); /* お問合せ */
    req += b.createFeedElement({ line: 4 });

    req += b.createCutPaperElement({ type: 'partial', feed: 'true' });
    return req;
}

/* ========================================================
   グローバル変数
   ======================================================== */
var _starReceiptData = null;

/* ========================================================
   メイン印刷関数
   引数 btn: クリックされたボタン要素（視覚フィードバック用）
   ======================================================== */
/* ========================================================
   デバッグ出力（サーバログファイルに書き出し）
   ======================================================== */
var _starLogBuf = [];
var _starLogBtn = null;
var _STAR_LOG_URL = (function() {
    /* star_debug_log.php は star_webprnt.js と同じサーバ上にある */
    var base = location.origin;
    var path = location.pathname.replace(/\/[^/]*$/, '/');
    return base + path + 'star_debug_log.php';
}());

function _starLog(msg) {
    _starLogBuf.push(msg);

    /* サーバのログファイルに POST */
    try {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', _STAR_LOG_URL, true);
        xhr.setRequestHeader('Content-Type', 'text/plain; charset=UTF-8');
        xhr.send(msg);
    } catch(e) { /* ログ失敗は無視 */ }

    /* ボタンテキストにも最終メッセージを表示 */
    if (_starLogBtn) {
        _starLogBtn.textContent = msg.substring(0, 35);
    }
}

function starPrintReceipt(btn) {

    _starLogBtn = btn;   /* ボタン参照を保存 */
    _starLogBuf = [];

    var isStar = isStarWebPRNTBrowser();

    _starLog('START isStar=' + (isStar ? 'YES' : 'NO'));
    _starLog('URL=' + STAR_BT_URL);
    _starLog('UA=' + navigator.userAgent.substring(0, 80));

    /* ライブラリ未ロードチェック */
    if (typeof StarWebPrintBuilder === 'undefined' ||
        typeof StarWebPrintTrader  === 'undefined') {
        _starLog('ERR:LIB_NOT_LOADED');
        return;
    }
    _starLog('LIB:OK');

    /* ボタン視覚フィードバック */
    if (btn) {
        btn.disabled    = true;
        btn.textContent = '⏳ 送信中...';
    }
    function resetBtn(label) {
        if (btn) {
            btn.disabled    = false;
            btn.textContent = label || '🖨 印刷';
        }
    }

    /* StarWebPRNT Browser 以外 → 通常印刷 */
    if (!isStar) {
        _starLog('→ window.print()');
        window.print();
        resetBtn('🖨 印刷(通常)');
        return;
    }

    /* データ未セット */
    if (!_starReceiptData) {
        _starLog('ERR:DATA_NULL');
        resetBtn('ERR:DATA');
        return;
    }
    _starLog('DATA:OK store=' + _starReceiptData.storeName);

    /* XML構築 */
    var req;
    try {
        var d = _starReceiptData;
        var barKeys = d.barCodes ? Object.keys(d.barCodes).join(',') : 'null';
        _starLog('barCodes=' + barKeys + ' count=' + (d.count || 0));
        req = buildStarReceiptXml(
            d.storeName, d.dateStr, d.receiptNo,
            d.groups, d.catOrder, d.grandTotal,
            d.isKakunin || false,
            d.barCodes || null,
            d.count || null
        );
        _starLog('XML:OK len=' + req.length);
    } catch (e) {
        _starLog('ERR:XML ' + e.message);
        resetBtn('ERR:XML');
        return;
    }

    /* 送信 */
    _starLog('SEND→ ' + STAR_BT_URL);
    var trader = new StarWebPrintTrader({ url: STAR_BT_URL, timeout: 30000 });

    trader.onReceive = function (response) {
        _starLog('RECV:OK success=' + response.traderSuccess
               + ' st=' + response.traderStatus);
        resetBtn('✅ 印刷完了');
    };

    trader.onError = function (response) {
        _starLog('ERR:status=' + response.status
               + ' ' + String(response.responseText).substring(0, 80));
        resetBtn('❌ エラー(' + response.status + ')');
    };

    trader.onTimeout = function () {
        _starLog('TIMEOUT:30秒');
        resetBtn('⏱ タイムアウト');
    };

    try {
        trader.sendMessage({ request: req });
        _starLog('sendMessage呼出完了(非同期待機中)');
    } catch (e) {
        _starLog('ERR:sendMsg ' + e.message);
        resetBtn('ERR:SEND');
    }
}

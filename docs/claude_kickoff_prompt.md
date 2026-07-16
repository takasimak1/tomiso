# Claude キックオフプロンプト（tomiso / ととレジ）

Mac再起動後など、Claude Codeとの作業を再開するときにこのままコピー＆ペーストして使う。
作業が一区切りつくたびに、下記「直前までの状況」を更新しておくこと。

---

## コピー用プロンプト

```
tomiso プロジェクトで作業を再開します。
リポジトリ: ~/Documents/Claude/Projects/富惣/FileMakerDataAPI/富惣_FileMakerDataAPI/tomiso/
（CLAUDE.md にプロジェクト概要・デプロイ手順あり。まずそれを読んでください）

直前までの状況:
（ここに docs/claude_kickoff_prompt.md の「直前までの状況」セクションを貼り付ける）

まずローカルのファイルアクセス（ls / Read）とgitの状態（git status / git log）を
確認してから、上記の続きに着手してください。
```

---

## 直前までの状況（最終更新: 2026-07-17）

1. **部門マスタ統一（完了・デプロイ済み / commit 620c716）**
   FileMakerレイアウト `bumon_API`（部門CD/部門名/並び順）を新設し、`bumon_master.php`
   を新規作成。`hq_shohin_maint.php` / `hq_tenpo_maint.php` / `sales_entry.php` /
   `sales_edit.php` / `shohin_maint.php` の部門定義をこのマスタ参照に統一。

2. **インストアコード取得バグ修正（完了・デプロイ済み / commit 620c716）**
   `sales_entry.php` の `_fetch_instore_codes_from_fm()` と `sales_edit.php` の
   `_fetch_instore_codes_from_fm_edit()` が、存在しないメソッド
   `$fm->findRecord(...)` を呼んでいたため、account_API からのインストアコード取得が
   常にサイレントに失敗していた（try/catchで握りつぶされていた）。
   `$fm->findRecords(['query' => [...]])` に修正して解消。
   → 難波店のレシートでバーコードが出ない不具合の原因だった。修正後、印刷・プレビュー
   とも表示確認済み。

3. **UI微修正（完了・デプロイ済み / commit c77bbc1）**
   - `top.php`: ナビボタン「売上登録」→「レジ」に表示変更
   - `sales_entry.php`: レシート画面の「次のお客様へ」ボタンを廃止し、印刷ボタン1つで
     印刷実行と同時に売上登録画面へ戻るよう統合（未使用になった `.rcpt-btn-next` の
     CSSも削除）
   - `sales_entry.php`: 「全消去」「登録する」ボタンに `user-select: none` /
     `-webkit-touch-callout: none` を追加し、タブレット長押し時のコピー/共有メニュー
     表示を抑止

4. **横向きタブレット2カラムレイアウト（完了・デプロイ済み / commit 2d1cf93）**
   `sales_entry.php` のレジ画面に `@media (orientation: landscape)` を追加し、
   左63%（タブ＋商品グリッド）・右37%（カート全高）のCSS Gridレイアウトに変更。
   縦向きは既存レイアウトを完全維持。対象タブレットは実測 23.7cm×14.8cm
   （アスペクト比約1.6、店舗の専用端末のみで使用するため、幅指定ではなく
   `orientation: landscape` のみで判定）。
   **未確認**: 実機タブレットを横向きにして、左に商品ボタン・右にカートが
   意図通り表示されるかの実機確認待ち。

## 次にやること候補
- 上記4番の横向きレイアウトを実機タブレットで確認
- （現時点で他に指示された未着手タスクなし。次回セッション開始時にユーザーへ確認）

## 参照
- プロジェクト概要・デプロイ手順: `CLAUDE.md`
- 詳細仕様: `SPEC.md`
- デプロイ: `bash deploy.sh`（rsyncでさくらインターネットへ反映。`docs/` はデプロイ対象外）

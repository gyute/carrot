# CARROT

[![tests](https://github.com/gyute/carrot/actions/workflows/tests.yml/badge.svg)](https://github.com/gyute/carrot/actions/workflows/tests.yml)

[English](README.md) · **日本語** · [한국어](README.ko.md)

著作権フリーのサンプル社内ポータルです。Laravel 13 + Inertia v3 (React) +
PostgreSQL、UI はすべて日本語です。

> 英語版 [README.md](README.md) が原本です。内容が食い違う場合はそちらが正です。

## 動作環境

- PHP 8.4 以上、Composer
- Node 22 以上
- Docker（同梱の PostgreSQL コンテナ用）

## セットアップ

```bash
docker compose up -d          # PostgreSQL を 127.0.0.1:5432 で起動
composer setup                # install, .env, app key, migrate, seed, npm install, build
composer run dev              # サーバー + ワーカー + vite + reverb + ログ
```

`composer run dev` は `sandbox,default` キューのワーカーを動かします。**これが
重要です**。スクリプトの実行も通知もキュー経由なので、ワーカーがいないと
`待機中` のまま止まります。

5432 が埋まっている場合は、先に `.env.example` を `.env` にコピーして `DB_PORT`
を空いているポート（たとえば 5433）にしてください。compose は `DB_PORT` の
ポートで公開し、Laravel も同じポートに繋ぎます。

`composer setup` は何度実行しても安全です（シーダーは既存のものを飛ばします）。
http://127.0.0.1:8000 を開いてログインしてください。

| ログイン ID | 権限           | パスワード |
| ----------- | -------------- | ---------- |
| `test`      | メンバー       | `password` |
| `manager`   | 部署管理者     | `password` |
| `admin`     | システム管理者 | `password` |

`/tools` は空の状態で始まります。ツールはこのリポジトリのコードではなく、誰かが
登録する行だからです。中身のある状態で見たい場合は:

```bash
php artisan demo:seed         # デモカタログを公開。--fresh で作り直し
```

`demo` / `demo-manager` / `demo-admin`（同じく `password`）を追加し、いくつかの
サンプルツールを実際の承認フローに通します。公開される内容と追加方法は
[`demo/README.md`](demo/README.md) を参照してください。

構築済みの環境に変更を取り込んだあとは:

```bash
php artisan migrate
php artisan db:seed --force
```

## `.env` で設定が要るもの

`composer setup` が `.env.example` をコピーし、そのままでも動きます。以下は一度
目を通す価値のある値です。

| キー                                                  | 理由                                                                                                          |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `DB_PORT`                                             | compose が Postgres を公開するポート。5432 が埋まっていれば変更します                                         |
| `SANDBOX_DRIVER`                                      | `none` は実行をキューに積むだけです。ローカルは `bubblewrap`、ランナーホストは `docker`。でないと終わりません |
| `REVERB_APP_ID` / `_KEY` / `_SECRET`, `VITE_REVERB_*` | ライブ更新。空のままでも各画面はポーリングに落ちるだけで、壊れはしません                                      |
| `CATALOG_DEPARTMENTS`                                 | 所属の許可リスト（カンマ区切り）。空なら自由入力になります                                                    |
| `CATALOG_SUBMISSIONS`                                 | 誰がツールを登録できるか。`all` / `admin`（開発チームのみ）/ `none`（誰も登録できず画面ごと消えます）         |
| `CATALOG_REQUESTS`                                    | `/tools/requests` の開発チームへの依頼窓口。off にすると画面ごと消えます                                       |
| `PASSKEYS_USER_HANDLE_SECRET`                         | 既定は `APP_KEY`。`APP_KEY` を回す可能性があるなら固定値を別に設定してください                                |
| `LOG_CHANNEL`                                         | システム画面が tail するチャンネル。`daily` や独自パスにも追随します                                          |

## 構成

| パス                                                        | 置いてあるもの                                   |
| ----------------------------------------------------------- | ------------------------------------------------ |
| `routes/web.php`, `routes/settings.php`, `routes/tools.php` | ルート（領域ごとに分割）                         |
| `app/Http/Controllers/Tools/`                               | ツールモジュール                                 |
| `database/migrations/`                                      | `tools`, `tags`, `tag_tool`, `tool_submissions`, `tool_requests` |
| `config/catalog.php`                                        | 所属の一覧と 2 つの機能スイッチ                  |
| `app/Sandbox/`                                              | スクリプトを実行するサンドボックスランナー       |
| `config/sandbox.php`, `docker/sandbox/`                     | サンドボックスの上限・ドライバ・コンテナイメージ |
| `resources/js/pages/`                                       | Inertia のページコンポーネント                   |
| `demo/`                                                     | `php artisan demo:seed` が公開するデモカタログ   |
| `.ai/rules/`                                                | 編集前に知っておきたい決定事項と落とし穴         |

## ツールモジュール

`/tools` は社内ツールを集約します。コードとして push するものは何もありません。
ツールは `tools` テーブルの行です。

- **種類**: `link` は URL を開き（外部 https かポータル内のパス）、`embed` は外部
  の https ページをツール自身の画面に埋め込み、`script` はサンドボックスで
  スクリプトを実行します。
- カタログはステータス・カテゴリ・所属で絞り込めます。非推奨のツールは参照用に
  残りますが、ステータスを選ぶまでは表示されません。
- **リクエスト** (`tool_requests`): ツールを自分で作れない人が困りごとを書き、
  開発チームが対応を判断します。受付中 → 対応予定 → 対応中 → 公開済み、
  あるいは見送り / 重複 / 取り下げ。リクエストは依頼者の所属と開発チームだけに
  見えます。リクエストに紐づけた申請が承認された時点で公開済みになるので、
  ツールの公開がそのまま依頼のクローズです。
- **申請** (`tool_submissions`): ツールの登録、動作の変更（URL / スクリプト /
  ランタイム / 入力）、非推奨化は 下書き → 承認待ち → 部署承認済み →
  承認 / 差し戻し を通ります。表示項目（名前・概要・説明・アイコン・タグ）は
  所有者が審査なしでその場で編集します。
- **バージョン**: 承認のたびに承認日時を分単位で刻み（`202608271037`、同じ分に
  2 度目なら `202608271037.2`）、申請者・部署承認者・システム承認者を記録します。
  承認済みの申請がそのまま履歴です。
- **通知**: 申請は全レビュアーにメッセージと通知を送り（受信箱は `/inbox`、
  ヘッダーのベル）、`/admin/approvals/{id}` へのリンクが付きます。判定は申請者に
  返ります。Reverb がライブ更新を流しますが、各画面は毎分ポーリングもするので、
  `reverb:start` のない開発機でも動きます。
- **権限**: `users.role` は `member` / `manager` / `admin` です。まず申請者の部署の
  **部署管理者**が承認し、次に**システム管理者**が公開します。管理者は 1 段階目から
  直接公開でき、部署管理者のいない部署はそのまま管理者に回ります。
- **止められる半分**: `CATALOG_SUBMISSIONS` と `CATALOG_REQUESTS` が、この環境で
  どちらのフローを動かすかを決めます。止めたフローは「禁止」ではなく「不在」で、
  ルートは 404 を返しメニューからも消えます。動いているフローの中で誰が申請できるかは
  別問題で、そちらは 403 です。`CATALOG_SUBMISSIONS=admin` は、リクエストは全員から
  受けつつ登録は開発チームだけ、という形になります。

`/admin` は DB クライアントなしで全テーブルを扱えます。

| 画面               | 編集できるもの                                           |
| ------------------ | -------------------------------------------------------- |
| `/admin/approvals` | 2 段階の承認（部署管理者は自分の部署のみ）               |
| `/admin/requests`  | 開発チームの対応キュー: 受付・見送り・重複統合・公開     |
| `/admin/users`     | 権限と所属（`php artisan carrot:promote` と同じ 2 列）   |
| `/admin/tools`     | 削除済みを含む全行。非推奨化・復帰・完全削除             |
| `/admin/tags`      | カテゴリタグの名称変更と統合                             |
| `/admin/runs`      | サンドボックス実行の閲覧・削除・一括削除                 |
| `/admin/system`    | キュー・ワーカー・サンドボックス・Reverb・実行履歴・ログ |

## サンドボックス

スクリプトツールがアプリ内で走ることはありません。`sandbox` キューに積まれた
`RunToolJob` が承認済みのソースを読み直し、実行依頼時のハッシュと突き合わせてから
`SandboxRunner` に渡します。

| `SANDBOX_DRIVER` | 場所                | 隔離                                                                                                           |
| ---------------- | ------------------- | -------------------------------------------------------------------------------------------------------------- |
| `docker`         | ランナーホスト      | 使い捨てコンテナ: `--network none`、読み取り専用ルート、uid 65534、全 cap 剥奪、メモリ/CPU/PID 上限、`timeout` |
| `bubblewrap`     | Docker のない開発機 | 新規 namespace、ネットワークなし、読み取り専用ルート、専用 /tmp。メモリは ulimit。本番用ではありません         |
| `fake`           | テスト              | 何も実行しません                                                                                               |
| `none`           | Web ホスト          | キューに積むだけ。ここで実行されたら例外を投げます                                                             |

スクリプトツールはインターネットの要否を宣言します（`config.network`: 既定の
`none`、または `internet`）。レビュアーは承認画面でその選択を強調表示で確認でき、
判定前にサンドボックスで実行して試せます。ランナーは `internet` のツールだけを
`SANDBOX_INTERNET_NETWORK`（既定 `bridge` — 出口を管理下に置いたブリッジを
指定してください）に繋ぎ、それ以外は `--network none` です。

入力は `$TOOL_INPUTS` が指す JSON ファイルとして渡り、標準出力がそのまま結果に
なります（`SANDBOX_OUTPUT_BYTES` で打ち切り）。実行はユーザーごとにレート制限され
（`SANDBOX_RATE_LIMIT` / 分）、`SANDBOX_RUN_RETENTION_DAYS` 経過後にスケジュールされた
`carrot:prune-runs` が削除します。

### ランナーホスト

HTTP を提供せずキューだけを処理する別ホストで、同じコードベースを動かします:
`php artisan queue:work --queue=sandbox,default`。必要なのは DB・キュー・ストレージ
の資格情報だけです。

1. 非特権アカウント（例 `carrot-runner`）を `/etc/subuid`・`/etc/subgid` の範囲付きで
   作成します。
2. そのアカウントで rootless Docker を導入し
   （`dockerd-rootless-setuptool.sh install`）、`loginctl enable-linger carrot-runner`
   でログアウト後もデーモンを残します。`docker` グループには**絶対に**入れないで
   ください。root の dockerd ソケットは root そのものです。`DockerSandboxRunner` は
   `docker info` が rootless を報告しない限り起動を拒否します
   （`SANDBOX_REQUIRE_ROOTLESS=false` は開発機だけ）。
3. cgroup v2 の委譲を有効にします（`systemd` の drop-in で
   `Delegate=cpu cpuset io memory pids`）。でないと `--memory` / `--cpus` /
   `--pids-limit` は無視されます。
4. イメージは CI でビルドし（`docker/sandbox/README.md`）、ホストでは pull だけ。
   ランナーはビルドしません。
5. ランナーの `.env` に `SANDBOX_DRIVER=docker` と
   `DOCKER_HOST=unix:///run/user/<uid>/docker.sock` を設定します。Web ホストは
   `SANDBOX_DRIVER=none` のままです。

ローカルでは `composer run dev` が `sandbox,default` のワーカーと Reverb を
Web サーバーの隣で動かすので、`SANDBOX_DRIVER=bubblewrap`（または rootless
デーモンでの `docker`）にすれば 1 台でスクリプトツールが端から端まで動きます。
Reverb がなければ画面はポーリングになるだけです。

## チェック

```bash
composer test        # Pint, PHPStan, Pest
npm run types:check  # tsc
npm run lint         # eslint
```

CI は push と pull request のたびに全部を実行します（`composer setup` のあと
`composer ci:check`、PHP 8.4 / Node 22）。

### テストが守っているもの

Pest のフィーチャーテスト中心で、全体でも数秒です。クラスを直接呼ぶのではなく
HTTP と Inertia の props を叩くので、ルート・ポリシー・画面の props が一度に
守られます。

| スイート                           | 守っているもの                                                                                                                                                                            |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tests/Feature/Tools/`             | カタログ: 何が並ぶか、タグのグループと件数、埋め込みが外部 https のオリジンしか表示しないこと。申請フロー: 下書き、種類ごとの検証、取り下げ、変更・非推奨化の申請、審査なしの表示項目編集。リクエスト: 刻まれる所属と誰が読めるか、止めた機能が 404 を返し、使えない人には 403 を返すこと |
| `tests/Feature/Admin/`             | 2 段階承認、バージョンの刻印（同一分の 2 回目を含む）、スラッグの一意性、差し戻し。リクエストの対応: 受付・理由付きの見送り・重複統合、リクエストに紐づけた申請の承認が公開済みにすること。管理画面: 権限と所属、削除と完全削除、タグの名称変更と統合、実行履歴の削除、システム状態               |
| `tests/Feature/Sandbox/`           | docker コマンドの隔離フラグ全部、出力の打ち切り、ネットワークの選択、承認されていないものを実行しないソースハッシュ検証、ユーザーごとのレート制限、実行の可視範囲と削除                   |
| `tests/Feature/Inbox/`             | 各段階で誰にメッセージと通知が届くか、既読状態、メッセージが受信者以外に見えないこと                                                                                                      |
| `tests/Feature/DemoSeedTest.php`   | `demo:seed` が実際の承認フローを通して公開すること、リクエストも同じ経路で登録し、答えとなるツールの公開がそれを閉じること、再実行しても安全なこと、本番では拒否すること                  |
| `tests/Feature/Auth/`, `Settings/` | ログイン ID による認証、登録の検証、パスワード再設定、2 要素認証とパスキー（スターターキット由来）                                                                                        |

2 つのスイートは環境がなければ自動でスキップされます。`BubblewrapRunnerTest` は
`bwrap` の導入が、`DockerRunnerTest` は `SANDBOX_DOCKER_TESTS=1` と動作する Docker が
必要です。それ以外はインメモリの SQLite に対してどこでも走ります。

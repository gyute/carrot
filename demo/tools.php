<?php

/**
 * What `php artisan demo:seed` publishes. Every URL here is a documentation
 * domain or a portal path - see demo/README.md before adding one.
 */
return [

    /* Deliberately not a real department name. */
    'department' => 'デモ部',

    /*
    | Asks the development team has not turned into tools yet - plus one it
    | has, which the entry in `tools` below answers. Titles are how the
    | command recognises a request on a later run.
    */
    'requests' => [

        [
            'title' => 'デモ: 会議室の空きをまとめて見たい',
            'body' => "今は会議室ごとにカレンダーを開いて空きを探しています。\n朝の予約が重なる時間帯だと 10 分ほどかかります。\n全部屋の空きが一画面で見られると助かります。",
            'categories' => ['ポータル'],
            'needed_by' => '+3 weeks',
            'state' => 'open',
        ],

        [
            'title' => 'デモ: 請求書の消費税を一括で計算したい',
            'body' => "月末に請求書を 1 件ずつ開いて電卓で消費税を出しています。\n100 件あると半日かかり、写し間違いも起きます。",
            'categories' => ['データ'],
            'desired_kind' => 'script',
            'state' => 'in_progress',
        ],

        /* Answered by「デモ: 環境情報」below, so the request closes itself. */
        [
            'title' => 'デモ: サンドボックスの実行環境を知りたい',
            'body' => "スクリプトツールを書きたいのですが、どのランタイムが使えるのか分かりません。\n実行環境を表示するだけのツールがほしいです。",
            'categories' => ['サンプル'],
            'desired_kind' => 'script',
            'state' => 'accepted',
        ],

    ],

    'tools' => [

        [
            'kind' => 'link',
            'name' => 'デモ: 申請一覧',
            'summary' => 'ポータル内のページを開くだけのリンクツールです。',
            'description' => "kind が `link` のツールは URL を開くだけです。\n外部の https と、ポータル内のパス（/ で始まる）が使えます。",
            'icon' => 'file-text',
            'accent' => 'amber',
            'categories' => ['ポータル'],
            'config' => ['url' => '/tools/submissions'],
            'state' => 'published',
        ],

        [
            'kind' => 'link',
            'name' => 'デモ: お知らせ',
            'summary' => '受信箱を開きます。承認の通知が届く場所です。',
            'icon' => 'book-open',
            'accent' => 'emerald',
            'categories' => ['ポータル'],
            'config' => ['url' => '/inbox'],
            'state' => 'published',
        ],

        [
            'kind' => 'embed',
            'name' => 'デモ: サンプルページ',
            'summary' => '外部ページをポータルの中に埋め込んで表示します。',
            'description' => "kind が `embed` のツールは、ツール自身の画面に外部ページを iframe で表示します。\n埋め込めるのは外部の https だけです - ポータル自身のオリジンは、埋め込んだ側に DOM を渡してしまうため拒否されます。",
            'icon' => 'app-window',
            'accent' => 'sky',
            'categories' => ['外部連携'],
            'config' => ['url' => 'https://example.com/'],
            'state' => 'published',
        ],

        [
            'kind' => 'embed',
            'name' => 'デモ: テキスト文書',
            'summary' => 'プレーンテキストの外部文書を埋め込みます。',
            'icon' => 'book-open',
            'accent' => 'slate',
            'categories' => ['ドキュメント'],
            'config' => ['url' => 'https://www.rfc-editor.org/rfc/rfc2119.txt'],
            'state' => 'published',
        ],

        [
            'kind' => 'script',
            'name' => 'デモ: サンドボックス動作確認',
            'summary' => '隔離環境で PHP を実行し、入力した名前に挨拶します。',
            'description' => "kind が `script` のツールはサンドボックスで実行されます。\n入力は JSON ファイルとして渡され（\$TOOL_INPUTS）、標準出力がそのまま結果になります。",
            'icon' => 'terminal',
            'accent' => 'violet',
            'categories' => ['サンプル'],
            'config' => [
                'runtime' => 'php',
                'timeout_sec' => 10,
                'memory_mb' => 64,
                'network' => 'none',
                'inputs' => [
                    ['key' => 'name', 'label' => '名前', 'type' => 'text', 'required' => true, 'options' => null],
                ],
            ],
            'source' => 'scripts/hello.php',
            'state' => 'published',
        ],

        [
            'kind' => 'script',
            'name' => 'デモ: 実行環境の確認',
            'summary' => 'シェルスクリプトが走る隔離環境そのものを表示します。',
            'icon' => 'terminal',
            'accent' => 'rose',
            'categories' => ['サンプル'],
            'config' => [
                'runtime' => 'shell',
                'timeout_sec' => 10,
                'memory_mb' => 64,
                'network' => 'none',
                'inputs' => [
                    ['key' => 'lines', 'label' => '行数', 'type' => 'number', 'required' => false, 'options' => null],
                ],
            ],
            'source' => 'scripts/environment.sh',
            // Approving this is what closes the request of the same subject,
            // so the demo shows the seam rather than a hand-made link.
            'answers' => 'デモ: サンドボックスの実行環境を知りたい',
            'state' => 'published',
        ],

        /* Left waiting on purpose, so the approval screens have something to show. */
        [
            'kind' => 'embed',
            'name' => 'デモ: 承認待ちの申請',
            'summary' => '承認画面を見せるために、申請したまま止めてあります。',
            'icon' => 'link',
            'accent' => 'amber',
            'categories' => ['外部連携'],
            'config' => ['url' => 'https://example.org/'],
            'state' => 'pending',
        ],

    ],

];

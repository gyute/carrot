<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio pages
    |--------------------------------------------------------------------------
    |
    | Pages embedded in the studio tool. This list is the allowlist: users pick
    | a key, never a URL, so nobody can frame an arbitrary page under our own
    | domain. Only external https origins belong here - framing our own origin
    | would hand the embedded page our DOM.
    |
    | Sites that send X-Frame-Options or a frame-ancestors policy refuse to be
    | framed at all; the screen always offers a plain link as a way out.
    |
    */

    'studio' => [

        'example' => [
            'label' => 'サンプルページ',
            'description' => '埋め込み表示の動作確認用のページです。',
            'url' => 'https://example.com/',
        ],

        'carrot_docs' => [
            'label' => '社内ドキュメント',
            'description' => '社内で公開しているドキュメントを開きます。',
            'url' => 'https://www.rfc-editor.org/rfc/rfc2119.txt',
        ],

    ],

];

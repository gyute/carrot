<?php

return [

    'accepted' => ':attributeに同意してください。',
    'after' => ':attributeには:date以降の日付を指定してください。',
    'alpha' => ':attributeには英字のみ使用できます。',
    'alpha_dash' => ':attributeには英数字と-、_のみ使用できます。',
    'alpha_num' => ':attributeには英数字のみ使用できます。',
    'before' => ':attributeには:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:min KBから:max KBの間で指定してください。',
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
        'string' => ':attributeは:min文字から:max文字の間で入力してください。',
    ],
    'boolean' => ':attributeにはtrueかfalseを指定してください。',
    'confirmed' => ':attributeの確認が一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeが正しい日付ではありません。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'email' => ':attributeには正しいメールアドレスを入力してください。',
    'exists' => '選択された:attributeは正しくありません。',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeを入力してください。',
    'image' => ':attributeには画像を指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'integer' => ':attributeには整数を入力してください。',
    'lowercase' => ':attributeには小文字を入力してください。',
    'max' => [
        'array' => ':attributeは:max個以下にしてください。',
        'file' => ':attributeは:max KB以下にしてください。',
        'numeric' => ':attributeは:max以下にしてください。',
        'string' => ':attributeは:max文字以下で入力してください。',
    ],
    'min' => [
        'array' => ':attributeは:min個以上にしてください。',
        'file' => ':attributeは:min KB以上にしてください。',
        'numeric' => ':attributeは:min以上にしてください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeには数値を入力してください。',
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'mixed' => ':attributeには大文字と小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'symbols' => ':attributeには記号を1文字以上含めてください。',
        'uncompromised' => 'この:attributeは漏洩したことがあります。別のものを指定してください。',
    ],
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeを入力してください。',
    'required_if' => ':otherが:valueの場合は:attributeを入力してください。',
    'required_with' => ':valuesがある場合は:attributeを入力してください。',
    'same' => ':attributeと:otherが一致していません。',
    'size' => [
        'array' => ':attributeは:size個にしてください。',
        'file' => ':attributeは:size KBにしてください。',
        'numeric' => ':attributeは:sizeにしてください。',
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'string' => ':attributeには文字列を入力してください。',
    'unique' => 'この:attributeはすでに使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'url' => ':attributeには正しいURLを入力してください。',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    'attributes' => [
        'code' => '認証コード',
        'current_password' => '現在のパスワード',
        'email' => 'メールアドレス',
        'name' => '氏名',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'recovery_code' => 'リカバリーコード',
        'username' => 'ログインID',
    ],

];

<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8" />
</head>
<body style="font-family: sans-serif; font-size: 14px; color: #111827;">
    <h1 style="font-size: 16px;">katsuoooolにお問い合わせがありました</h1>

    <table style="border-collapse: collapse; width: 100%; max-width: 480px;">
        <tbody>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">当サイトを知ったきっかけ</th>
                <td style="padding: 4px 8px;">{{ $data['referral'] ?: '(未回答)' }}</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">ご検討中の内容</th>
                <td style="padding: 4px 8px;">{{ $data['category'] }}</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">お名前</th>
                <td style="padding: 4px 8px;">{{ $data['name'] }}({{ $data['name_kana'] }})</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">会社名・屋号</th>
                <td style="padding: 4px 8px;">{{ $data['company'] ?: '(未入力)' }}</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">電話番号</th>
                <td style="padding: 4px 8px;">{{ $data['tel'] }}</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 4px 8px; white-space: nowrap; vertical-align: top;">メールアドレス</th>
                <td style="padding: 4px 8px;">{{ $data['email'] }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 16px; white-space: pre-wrap;">{{ $data['message'] }}</p>
</body>
</html>

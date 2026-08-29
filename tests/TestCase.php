<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // フロントエンドのビルド成果物(public/build/manifest.json)はテスト実行時に
        // 生成されないうえ、このスターターキットに元々あるフロントエンドの
        // モジュール解決の問題はここでは対象外とする。Feature テストではバックエンドの
        // レスポンスと Inertia のペイロードのみを検証すればよく、コンパイル済み
        // アセットは不要なため無効化する。
        $this->withoutVite();

        // Password::uncompromised() は Http ファサード経由で Have I Been Pwned API を
        // 呼び出す。テストが実際にネットワーク通信を行ったり、外部サービスの
        // 到達不能によって失敗したりしないよう、グローバルにフェイクしておく。
        // 「漏洩済みパスワードが拒否されること」を個別に検証したいテストは、
        // 自前の Http::fake() で上書きできる。
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

# カラー例・UIパターン集の「コピー人気度」計測機能 設計書

## 背景・目的

カラー系ツール(パレット生成・グラデーション生成・色の組み合わせ)は、いずれもユーザーが自分で値を動かして生成するツールのみで、Admin側で用意した「お手本の例」を提示する仕組みがない。UIパターン集(`docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md`)には既にAdmin登録のパターン一覧があるが、どのパターンがよく使われているかを可視化する仕組みがない。

そこで、カラー3ツールにAdmin登録の「例」ギャラリーを新設し、UIパターン集と共通の仕組みで「コピーされた回数」を計測、その回数順に並べることで「人気カラー」「人気のボタン」を自然に可視化する。ログイン必須にすると使われる母数が非常に少なくなる想定のため、匿名での計測を基本とし、`tool_events`で既に採用している日次ソルト付きIPハッシュの手法を流用して連打による水増しを防ぐ。

## スコープ

- 対象: `ColorPalette`・`ColorGradient`・`ColorScheme`の3ツール(新規「例」ギャラリー)、既存のUIパターン集14カテゴリ(コピー回数の計測・並び替えのみ追加)。
- `ColorConvert`・`ColorContrast`は値のチェック/変換ツールであり「例」という概念がなじまないためスコープ外。
- 例(`color_examples`)の作成・編集はAdminの手作業のみ(自動生成・UGC投稿は行わない)。
- ランキング専用ページは作らない。各ツールページ内の一覧をコピー回数順に並べ替えるのみ。
- バッジ演出(「1位」「人気No.1」等)は行わない。

## データモデル

### `color_examples`(新規テーブル)

```php
Schema::create('color_examples', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['palette', 'gradient', 'scheme']);
    $table->string('title');
    $table->json('colors'); // hexの配列。palette/scheme: 2件以上、gradient: 常に2件(start, end)
    $table->unsignedSmallInteger('angle')->nullable(); // type=gradientのみ使用(0-360)
    $table->unsignedInteger('copy_count')->default(0);
    $table->timestamps();

    $table->index(['type', 'copy_count']);
});
```

`App\Models\ColorExample`:

```php
#[Fillable(['type', 'title', 'colors', 'angle'])]
class ColorExample extends Model
{
    protected $attributes = ['copy_count' => 0];

    public const TYPES = ['palette', 'gradient', 'scheme'];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderByDesc('copy_count')->orderBy('id');
    }

    protected function casts(): array
    {
        return ['colors' => 'array'];
    }
}
```

`copy_count`は`increment()`でのみ更新し、Adminフォームからは編集不可(手動で数値を書き換えられると計測の意味がなくなるため)。

### `ui_pattern_snippets`への追加

```php
Schema::table('ui_pattern_snippets', function (Blueprint $table) {
    $table->unsignedInteger('copy_count')->default(0)->after('is_premium');
});
```

`UiPatternSnippet::scopeForCategory()`を次のように変更する:

```php
public function scopeForCategory(Builder $query, string $category): Builder
{
    return $query->where('category', $category)->orderByDesc('copy_count')->orderBy('id');
}
```

### `copy_events`(新規テーブル、重複防止用)

```php
Schema::create('copy_events', function (Blueprint $table) {
    $table->id();
    $table->morphs('copyable'); // copyable_type, copyable_id
    $table->string('ip_hash', 64)->nullable();
    $table->timestamps();

    $table->unique(['copyable_type', 'copyable_id', 'ip_hash']);
});
```

`ip_hash`は`tool_events`と同じ`hash('sha256', $ip . now()->toDateString() . config('app.key'))`方式(日付を含めるため、日が変われば同じIPでも別ハッシュになり再度カウント可能)。IP取得不可(`ip_hash`が`null`になるケース)は稀だが、その場合はユニーク制約に引っかからず毎回カウントされてしまう点は許容する(低リスクな人気指標であり、厳密な不正対策は本機能のスコープ外)。

## バックエンド設計

### `App\Services\CopyPopularityTracker`(新規)

```php
class CopyPopularityTracker
{
    public function record(Model $copyable, ?string $ip): void
    {
        $ipHash = $this->hashIp($ip);

        $created = CopyEvent::query()->firstOrCreate([
            'copyable_type' => $copyable::class,
            'copyable_id' => $copyable->getKey(),
            'ip_hash' => $ipHash,
        ]);

        if ($created->wasRecentlyCreated) {
            $copyable->increment('copy_count');
        }
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.now()->toDateString().config('app.key'));
    }
}
```

`firstOrCreate`のユニーク制約への依存により、同時リクエストのレースコンディションでも二重カウントされない(DBのユニーク制約が最終防衛線)。

### エンドポイント

```php
// routes/web.php
Route::post('/color-palette/examples/{colorExample}/copy', [ColorPaletteController::class, 'copyExample'])
    ->middleware('throttle:30,1')
    ->name('color-palette.examples.copy');
// color-gradient, color-scheme も同様に3本

Route::post('/ui-pattern-snippets/{uiPatternSnippet}/copy', [UiPatternCopyController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('ui-pattern-snippets.copy');
```

各カラーツールの`copyExample()`は対象の`type`と一致しない`ColorExample`が渡された場合`404`を返す(例: `/color-palette/examples/{id}/copy`に`type=gradient`のIDを渡せない)。

```php
public function copyExample(ColorExample $colorExample, Request $request, CopyPopularityTracker $tracker): JsonResponse
{
    abort_unless($colorExample->type === 'palette', 404);

    $tracker->record($colorExample, $request->ip());

    return response()->json(['status' => 'ok']);
}
```

UIパターン集はカテゴリを問わず1本の`UiPatternCopyController`で済む(`UiPatternSnippet`はカテゴリに関わらず同じモデルのため)。

### Admin CRUD(`color_examples`)

既存の`Admin\UiPatternSnippetController`と同じ構成で`Admin\ColorExampleController`(index/create/store/edit/update/destroy)を新設する。

- フォーム(`resources/js/components/admin/ColorExampleForm.vue`): `type`セレクト(palette/gradient/scheme)、`title`、`colors`(カンマ区切りhex文字列で入力→サーバー側で配列にパース・バリデーション)、`angle`(type=gradientの場合のみ表示、0-360必須)。
- バリデーション: `type`は`in:palette,gradient,scheme`、`colors`は各要素`/^#[0-9a-fA-F]{6}$/`、`type=palette`なら`colors`は2〜6件、`type=gradient`なら`colors`はちょうど2件かつ`angle`必須、`type=scheme`なら`colors`は2〜4件。
- 一覧(`Index.vue`)に`copy_count`列を追加(降順ソートはデフォルトでは行わず、Admin確認用に生の数値を表示するのみ)。

## フロントエンド設計

### カラー3ツール共通コンポーネント

`resources/js/components/tools/ColorExampleGallery.vue`(新規、3ツール共通)

- props: `examples: ColorExample[]`, `type: 'palette' | 'gradient' | 'scheme'`
- 各exampleをカード表示。パレット/組み合わせはswatchを横並び、グラデーションはプレビュー矩形(`background: linear-gradient(...)`)。
- 「コピー」ボタン1つ/例。クリックで:
  1. `navigator.clipboard.writeText(...)`(パレット/組み合わせ: カンマ区切りhex文字列、グラデーション: `background: linear-gradient(${angle}deg, ${colors[0]}, ${colors[1]});`)
  2. `axios.post(route の copy エンドポイント)`を実行(失敗しても無視。計測専用でUX をブロックしない、既存の`/color-palette/track`と同じ方針)

各ページ(`ColorPalette.vue`等)は、既存の生成ツールUIの**下**に`<ColorExampleGallery :examples="examples" type="palette" />`を追加する。`examples`propsはコントローラーから`ColorExample::ofType('palette')->get()`で渡す。

### `PatternCard.vue`

既存の「コピー」ボタンの処理に、対応する`copy`エンドポイントへの`axios.post`呼び出しを追加する(表示・タブ切り替え等の既存UIは変更しない)。ロック状態(`locked: true`)のパターンはそもそもコピーボタンを表示しないため、計測対象は非ロックパターンのみで自然に絞られる。

## エラーハンドリング・エッジケース

- `examples`が0件のツール/カテゴリでは、ギャラリーセクション自体を非表示にする(空の見出しだけ出さない)。
- クリップボードAPIが使えない環境(既存の各ツールと同じ)では、コピー自体は失敗するが計測リクエストは送らない(コピーできていないものをカウントしない)。
- 同一ユーザーが日をまたいで同じ例を複数回コピーした場合は、日ごとに1回ずつカウントされる(仕様として許容)。

## テスト方針

- `CopyPopularityTracker`のユニットテスト: 同一(copyable, IP, 同日)の連続呼び出しは1回しか`copy_count`を増やさないこと、IPが変われば別カウントになること、日付が変わればハッシュが変わり再度カウントされることを検証。
- `ColorExample`モデル: `ofType()`スコープの絞り込み・並び順(copy_count降順)を検証。
- 各カラーコントローラーのFeatureテスト: `copyExample`エンドポイントが対象typeと異なるIDで404になること、正常系で`copy_count`が1増えること、連打(同一テストリクエストを2回)しても1しか増えないこと。
- `UiPatternCopyController`のFeatureテスト: 同上のパターンで`ui_pattern_snippets.copy_count`が更新されること。
- `ColorExampleGallery.vue`のコンポーネントテスト: クリップボードAPIをモックし、コピー時に正しい文字列が渡されること・計測APIが呼ばれること。
- Admin側`ColorExampleController`のFeatureテスト: バリデーション(colors件数・hex形式・angle必須条件)。

## 影響範囲まとめ(実装計画のためのファイルリスト予告)

- Migration: `color_examples`新規作成、`copy_events`新規作成、`ui_pattern_snippets`に`copy_count`追加
- `app/Models/ColorExample.php`(新規)、`app/Models/CopyEvent.php`(新規)
- `app/Services/CopyPopularityTracker.php`(新規)
- `app/Http/Controllers/Tools/ColorPaletteController.php`・`ColorGradientController.php`・`ColorSchemeController.php`: `show()`に`examples`props追加、`copyExample()`アクション追加
- `app/Http/Controllers/Tools/UiPatternCopyController.php`(新規)
- `app/Models/UiPatternSnippet.php`: `forCategory()`の並び順変更
- `app/Http/Controllers/Admin/ColorExampleController.php`(新規)、対応するAdmin向けVueページ一式(`Index.vue`/`Create.vue`/`Edit.vue`)、`ColorExampleForm.vue`
- `resources/js/components/tools/ColorExampleGallery.vue`(新規)
- `resources/js/pages/ColorPalette.vue`・`ColorGradient.vue`・`ColorScheme.vue`: ギャラリー組み込み
- `resources/js/components/tools/PatternCard.vue`: コピー計測呼び出し追加
- `routes/web.php`: 上記エンドポイント追加
- テスト一式(上記テスト方針を参照)

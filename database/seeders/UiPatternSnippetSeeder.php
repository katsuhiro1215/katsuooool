<?php

namespace Database\Seeders;

use App\Models\UiPatternSnippet;
use Illuminate\Database\Seeder;

class UiPatternSnippetSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UiPatternSnippet::create([
            'category' => 'button',
            'title' => '斜線シャドウボタン',
            'html' => '<a href="#"><span>Button</span></a>',
            'css' => <<<'CSS'
a {
    display: block;
    position: relative;
    color: #333;
    text-decoration: none;
}

a span {
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
    padding: 20px 10px;
    width: 260px;
    font-size: 18px;
    font-weight: 700;
    background-color: #90be70;
    z-index: 2;
}

a::before {
    content: "";
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 100%;
    height: 100%;
    background-image: repeating-linear-gradient(-45deg, #2b550e 0, #2b550e 2px, rgba(0 0 0 / 0) 0%, rgba(0 0 0 / 0) 50%);
    background-size: 8px 8px;
    z-index: 1;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'button',
            'title' => 'グラデーションボタン',
            'html' => '<a href="#">Get Started</a>',
            'css' => <<<'CSS'
a {
    display: inline-block;
    padding: 14px 32px;
    border-radius: 999px;
    background: linear-gradient(135deg, #6366f1, #ec4899);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

a:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(99, 102, 241, 0.45);
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'button',
            'title' => 'アウトラインホバーボタン',
            'html' => '<a href="#">Learn More</a>',
            'css' => <<<'CSS'
a {
    display: inline-block;
    position: relative;
    padding: 12px 28px;
    border: 2px solid #111827;
    border-radius: 6px;
    color: #111827;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    overflow: hidden;
    z-index: 1;
    transition: color 0.2s ease;
}

a::before {
    content: "";
    position: absolute;
    inset: 0;
    background: #111827;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.25s ease;
    z-index: -1;
}

a:hover {
    color: #fff;
}

a:hover::before {
    transform: scaleX(1);
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'card',
            'title' => 'シャドウカード',
            'html' => <<<'HTML'
<div class="card">
    <h3>タイトル</h3>
    <p>カードの説明文がここに入ります。</p>
</div>
HTML,
            'css' => <<<'CSS'
.card {
    width: 260px;
    padding: 20px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}

.card h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 700;
    color: #111827;
}

.card p {
    margin: 0;
    font-size: 14px;
    color: #6b7280;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'pagination',
            'title' => 'ピル型ページネーション',
            'html' => <<<'HTML'
<nav class="pagination">
    <a href="#">1</a>
    <a href="#" class="active">2</a>
    <a href="#">3</a>
    <a href="#">次へ</a>
</nav>
HTML,
            'css' => <<<'CSS'
.pagination {
    display: flex;
    gap: 6px;
}

.pagination a {
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 14px;
    color: #374151;
    text-decoration: none;
    background: #f3f4f6;
}

.pagination a.active {
    background: #111827;
    color: #fff;
    font-weight: 700;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'modal',
            'title' => 'シンプルモーダル',
            'html' => <<<'HTML'
<div class="modal-overlay">
    <div class="modal">
        <h3>確認</h3>
        <p>この操作を実行してもよろしいですか？</p>
        <div class="modal-actions">
            <button class="btn-secondary">キャンセル</button>
            <button class="btn-primary">実行する</button>
        </div>
    </div>
</div>
HTML,
            'css' => <<<'CSS'
.modal-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    padding: 24px;
    border-radius: 8px;
}

.modal {
    width: 280px;
    padding: 20px;
    border-radius: 10px;
    background: #fff;
}

.modal h3 {
    margin: 0 0 8px;
    font-size: 16px;
    font-weight: 700;
}

.modal p {
    margin: 0 0 16px;
    font-size: 14px;
    color: #6b7280;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.btn-secondary {
    padding: 8px 14px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #fff;
    font-size: 13px;
}

.btn-primary {
    padding: 8px 14px;
    border-radius: 6px;
    border: none;
    background: #111827;
    color: #fff;
    font-size: 13px;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'toast',
            'title' => '成功トースト',
            'html' => <<<'HTML'
<div class="toast">
    <span class="toast-icon">✓</span>
    <span>保存しました</span>
</div>
HTML,
            'css' => <<<'CSS'
.toast {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    border-radius: 8px;
    background: #111827;
    color: #fff;
    font-size: 14px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.toast-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #22c55e;
    font-size: 12px;
    font-weight: 700;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'loading',
            'title' => 'スピナーローディング',
            'html' => '<div class="spinner"></div>',
            'css' => <<<'CSS'
.spinner {
    width: 36px;
    height: 36px;
    border: 4px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'skeleton',
            'title' => 'カード用スケルトン',
            'html' => <<<'HTML'
<div class="skeleton-card">
    <div class="skeleton-line skeleton-title"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line skeleton-short"></div>
</div>
HTML,
            'css' => <<<'CSS'
.skeleton-card {
    width: 240px;
    padding: 16px;
    border-radius: 10px;
    background: #fff;
}

.skeleton-line {
    height: 12px;
    margin-bottom: 8px;
    border-radius: 6px;
    background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 37%, #e5e7eb 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
}

.skeleton-title {
    width: 60%;
    height: 16px;
}

.skeleton-short {
    width: 40%;
}

@keyframes shimmer {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'form-input',
            'title' => 'フローティングラベル入力',
            'html' => <<<'HTML'
<div class="field">
    <input type="text" id="name" placeholder=" " />
    <label for="name">お名前</label>
</div>
HTML,
            'css' => <<<'CSS'
.field {
    position: relative;
    width: 240px;
}

.field input {
    width: 100%;
    padding: 16px 12px 6px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}

.field label {
    position: absolute;
    left: 12px;
    top: 11px;
    font-size: 14px;
    color: #9ca3af;
    transition: 0.15s ease;
    pointer-events: none;
}

.field input:focus + label,
.field input:not(:placeholder-shown) + label {
    top: 4px;
    font-size: 11px;
    color: #6366f1;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'form-textarea',
            'title' => 'シンプルテキストエリア',
            'html' => <<<'HTML'
<label class="textarea-field">
    お問い合わせ内容
    <textarea rows="4" placeholder="内容をご記入ください"></textarea>
</label>
HTML,
            'css' => <<<'CSS'
.textarea-field {
    display: block;
    width: 260px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.textarea-field textarea {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
}

.textarea-field textarea:focus {
    outline: none;
    border-color: #6366f1;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'form-check',
            'title' => 'カスタムチェックボックス',
            'html' => <<<'HTML'
<label class="check">
    <input type="checkbox" checked />
    <span>利用規約に同意する</span>
</label>
HTML,
            'css' => <<<'CSS'
.check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #374151;
}

.check input[type='checkbox'] {
    width: 18px;
    height: 18px;
    accent-color: #6366f1;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'form-select',
            'title' => 'シンプルセレクト',
            'html' => <<<'HTML'
<label class="select-field">
    プラン
    <select>
        <option>フリー</option>
        <option>プレミアム</option>
    </select>
</label>
HTML,
            'css' => <<<'CSS'
.select-field {
    display: block;
    width: 220px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.select-field select {
    display: block;
    width: 100%;
    margin-top: 6px;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    box-sizing: border-box;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'form-group',
            'title' => 'ラベル+ヘルプテキスト付きグループ',
            'html' => <<<'HTML'
<div class="form-group">
    <label for="email">メールアドレス</label>
    <input type="email" id="email" placeholder="you@example.com" />
    <p class="help">確認メールを送信します。</p>
</div>
HTML,
            'css' => <<<'CSS'
.form-group {
    width: 260px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group .help {
    margin: 6px 0 0;
    font-size: 12px;
    color: #9ca3af;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'header',
            'title' => 'シンプルヘッダー',
            'html' => <<<'HTML'
<header class="site-header">
    <span class="logo">Logo</span>
    <nav>
        <a href="#">機能</a>
        <a href="#">料金</a>
        <a href="#">お問い合わせ</a>
    </nav>
</header>
HTML,
            'css' => <<<'CSS'
.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 14px 20px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    box-sizing: border-box;
}

.site-header .logo {
    font-weight: 700;
    color: #111827;
}

.site-header nav {
    display: flex;
    gap: 20px;
}

.site-header nav a {
    font-size: 14px;
    color: #374151;
    text-decoration: none;
}
CSS,
        ]);

        UiPatternSnippet::create([
            'category' => 'footer',
            'title' => 'シンプルフッター',
            'html' => <<<'HTML'
<footer class="site-footer">
    <p>&copy; 2026 Example Inc.</p>
    <nav>
        <a href="#">利用規約</a>
        <a href="#">プライバシーポリシー</a>
    </nav>
</footer>
HTML,
            'css' => <<<'CSS'
.site-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 16px 20px;
    background: #111827;
    color: #9ca3af;
    font-size: 13px;
    box-sizing: border-box;
}

.site-footer nav {
    display: flex;
    gap: 16px;
}

.site-footer nav a {
    color: #d1d5db;
    text-decoration: none;
}
CSS,
        ]);
    }
}

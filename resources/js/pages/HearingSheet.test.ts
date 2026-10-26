// @vitest-environment jsdom
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, expect, test, vi } from 'vitest';

const post = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({
    default: { post, isAxiosError: () => false },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({
        url: '/hearing-sheet',
        props: { auth: { user: null }, toolNav: [] },
    }),
    router: { get: () => {} },
}));

import { STORAGE_KEY } from '@/lib/hearing/sheet';
import HearingSheet from './HearingSheet.vue';

// Modal は dialog.showModal() を使うので、中身が見えるスタブに差し替える
const Modal = {
    props: ['show'],
    template: '<div v-if="show" data-modal><slot /></div>',
};

const mountPage = () => mount(HearingSheet, { global: { stubs: { Modal } } });

beforeEach(() => {
    localStorage.clear();
    post.mockReset();
    vi.stubGlobal('route', (name: string) => `/${name}`);
    // jsdom には scrollIntoView が無い
    Element.prototype.scrollIntoView = vi.fn();
    post.mockResolvedValue({ data: {} });
    vi.useRealTimers();
    Object.assign(navigator, {
        clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    });
});

test('6つのステップのタブが並び、最初は基本情報', () => {
    const wrapper = mountPage();

    expect(wrapper.findAll('nav button[data-step]')).toHaveLength(6);
    expect(wrapper.find('[data-step-title]').text()).toBe('基本情報');
    expect(wrapper.find('[data-percent]').text()).toBe('0%');
});

test('タブとボタンでステップを移動できる', async () => {
    const wrapper = mountPage();

    await wrapper.find('button[data-step="3"]').trigger('click');
    expect(wrapper.find('[data-step-title]').text()).toBe('デザイン');

    await wrapper.find('[data-prev]').trigger('click');
    expect(wrapper.find('[data-step-title]').text()).toBe('目的とターゲット');

    await wrapper.find('[data-next]').trigger('click');
    expect(wrapper.find('[data-step-title]').text()).toBe('デザイン');
});

test('最後のステップの「内容を確認する」で確認ダイアログが開く', async () => {
    const wrapper = mountPage();

    await wrapper.find('button[data-step="5"]').trigger('click');
    await wrapper.find('[data-next]').trigger('click');

    expect(wrapper.find('[data-modal]').text()).toContain(
        'ヒアリング内容を確認',
    );
});

test('入力すると進捗とプレビューに反映され、ブラウザに保存される', async () => {
    vi.useFakeTimers();
    const wrapper = mountPage();

    await wrapper.find('#f-project').setValue('テストサイト');

    expect(wrapper.find('[data-percent]').text()).not.toBe('0%');
    expect(wrapper.find('.hearing-paper').text()).toContain('テストサイト');

    vi.advanceTimersByTime(300);
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
    expect(saved.answers.project).toBe('テストサイト');
});

test('保存済みの内容を開いたときに復元する', () => {
    localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
            version: 1,
            step: 1,
            answers: { project: '復元サイト' },
            pending: {},
            updatedAt: null,
        }),
    );
    const wrapper = mountPage();

    expect(wrapper.find('[data-step-title]').text()).toBe('事業と課題');
    expect(wrapper.find('[data-toolbar]').text()).toContain('復元サイト');
});

test('壊れた保存データがあっても空の状態で開く', () => {
    localStorage.setItem(STORAGE_KEY, '{broken');
    const wrapper = mountPage();

    expect(wrapper.find('[data-step-title]').text()).toBe('基本情報');
    expect(wrapper.text()).toContain('保存データを読めません');
});

test('確認待ちにすると進捗の回答済みに含まれない', async () => {
    const wrapper = mountPage();

    await wrapper.find('#f-project').setValue('テストサイト');
    const answered = wrapper.find('[data-progress-detail]').text();
    expect(answered).toContain('1 /');

    await wrapper.find('#field-project [data-pending]').trigger('click');

    expect(wrapper.find('[data-progress-detail]').text()).toContain(
        '確認待ち 1',
    );
    expect(wrapper.find('[data-progress-detail]').text()).toContain('0 /');
});

test('形式が違うメールアドレスにはエラーが出る', async () => {
    const wrapper = mountPage();

    await wrapper.find('#f-email').setValue('abc');

    expect(wrapper.find('#field-email [data-error]').text()).toContain(
        'メールアドレスの形式',
    );
});

test('繰り返し項目は追加と削除ができる', async () => {
    const wrapper = mountPage();
    await wrapper.find('button[data-step="1"]').trigger('click');

    const field = '#field-competitors';
    expect(wrapper.findAll(`${field} textarea`)).toHaveLength(1);

    await wrapper.find(`${field} [data-add]`).trigger('click');
    expect(wrapper.findAll(`${field} textarea`)).toHaveLength(2);

    await wrapper.find(`${field} [data-remove]`).trigger('click');
    expect(wrapper.findAll(`${field} textarea`)).toHaveLength(1);
});

test('サンプルを読み込み、リセットで空に戻せる', async () => {
    const wrapper = mountPage();

    await wrapper.find('[data-action="sample"]').trigger('click');
    expect(wrapper.find('[data-toolbar]').text()).toContain(
        'こもれび工務店 コーポレートサイト',
    );

    await wrapper.find('[data-action="reset"]').trigger('click');
    await wrapper.find('[data-confirm="reset"]').trigger('click');
    expect(wrapper.find('[data-toolbar]').text()).toContain(
        '新しいヒアリングシート',
    );
    expect(wrapper.find('[data-percent]').text()).toBe('0%');
});

test('内容があるときのサンプル読み込みは、置き換えの確認が出る', async () => {
    const wrapper = mountPage();
    await wrapper.find('#f-project').setValue('入力中');

    await wrapper.find('[data-action="sample"]').trigger('click');
    expect(wrapper.find('[data-modal]').text()).toContain('置き換えます');
    expect(wrapper.find('[data-toolbar]').text()).toContain('入力中');

    await wrapper.find('[data-confirm="replace"]').trigger('click');
    expect(wrapper.find('[data-toolbar]').text()).toContain('こもれび');
});

test('「未回答を隠す」でテキストから未回答の項目が消える', async () => {
    const wrapper = mountPage();
    await wrapper.find('#f-project').setValue('テストサイト');
    await wrapper.find('[data-mode="text"]').trigger('click');

    expect(wrapper.find('[data-text-preview]').text()).toContain(
        'ご担当者名：未回答',
    );

    await wrapper.find('[data-hide-empty]').setValue(true);
    expect(wrapper.find('[data-text-preview]').text()).not.toContain(
        'ご担当者名',
    );
});

test('テキストコピーはクリップボードに書き込み、計測を送る', async () => {
    vi.useFakeTimers();
    const wrapper = mountPage();
    await wrapper.find('#f-project').setValue('テストサイト');

    await wrapper.find('[data-action="copy"]').trigger('click');
    await flushPromises();
    vi.advanceTimersByTime(600);

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        expect.stringContaining('テストサイト'),
    );
    expect(post).toHaveBeenCalledWith('/hearing-sheet/track');
});

test('確認ダイアログの項目から該当の入力欄へ移動する', async () => {
    const wrapper = mountPage();
    await wrapper.find('button[data-step="5"]').trigger('click');
    await wrapper.find('[data-action="review"]').trigger('click');

    await wrapper.find('[data-jump="project"]').trigger('click');
    await flushPromises();

    expect(wrapper.find('[data-step-title]').text()).toBe('基本情報');
});

import axios from 'axios';
import {
    computed,
    getCurrentInstance,
    onBeforeUnmount,
    ref,
    toValue,
} from 'vue';
import type { MaybeRefOrGetter } from 'vue';

import { isPreviewable, matchResults } from '@/lib/imageBatch';
import type { BatchResponse } from '@/lib/imageBatch';
import { resolveErrorMessage } from '@/lib/toolErrorMessage';

export interface BatchFile {
    id: string;
    file: File;
    previewUrl: string | null;
}

export interface UseImageBatchOptions {
    endpoint: string;
    maxFiles: MaybeRefOrGetter<number>;
    fallbackError: string;
}

/**
 * 画像ツール共通の状態。ファイル・サムネイル用 URL・送信・結果・エラーを持つ。
 */
export function useImageBatch(options: UseImageBatchOptions) {
    const files = ref<BatchFile[]>([]);
    const response = ref<BatchResponse | null>(null);
    const isProcessing = ref(false);
    const errorMessage = ref('');
    const showPremiumUpsell = ref(false);
    let nextId = 0;

    const phase = computed<'empty' | 'ready' | 'processing' | 'done'>(() => {
        if (isProcessing.value) {
            return 'processing';
        }

        if (response.value !== null) {
            return 'done';
        }

        return files.value.length === 0 ? 'empty' : 'ready';
    });

    const matched = computed(() =>
        response.value === null
            ? []
            : matchResults(
                  files.value.map((item) => item.file.name),
                  response.value.results,
              ),
    );

    function makeItem(file: File): BatchFile {
        return {
            id: `file-${nextId++}`,
            file,
            previewUrl: isPreviewable(file) ? URL.createObjectURL(file) : null,
        };
    }

    function revoke(item: BatchFile): void {
        if (item.previewUrl !== null) {
            URL.revokeObjectURL(item.previewUrl);
        }
    }

    function resetResults(): void {
        response.value = null;
        errorMessage.value = '';
        showPremiumUpsell.value = false;
    }

    function add(list: FileList | File[] | null): void {
        if (list === null || isProcessing.value) {
            return;
        }

        const incoming = Array.from(list);

        if (incoming.length === 0) {
            return;
        }

        resetResults();

        const max = toValue(options.maxFiles);
        const room = Math.max(0, max - files.value.length);

        if (incoming.length > room) {
            errorMessage.value = `一度にアップロードできるのは最大${max}枚までです。`;
        }

        files.value = [
            ...files.value,
            ...incoming.slice(0, room).map(makeItem),
        ];
    }

    function remove(id: string): void {
        const target = files.value.find((item) => item.id === id);

        if (target === undefined || isProcessing.value) {
            return;
        }

        revoke(target);
        files.value = files.value.filter((item) => item.id !== id);
        resetResults();
    }

    function clear(): void {
        files.value.forEach(revoke);
        files.value = [];
        resetResults();
    }

    async function submit(fields: Record<string, string> = {}): Promise<void> {
        if (files.value.length === 0 || isProcessing.value) {
            return;
        }

        isProcessing.value = true;
        resetResults();

        const formData = new FormData();
        files.value.forEach((item) => formData.append('files[]', item.file));
        Object.entries(fields).forEach(([key, value]) =>
            formData.append(key, value),
        );

        try {
            const { data } = await axios.post<BatchResponse>(
                options.endpoint,
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            );

            response.value = data;
        } catch (error) {
            const resolved = resolveErrorMessage(
                error,
                toValue(options.maxFiles),
                options.fallbackError,
            );
            errorMessage.value = resolved.message;
            showPremiumUpsell.value = resolved.showUpsell;
        } finally {
            isProcessing.value = false;
        }
    }

    // コンポーネントの外（テストなど）で呼ばれたときは、登録しない
    if (getCurrentInstance() !== null) {
        onBeforeUnmount(() => files.value.forEach(revoke));
    }

    return {
        files,
        response,
        matched,
        isProcessing,
        phase,
        errorMessage,
        showPremiumUpsell,
        add,
        remove,
        clear,
        resetResults,
        submit,
    };
}

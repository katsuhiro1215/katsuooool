export interface SuccessResult {
    original_name: string;
    status: 'success';
    input_bytes: number;
    output_bytes: number;
    reduction_percent: number;
    download_url: string;
    width?: number;
    height?: number;
    clamped?: boolean;
    has_gps?: boolean;
}

export interface FailedResult {
    original_name: string;
    status: 'rejected' | 'failed';
    reason: string;
}

export type BatchResult = SuccessResult | FailedResult;

export interface BatchResponse {
    results: BatchResult[];
    batch_download_url: string | null;
}

export interface ThumbItem {
    id: string;
    name: string;
    size: number;
    previewUrl: string | null;
    state: 'ready' | 'processing' | 'success' | 'failed';
    result: BatchResult | null;
}

export function isSuccess(result: BatchResult | null): result is SuccessResult {
    return result !== null && result.status === 'success';
}

const PREVIEWABLE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/avif',
    'image/gif',
];

/** ブラウザがそのまま表示できる画像か（HEIC は不可）。MIME が空のときは拡張子で判定する。 */
export function isPreviewable(file: { type: string; name: string }): boolean {
    if (file.type !== '') {
        return PREVIEWABLE_TYPES.includes(file.type);
    }

    return /\.(jpe?g|png|webp|avif|gif)$/i.test(file.name);
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(1)} MB`;
}

/**
 * 結果は「成功→拒否」の順で返るため、ファイル名で元のファイルに対応づける。
 * 同じ名前が複数あっても、先頭から1つずつ使う。
 */
export function matchResults(
    names: string[],
    results: BatchResult[],
): Array<BatchResult | null> {
    const used = new Set<number>();

    return names.map((name) => {
        const index = results.findIndex(
            (result, i) => !used.has(i) && result.original_name === name,
        );

        if (index === -1) {
            return null;
        }

        used.add(index);

        return results[index];
    });
}

export function summarize(results: BatchResult[]): {
    count: number;
    inputBytes: number;
    outputBytes: number;
    percent: number;
} {
    const successes = results.filter(isSuccess);
    const inputBytes = successes.reduce((sum, r) => sum + r.input_bytes, 0);
    const outputBytes = successes.reduce((sum, r) => sum + r.output_bytes, 0);

    return {
        count: successes.length,
        inputBytes,
        outputBytes,
        percent:
            inputBytes > 0
                ? Math.round((1 - outputBytes / inputBytes) * 100)
                : 0,
    };
}

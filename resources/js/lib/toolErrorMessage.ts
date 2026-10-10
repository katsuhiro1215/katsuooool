import axios from 'axios';

interface ResolvedError {
    message: string;
    showUpsell: boolean;
}

// Premium の一度に処理できる枚数（config/image_tools.php の max_files.premium と同じ値）
export const PREMIUM_MAX_FILES = 20;

/**
 * A 422 from these tools' `files` array validation can mean two very
 * different things: too many files (batch-count limit) or a single file
 * that never made it to Laravel intact (e.g. rejected by the server's
 * upload_max_filesize/post_max_size before validation ever ran). Laravel
 * reports the former under the `files` key and the latter under a
 * per-index key like `files.0` — only the former is actually about the
 * free/Premium batch limit.
 */
export function resolveErrorMessage(
    error: unknown,
    maxFiles: number,
    genericMessage: string,
): ResolvedError {
    if (!axios.isAxiosError(error)) {
        return { message: genericMessage, showUpsell: false };
    }

    if (error.response?.status === 422) {
        const errors =
            (error.response.data as { errors?: Record<string, string[]> })
                ?.errors ?? {};

        if ('files' in errors) {
            if (maxFiles < PREMIUM_MAX_FILES) {
                return {
                    message: `無料プランでは一度に${maxFiles}枚までです。Premium(¥600/月)なら${PREMIUM_MAX_FILES}枚まで一括処理できます。`,
                    showUpsell: true,
                };
            }

            return { message: 'ファイル数が多すぎます。', showUpsell: false };
        }

        return {
            message:
                'ファイルのアップロードに失敗しました。ファイルサイズ(1ファイル10MBまで)をご確認のうえ、再度お試しください。',
            showUpsell: false,
        };
    }

    if (error.response?.status === 429) {
        if (maxFiles < PREMIUM_MAX_FILES) {
            return {
                message:
                    'リクエストが多すぎます。しばらく時間をおいてから再度お試しください。Premiumならレート制限が緩和されます。',
                showUpsell: true,
            };
        }

        return {
            message:
                'リクエストが多すぎます。しばらく時間をおいてから再度お試しください。',
            showUpsell: false,
        };
    }

    if (error.response?.status === 503) {
        return {
            message:
                '現在アクセスが集中しています。少し待ってから、もう一度お試しください。',
            showUpsell: false,
        };
    }

    return { message: genericMessage, showUpsell: false };
}

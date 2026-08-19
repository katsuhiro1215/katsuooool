export type CodecResult =
    { success: true; output: string } | { success: false; error: string };

export function encodeUrl(text: string): string {
    return encodeURIComponent(text);
}

export function decodeUrl(text: string): CodecResult {
    try {
        return { success: true, output: decodeURIComponent(text) };
    } catch {
        return {
            success: false,
            error: '不正なURLエンコード文字列です。',
        };
    }
}

/**
 * `btoa`/`atob` operate on a "binary string" (one UTF-16 code unit per
 * byte), not arbitrary UTF-8 text, so a non-ASCII string round-tripped
 * through them directly would corrupt multi-byte characters. Routing
 * through TextEncoder/TextDecoder first makes this correctly UTF-8-aware.
 */
export function encodeBase64(text: string): string {
    const bytes = new TextEncoder().encode(text);
    let binary = '';

    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary);
}

export function decodeBase64(text: string): CodecResult {
    try {
        const binary = atob(text);
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));

        return { success: true, output: new TextDecoder().decode(bytes) };
    } catch {
        return {
            success: false,
            error: '不正なBase64文字列です。',
        };
    }
}

export interface SeoTextCheckResult {
    characterCount: number;
    byteCount: number;
    withinRecommended: boolean;
}

const TITLE_RECOMMENDED_MAX = 35;
const DESCRIPTION_RECOMMENDED_MAX = 160;

export function checkTitleLength(text: string): SeoTextCheckResult {
    return buildResult(text, TITLE_RECOMMENDED_MAX);
}

export function checkDescriptionLength(text: string): SeoTextCheckResult {
    return buildResult(text, DESCRIPTION_RECOMMENDED_MAX);
}

function buildResult(text: string, recommendedMax: number): SeoTextCheckResult {
    const characterCount = Array.from(text).length;
    const byteCount = new TextEncoder().encode(text).length;

    return {
        characterCount,
        byteCount,
        withinRecommended: characterCount <= recommendedMax,
    };
}

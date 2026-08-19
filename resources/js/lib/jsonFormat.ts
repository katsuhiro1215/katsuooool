export type JsonFormatResult =
    { success: true; output: string } | { success: false; error: string };

export function formatJson(input: string, indent = 2): JsonFormatResult {
    try {
        const parsed: unknown = JSON.parse(input);

        return { success: true, output: JSON.stringify(parsed, null, indent) };
    } catch (error) {
        return {
            success: false,
            error: error instanceof Error ? error.message : String(error),
        };
    }
}

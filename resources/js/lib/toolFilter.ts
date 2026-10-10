export interface Tool {
    slug: string;
    name: string;
    category: string;
    description: string;
    href: string;
}

export interface CategoryTab {
    key: string;
    label: string;
}

export const CATEGORY_TABS: CategoryTab[] = [
    { key: 'all', label: 'すべて' },
    { key: 'image', label: '画像' },
    { key: 'color', label: '色' },
    { key: 'css', label: 'CSS' },
    { key: 'pattern', label: 'Pattern' },
    { key: 'generator', label: 'Generator' },
    { key: 'developer', label: 'Developer' },
    { key: 'seo', label: 'SEO' },
    { key: 'ui-pattern', label: 'UI Pattern' },
];

export type ToolNavItem = Pick<Tool, 'slug' | 'name' | 'category' | 'href'>;

export function normalizeCategory(key: string | null | undefined): string {
    return key && CATEGORY_TABS.some((tab) => tab.key === key) ? key : 'all';
}

export function countByCategory(tools: ToolNavItem[]): Record<string, number> {
    const counts: Record<string, number> = { all: tools.length };

    for (const tool of tools) {
        counts[tool.category] = (counts[tool.category] ?? 0) + 1;
    }

    return counts;
}

/**
 * Inertia の page.url を、パスとクエリに分解する。
 * #フラグメント（Inertia が戻る操作などで付ける）と、パス末尾のスラッシュは取り除く。
 */
export function parsePageUrl(url: string): {
    path: string;
    params: URLSearchParams;
} {
    const parsed = new URL(url, 'http://localhost');
    const path =
        parsed.pathname.length > 1
            ? parsed.pathname.replace(/\/+$/, '')
            : parsed.pathname;

    return { path, params: parsed.searchParams };
}

/**
 * 現在の URL から、サイドバーで強調するカテゴリを求める。
 * トップは ?category=、ツール画面はそのツールのカテゴリ。どちらでもなければ null。
 */
export function resolveActiveCategory(
    url: string,
    tools: ToolNavItem[],
): string | null {
    const { path, params } = parsePageUrl(url);

    if (path === '/') {
        return normalizeCategory(params.get('category'));
    }

    return tools.find((tool) => tool.href === path)?.category ?? null;
}

export function extractQuery(url: string): string {
    return (parsePageUrl(url).params.get('q') ?? '').trim();
}

export function filterTools(
    tools: Tool[],
    activeCategory: string,
    query: string,
): Tool[] {
    const keyword = query.trim().toLowerCase();

    return tools.filter((tool) => {
        const matchesCategory =
            activeCategory === 'all' || tool.category === activeCategory;
        const matchesKeyword =
            keyword === '' ||
            tool.name.toLowerCase().includes(keyword) ||
            tool.description.toLowerCase().includes(keyword);

        return matchesCategory && matchesKeyword;
    });
}

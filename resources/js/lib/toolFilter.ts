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
 * 現在の URL から、サイドバーで強調するカテゴリを求める。
 * トップは ?category=、ツール画面はそのツールのカテゴリ。どちらでもなければ null。
 */
export function resolveActiveCategory(
    url: string,
    tools: ToolNavItem[],
): string | null {
    const [path, search = ''] = url.split('?');

    if (path === '/') {
        return normalizeCategory(new URLSearchParams(search).get('category'));
    }

    return tools.find((tool) => tool.href === path)?.category ?? null;
}

export function extractQuery(url: string): string {
    const search = url.split('?')[1] ?? '';

    return new URLSearchParams(search).get('q') ?? '';
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

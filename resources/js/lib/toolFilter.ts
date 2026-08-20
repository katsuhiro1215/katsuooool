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
];

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

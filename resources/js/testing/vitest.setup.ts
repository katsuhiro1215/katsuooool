import { config } from '@vue/test-utils';

// Vue's SFC template compiler resolves free identifiers (e.g. `route()`) via
// the component's render context, which falls back to
// `app.config.globalProperties` — it does not fall back to plain
// `globalThis`/`window` lookups. In the real app this global property is
// registered at runtime by Ziggy's Vue plugin (`ZiggyVue`, see
// `resources/js/app.ts`). Tests that need `route()` stub it per-test with
// `vi.stubGlobal('route', ...)`; this bridge makes that stub visible to
// every mounted component by resolving `route` dynamically from
// `globalThis` whenever the template calls it.
Object.assign(config.global.config as Record<string, unknown>, {
    globalProperties: {
        route: (...args: unknown[]) => {
            const globalRoute = (globalThis as { route?: unknown }).route;

            if (typeof globalRoute !== 'function') {
                throw new Error(
                    'route() was called during a test but is not stubbed. Call vi.stubGlobal("route", ...) in a beforeEach.',
                );
            }

            return globalRoute(...args);
        },
    },
});

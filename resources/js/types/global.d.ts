import type { AxiosInstance } from 'axios';
import type { route as ziggyRoute } from 'ziggy-js';
import type { PageProps as AppPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    var route: typeof ziggyRoute;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: AppPageProps;
    }
}

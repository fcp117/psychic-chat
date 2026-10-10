import '../css/app.css';
import './bootstrap';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import SiteLayout from './Layouts/SiteLayout.vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const configuredName = import.meta.env.VITE_APP_NAME;
const appName = configuredName && configuredName !== 'Laravel' ? configuredName : 'Intuition Island';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        // One persistent layout keeps audio and its mute state alive during navigation.
        page.default.layout = SiteLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = () => window.dispatchEvent(new Event('psychic:ui-error'));
        return app
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: 'var(--color-primary)',
    },
});

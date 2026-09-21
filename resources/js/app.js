import '../css/app.css';
import './bootstrap';
import './echo';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import RecoveryNotice from './Components/RecoveryNotice.vue';
import SiteAssistant from './Components/SiteAssistant.vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const configuredName = import.meta.env.VITE_APP_NAME;
const appName = configuredName && configuredName !== 'Laravel' ? configuredName : 'Psychic Chat';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        page.default.layout = (h, page) => h('div', [page, h(SiteAssistant), h(RecoveryNotice)]);
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

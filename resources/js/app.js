import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/inertia-vue3'
import { HeadlessProvider } from '@headlessui/vue'
import '../css/app.css'

createInertiaApp({
  resolve: name => import(`./Pages/${name}.vue`),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .component('HeadlessProvider', HeadlessProvider)
      .mount(el)
  },
})

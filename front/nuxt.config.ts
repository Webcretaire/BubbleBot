// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
    app: {baseURL: '/BubbleBot/'},
    css: ['~/assets/css/main.css'],
    postcss: {
        plugins: {
            tailwindcss: {},
            autoprefixer: {}
        }
    },
    runtimeConfig: {
        public: {
            BUBBLEBOT_API_PATH: process.env.BUBBLEBOT_API_PATH
        }
    },
    ssr: false
});

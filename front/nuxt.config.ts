// https://nuxt.com/docs/api/configuration/nuxt-config

export default defineNuxtConfig({
    app: {baseURL: '/BubbleBot/'},
    css: ['~/assets/css/main.css'],
    postcss: {
        plugins: {
            'postcss-import': {},
            'tailwindcss/nesting': 'postcss-nesting',
            tailwindcss: {},
            autoprefixer: {},
        }
    },
    runtimeConfig: {
        public: {
            BUBBLEBOT_API_PATH: process.env.BUBBLEBOT_API_PATH,
            WEBSOCKET_BASE_URL: process.env.WEBSOCKET_BASE_URL,
            COLOR_REWARD_ID: process.env.COLOR_REWARD_ID,
            MEROSS_BASE_URL: process.env.MEROSS_BASE_URL
        }
    },
    ssr: false
});

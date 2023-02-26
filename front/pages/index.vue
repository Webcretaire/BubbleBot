<template>
    <main class="container mx-auto ">
        <section class="b-card text-center">
            <h1 class="text-6xl font-extralight m-6 mt-5">BubbleBot</h1>
            <p class="mb-5">
                Custom Twitch and Discord bot for
                <a class="font-weight-normal" href="https://www.twitch.tv/webcretaire" target="_blank">
                    <span class="username font-bold hover:contrast-150">
                        <span class="username-dark username-gradient webcretaire-color">Webcretaire</span>
                    </span>
                </a>
                on Twitch and
                <a href="https://webcretaire.github.io/LiveSplitAnalyzer/" target="_blank"
                   class="font-bold text-cyan-900 hover:text-cyan-700">
                    LiveSplitAnalyzer
                </a>
            </p>
        </section>
        <command-list/>
        <quote-list/>
    </main>
</template>

<style scoped>
.webcretaire-color {
    color: #09B876;
    background-image: linear-gradient(90deg, #09B876, #8AC951);
}

.username-gradient {
    background-clip: text;
    background-size: 100%;
    background-repeat: no-repeat;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none !important;
}
</style>
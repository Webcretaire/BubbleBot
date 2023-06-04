<script setup lang="ts">
const config = useRuntimeConfig();
const route  = useRoute();

interface User {
    id: number,
    user: string,
    position: string
}

const greetUsers = ref<User[]>([]);

const conn = new WebSocket(`${config.public.WEBSOCKET_BASE_URL}/events`);

let currentId = 0;

const randomPosition = () => `${(0.25 + (Math.random() / 2)) * 100}vw`;

conn.onmessage = function (e) {
    const d = JSON.parse(e.data);

    if (d.action === 'greet') {
        ++currentId;
        ((curId) => {
            greetUsers.value.push({id: currentId, position: randomPosition(), user: d.data.user});
            setTimeout(() => greetUsers.value = greetUsers.value.filter(({id}) => id !== curId), 7000);
        })(currentId);
    } else if (d.action === 'redeem' && d.data.reward === config.public.COLOR_REWARD_ID) {
        const color = d.data.message.startsWith('#') ? d.data.message.substr(1) : d.data.message;
        if (/^[0-9A-F]{6}$/i.test(color)) {
            fetch(`${config.public.MEROSS_BASE_URL}/set_devices_color`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({devices: ['Lampe de salon', 'Chevet salon'], color: color})
            });
        } else {
            console.error(`Incorrect hex value redeemed : ${color}`);
        }
    }
};
conn.onopen    = (e: Event) => conn.send(JSON.stringify({action: 'auth', key: route.query.key}));
</script>

<template>
    <main>
        <div v-for="user in greetUsers" :key="user.id" class="floating-bubble" :style="{left:user.position}">
            <img src="~/assets/images/bubble.png" alt="Bubble" />
            <h1 class="text-white text-center">Hello</h1>
            <h2 class="text-white text-center">{{ user.user }}</h2>
        </div>
    </main>
</template>

<style scoped>
main {
    width: 100vw;
    height: 100vh;
    background-color: transparent;
}

.floating-bubble {
    position: absolute;
    top: 0;
    transform: translate(-50%, 0);
    animation-duration: 5s;
    width: 30rem;
    animation: wiggle 4s ease-in-out infinite alternate, slideIn 7s;
}

@keyframes wiggle {
    0% {
        transform: translate(calc(-50% + 3rem), 0);
    }
    50% {
        transform: translate(calc(-50% - 3rem), 0);
    }
    100% {
        transform: translate(calc(-50% + 3rem), 0);
    }
}

@keyframes slideIn {
    0% {
        width: 0;
        top: 70vh;
    }
    75% {
        width: 30rem;
        top: 0;
    }
    100% {
        top: -30rem;
    }
}

@keyframes growTextH1 {
    0% {
        margin-top: 0;
        font-size: 0;
    }
    40% {
        margin-top: -8rem;
        font-size: 0;
    }
    100% {
        margin-top: -24rem;
        font-size: 5rem;
    }
}

@keyframes growTextH2 {
    0% {
        font-size: 0;
    }
    40% {
        font-size: 0;
    }
    100% {
        font-size: 4.5rem;
    }
}

.floating-bubble h1 {
    margin-top: -24rem;
    font-size: 5rem;
    animation-duration: 4s;
    animation-name: growTextH1;
}

.floating-bubble h2 {
    font-size: 4.5rem;
    animation-duration: 4s;
    animation-name: growTextH2;
}

.floating-bubble h1, .floating-bubble h2 {
    filter: drop-shadow(0 0 0.5rem black);
}

.floating-bubble img {
    opacity: 70%;
    margin-left: auto;
    margin-right: auto;
    filter: drop-shadow(0 0 1.5rem rgba(0, 0, 0, 0.75));
}
</style>
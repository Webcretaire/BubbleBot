<script setup lang="ts">
import {apiUrl} from '~/composables/api';

const quotes = await (await fetch(apiUrl(`/quotes`))).json();
</script>

<template>
    <section class="b-card">
        <h1 class="text-center text-2xl font-bold">Twitch chat quote list</h1>
        <div class="text-center w-full m-6">
            <hr class="w-8/12 m-auto"/>
        </div>
        <div class="flex w-full justify-center">
            <table class="border-collapse table-auto rounded border shadow-lg">
                <thead class="border-b border-slate-200">
                <tr>
                    <th class="p-3 pt-2 pb-2">#</th>
                    <th class="p-3 pt-2 pb-2">Quote</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(value, key) in quotes">
                    <td class="p-3 pt-2 pb-2">{{ key }}</td>
                    <td class="p-3 pl-10 pt-2 pb-2 text-gray-600" v-html="value"></td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
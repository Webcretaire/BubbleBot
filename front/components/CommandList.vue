<script setup lang="ts">
import {apiUrl}       from '~/composables/api';
import {initTooltips} from 'flowbite';

const commands = {
    // Hardcoded commands
    'addcmd': '<em>Adds custom command with name ${1}, value is the rest of the original message',
    'delcmd': '<em>Removes custom command with name ${1}</em>',
    'quote': '<em>Displays quote n°${1} or a random one</em>',
    'quote add': '<em>Adds quote ${0}</em>',
    'quote delete': '<em>Removes quote n°${1}</em>',
    // Fetch custom command list from the API
    ...(await (await fetch(apiUrl(`/commands`))).json())
};

const tooltipContentCommand = (match: string) => {
    if (match == 'user') return 'Username of the person sending the command';
    if (match == '0') return 'Full text following the command name in the original message';
    if (/\d+/.test(match.trim())) return `Word n°${match} after the command name in the original message`;

    return 'Unknown variable';
};

const commandsHtml = computed(() => {
    const out: Record<string, string> = {};

    Object.keys(commands).map(
        key => out[`!${key}`] = commands[key].replaceAll(
            /\$\{([\d\s+a-zA-Z]+)\}/g,
            (raw: string, match: string) => `<strong data-tooltip-target="tooltip-${key + '__' + match}" class="hover:underline cursor-help">${raw}</strong>
                <div id="tooltip-${key + '__' + match}"
                     role="tooltip"
                     class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 rounded-lg shadow-sm opacity-0 tooltip dark:bg-gray-700">
                    ${tooltipContentCommand(match)}
                    <div class="tooltip-arrow"></div>
                </div>`)
    );

    nextTick(() => initTooltips());

    return out;
});
</script>

<template>
    <section class="b-card">
        <h1 class="text-center text-2xl font-bold">Twitch chat command list</h1>
        <div class="text-center w-full m-6">
            <hr class="w-8/12 m-auto"/>
        </div>
        <div class="flex w-full justify-center">
            <table class="border-collapse table-auto rounded border shadow-lg">
                <thead class="border-b border-slate-200">
                <tr>
                    <th class="p-3 pt-2 pb-2">Command</th>
                    <th class="p-3 pt-2 pb-2">Text or <em>Effect</em></th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(value, key) in commandsHtml">
                    <td class="p-3 pt-2 pb-2">{{ key }}</td>
                    <td class="p-3 pl-10 pt-2 pb-2 text-gray-600" v-html="value"></td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
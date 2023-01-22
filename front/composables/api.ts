export const apiUrl = (localPath: string) => {
    const config = useRuntimeConfig();

    return `${config.public.BUBBLEBOT_API_PATH}${localPath}`;
};
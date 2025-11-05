/** @type {import('tailwindcss').Config} */
export default {
    content: ['./index.html', './src/**/*.{js,jsx,ts,tsx}'],
    theme: {
    extend: {
    colors: {
    whatsappGreen: '#075E54',
    chatBg: '#ECE5DD',
    sentBg: '#DCF8C6',
    receivedBg: '#FFFFFF',
    },
    },
    },
    plugins: [],
    }
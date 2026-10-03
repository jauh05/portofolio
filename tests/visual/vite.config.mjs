import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
// Dev-only fixture. Not a production entry and never calls Office APIs.
export default defineConfig({plugins:[react()],cacheDir:'node_modules/.vite-office-qa',server:{host:'127.0.0.1',port:5182,strictPort:true}});

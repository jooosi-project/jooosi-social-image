import { fileURLToPath, URL } from "node:url";

import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { wordpress } from "@nabasa/vp-wp";
import { defineConfig } from "vite-plus";
import Icons from "unplugin-icons/vite";
import svgr from "vite-plugin-svgr";

export default defineConfig({
  plugins: [
    react(),
    svgr({
      svgrOptions: {
        dimensions: false,
      },
    }),
    tailwindcss(),
    wordpress({
      entry: {
        app: "resources/App.tsx",
      },
      outDir: "assets/dist",
      sourcemap: false,
    }),
    Icons({
      compiler: "jsx",
      jsx: "react",
      autoInstall: true,
    }),
  ],
  resolve: {
    alias: {
      "~": fileURLToPath(new URL(".", import.meta.url)),
      "@": fileURLToPath(new URL("./resources", import.meta.url)),
    },
  },
});

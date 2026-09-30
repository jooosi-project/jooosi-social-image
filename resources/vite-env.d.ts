/// <reference types="vite/client" />
/// <reference types="vite-plugin-svgr/client" />
/// <reference types="unplugin-icons/types/react" />

import type { DetailedHTMLProps, HTMLAttributes } from "react";

declare module "*.css";

declare module "react" {
  namespace JSX {
    interface IntrinsicElements {
      "jooosi-icon": DetailedHTMLProps<HTMLAttributes<HTMLElement>, HTMLElement> & {
        name: string;
        width?: string | number;
        height?: string | number;
        color?: string;
      };
    }
  }
}

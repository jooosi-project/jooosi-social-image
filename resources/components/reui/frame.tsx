import type { ComponentProps } from "react";

import { cn } from "@/lib/utils";

function Frame({ className, ...props }: ComponentProps<"section">) {
  return (
    <section
      className={cn("relative flex flex-col gap-[3px] rounded-xl border bg-muted/50 p-[3px]", className)}
      data-slot="frame"
      {...props}
    />
  );
}

function FrameHeader({ className, ...props }: ComponentProps<"header">) {
  return (
    <header
      className={cn("flex flex-col gap-0 px-3 py-1.5", className)}
      data-slot="frame-panel-header"
      {...props}
    />
  );
}

function FramePanel({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn(
        "relative grow overflow-hidden rounded-[calc(var(--radius-xl)-4px)] border bg-card shadow-xs",
        "before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-xl)-5px)] before:shadow-black/5",
        "dark:before:shadow-white/5",
        className,
      )}
      data-slot="frame-panel"
      {...props}
    />
  );
}

function FrameFooter({ className, ...props }: ComponentProps<"footer">) {
  return (
    <footer
      className={cn("flex flex-col gap-1 px-3 py-1.5", className)}
      data-slot="frame-panel-footer"
      {...props}
    />
  );
}

export { Frame, FrameFooter, FrameHeader, FramePanel };

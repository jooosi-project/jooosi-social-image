import * as React from "react";

import { cn } from "@/lib/utils";

type SwitchProps = Omit<React.ComponentProps<"input">, "type"> & {
  label: string;
  containerClassName?: string;
};

function Switch({ label, className, containerClassName, ...props }: SwitchProps) {
  return (
    <label data-slot="switch-field" className={cn("flex cursor-pointer items-center gap-1.5 text-[10px] font-medium text-foreground", containerClassName)}>
      <input data-slot="switch-input" className={cn("peer sr-only", className)} type="checkbox" role="switch" {...props} />
      <span
        data-slot="switch-control"
        className="relative h-4 w-7 shrink-0 rounded-full border border-input bg-muted shadow-xs transition-colors after:absolute after:left-0.5 after:top-1/2 after:size-3 after:-translate-y-1/2 after:rounded-full after:bg-muted-foreground after:transition-transform peer-checked:border-primary peer-checked:bg-primary peer-checked:after:translate-x-3 peer-checked:after:bg-primary-foreground peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 peer-disabled:opacity-50"
        aria-hidden="true"
      />
      <span>{label}</span>
    </label>
  );
}

export { Switch };

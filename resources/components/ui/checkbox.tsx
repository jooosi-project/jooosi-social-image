import * as React from "react";

import IconCheck from "~icons/lucide/check";

import { cn } from "@/lib/utils";

type CheckboxProps = Omit<React.ComponentProps<"input">, "type"> & {
  label: string;
  containerClassName?: string;
};

function Checkbox({ label, className, containerClassName, ...props }: CheckboxProps) {
  return (
    <label data-slot="checkbox-field" className={cn("flex cursor-pointer items-center gap-2 text-[11px] leading-4 text-foreground", containerClassName)}>
      <input data-slot="checkbox-input" className={cn("peer sr-only", className)} type="checkbox" {...props} />
      <span
        data-slot="checkbox-control"
        className="grid size-4 shrink-0 place-items-center rounded-[4px] border border-input bg-background text-primary-foreground shadow-xs transition-colors peer-checked:border-primary peer-checked:bg-primary peer-focus-visible:ring-3 peer-focus-visible:ring-ring/50 peer-disabled:opacity-50 [&_svg]:size-3 [&_svg]:opacity-0 peer-checked:[&_svg]:opacity-100"
        aria-hidden="true"
      >
        <IconCheck />
      </span>
      <span>{label}</span>
    </label>
  );
}

export { Checkbox };

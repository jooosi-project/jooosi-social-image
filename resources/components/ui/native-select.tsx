import * as React from "react";

import IconChevronDown from "~icons/lucide/chevron-down";

import { cn } from "@/lib/utils";

type NativeSelectProps = React.ComponentProps<"select"> & {
  containerClassName?: string;
};

function NativeSelect({ className, containerClassName, children, ...props }: NativeSelectProps) {
  return (
    <span className={cn("relative block min-w-0", containerClassName)}>
      <select
        data-slot="native-select"
        className={cn("h-8 w-full min-w-0 max-w-none appearance-none rounded-md border border-input bg-background bg-none px-2 pr-7 text-xs leading-4 text-foreground shadow-none outline-none transition-[color,box-shadow] focus:border-ring focus:ring-3 focus:ring-ring/50 disabled:pointer-events-none disabled:opacity-50", className)}
        {...props}
      >
        {children}
      </select>
      <IconChevronDown className="pointer-events-none absolute right-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
    </span>
  );
}

export { NativeSelect };

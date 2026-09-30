import { useEffect, useState } from "react";

import IconLoader from "~icons/lucide/loader-circle";
import IconSearch from "~icons/lucide/search";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import type { IconSearchResult } from "@/types/admin";

type IconPickerDialogProps = {
  open: boolean;
  currentIcon: string;
  onOpenChange: (open: boolean) => void;
  onSearch: (query: string) => Promise<IconSearchResult[]>;
  onSelect: (icon: string) => void;
};

export function IconPickerDialog({ open, currentIcon, onOpenChange, onSearch, onSelect }: IconPickerDialogProps) {
  const [query, setQuery] = useState("star");
  const [selected, setSelected] = useState(currentIcon);
  const [results, setResults] = useState<IconSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (!open) return;
    setSelected(currentIcon);
    const iconName = currentIcon.split(":", 2)[1];
    setQuery(iconName || "star");
  }, [currentIcon, open]);

  useEffect(() => {
    const search = query.trim();

    if (!open || search === "") {
      setResults([]);
      return;
    }

    let active = true;
    const timeout = window.setTimeout(() => {
      setLoading(true);
      setError("");
      void onSearch(search)
        .then((items) => { if (active) setResults(items); })
        .catch((reason: unknown) => {
          if (active) setError(reason instanceof Error ? reason.message : "Icons could not be loaded.");
        })
        .finally(() => { if (active) setLoading(false); });
    }, 300);

    return () => {
      active = false;
      window.clearTimeout(timeout);
    };
  }, [onSearch, open, query]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[min(82vh,44rem)] max-w-[calc(100vw-1.5rem)] flex-col gap-4 overflow-hidden sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Choose a Jooosi Icon</DialogTitle>
          <DialogDescription>Search local, bundled, and Iconify SVG icons supplied by Jooosi Icon.</DialogDescription>
        </DialogHeader>

        <label className="relative block">
          <IconSearch className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input className="pl-9" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search icons, for example: heart or mdi:home" autoFocus />
        </label>

        <div className="min-h-0 flex-1 overflow-hidden rounded-lg border bg-muted/20">
          {loading && (
            <div className="grid h-full place-items-center text-sm text-muted-foreground">
              <span className="flex items-center gap-2"><IconLoader className="size-4 animate-spin" /> Searching Jooosi Icon…</span>
            </div>
          )}
          {!loading && error && <div className="grid h-full place-items-center px-6 text-center text-sm text-destructive">{error}</div>}
          {!loading && !error && results.length === 0 && <div className="grid h-full place-items-center px-6 text-center text-sm text-muted-foreground">No matching icons.</div>}
          {!loading && !error && results.length > 0 && (
            <ScrollArea className="h-full">
              <div className="grid grid-cols-3 gap-2 p-3 sm:grid-cols-5 md:grid-cols-6">
                {results.map((icon) => (
                  <button
                    key={icon.name}
                    type="button"
                    className={cn(
                      "flex min-h-24 min-w-0 flex-col items-center justify-center gap-2 rounded-md border bg-background p-2 text-center text-foreground transition-colors hover:border-foreground/25 hover:bg-muted",
                      selected === icon.name && "border-blue-500 bg-blue-50 ring-2 ring-blue-500/15",
                    )}
                    title={icon.name}
                    aria-pressed={selected === icon.name}
                    onClick={() => setSelected(icon.name)}
                  >
                    <jooosi-icon name={icon.name} width="30" height="30" color="currentColor" aria-hidden="true" />
                    <span className="w-full truncate text-[10px] font-medium">{icon.iconName}</span>
                    <span className="w-full truncate text-[9px] text-muted-foreground">{icon.prefix}</span>
                  </button>
                ))}
              </div>
            </ScrollArea>
          )}
        </div>

        <DialogFooter className="items-center sm:justify-between">
          <span className="mr-auto max-w-sm truncate text-xs text-muted-foreground">{selected || "No icon selected"}</span>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button disabled={!selected} onClick={() => { onSelect(selected); onOpenChange(false); }}>Use icon</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";

import IconLink from "~icons/lucide/link-2";
import IconSearch from "~icons/lucide/search";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";
import type { PlaceholderDefinition } from "@/types/admin";

export type PlaceholderPickerHandle = {
  close: () => void;
  handleKeyDown: (event: KeyboardEvent<HTMLElement>) => boolean;
  openAutocomplete: (query: string) => void;
};

type PlaceholderPickerProps = {
  label?: string;
  onSelect: (token: string, source: "autocomplete" | "manual") => void;
  placeholders: PlaceholderDefinition[];
};

export type PlaceholderAutocompleteRange = {
  end: number;
  query: string;
  start: number;
};

export function placeholderAutocompleteRange(value: string, caret: number): PlaceholderAutocompleteRange | null {
  const prefix = value.slice(0, caret);
  const start = prefix.lastIndexOf("{{");

  if (start < 0) return null;

  const query = prefix.slice(start + 2);
  if (query.includes("}}") || !/^[a-zA-Z0-9_.-]*$/.test(query)) return null;

  return { start, end: caret, query };
}

function filterPlaceholders(placeholders: PlaceholderDefinition[], query: string): PlaceholderDefinition[] {
  const terms = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
  if (terms.length === 0) return placeholders;

  return placeholders.filter((placeholder) => {
    const haystack = [placeholder.key, placeholder.label, placeholder.group, placeholder.description, placeholder.type]
      .join(" ")
      .toLowerCase();

    return terms.every((term) => haystack.includes(term));
  });
}

export const PlaceholderPicker = forwardRef<PlaceholderPickerHandle, PlaceholderPickerProps>(function PlaceholderPicker({
  label = "Insert dynamic data",
  onSelect,
  placeholders,
}, ref) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [autocomplete, setAutocomplete] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);
  const searchRef = useRef<HTMLInputElement>(null);
  const optionRefs = useRef<Array<HTMLButtonElement | null>>([]);
  const filtered = useMemo(() => filterPlaceholders(placeholders, query), [placeholders, query]);

  useEffect(() => setActiveIndex(0), [query]);

  useEffect(() => {
    if (!open) return;
    optionRefs.current[activeIndex]?.scrollIntoView({ block: "nearest" });
  }, [activeIndex, filtered, open]);

  const choose = (placeholder: PlaceholderDefinition | undefined) => {
    if (!placeholder) return;
    onSelect(`{{${placeholder.key}}}`, autocomplete ? "autocomplete" : "manual");
    setOpen(false);
    setAutocomplete(false);
  };

  const navigate = (event: KeyboardEvent<HTMLElement>): boolean => {
    if (!open) return false;

    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      event.preventDefault();
      const direction = event.key === "ArrowDown" ? 1 : -1;
      setActiveIndex((index) => filtered.length === 0 ? 0 : (index + direction + filtered.length) % filtered.length);
      return true;
    }

    if ((event.key === "Enter" || event.key === "Tab") && filtered[activeIndex]) {
      event.preventDefault();
      choose(filtered[activeIndex]);
      return true;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      setOpen(false);
      setAutocomplete(false);
      return true;
    }

    return false;
  };

  useImperativeHandle(ref, () => ({
    close: () => {
      setOpen(false);
      setAutocomplete(false);
    },
    handleKeyDown: (event) => autocomplete && navigate(event),
    openAutocomplete: (nextQuery) => {
      setAutocomplete(true);
      setQuery(nextQuery);
      setActiveIndex(0);
      setOpen(true);
    },
  }));

  return (
    <Popover
      open={open}
      onOpenChange={(nextOpen) => {
        setOpen(nextOpen);
        if (nextOpen) {
          setAutocomplete(false);
          setQuery("");
          setActiveIndex(0);
        }
      }}
    >
      <PopoverTrigger render={<Button size="icon-xs" variant="ghost" aria-label={label} title={label} />}>
        <IconLink />
      </PopoverTrigger>
      <PopoverContent
        align="end"
        className="w-80 p-0"
        initialFocus={autocomplete ? false : searchRef}
        finalFocus={false}
      >
        <div className="border-b p-2">
          <div className="relative">
            <IconSearch className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              ref={searchRef}
              aria-label="Search dynamic placeholders"
              className="h-8 pl-8 text-xs"
              value={query}
              placeholder="Search placeholders…"
              autoComplete="off"
              onChange={(event) => setQuery(event.target.value)}
              onKeyDown={navigate}
            />
          </div>
        </div>
        <div className="max-h-72 overflow-y-auto p-1" role="listbox" aria-label="Dynamic placeholders">
          {filtered.map((placeholder, index) => {
            const firstInGroup = index === 0 || filtered[index - 1].group !== placeholder.group;

            return (
              <div key={placeholder.key}>
                {firstInGroup && <p className={cn("px-2 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground", index === 0 && "pt-1")}>{placeholder.group}</p>}
                <button
                  ref={(node) => { optionRefs.current[index] = node; }}
                  type="button"
                  role="option"
                  aria-selected={activeIndex === index}
                  className={cn(
                    "flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left outline-none hover:bg-accent hover:text-accent-foreground",
                    activeIndex === index && "bg-accent text-accent-foreground",
                  )}
                  onMouseEnter={() => setActiveIndex(index)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => choose(placeholder)}
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-xs font-medium">{placeholder.label}</span>
                    <code className="block truncate text-[10px] text-muted-foreground">{`{{${placeholder.key}}}`}</code>
                  </span>
                  <span className="mt-0.5 text-[9px] uppercase text-muted-foreground">{placeholder.type}</span>
                </button>
              </div>
            );
          })}
          {filtered.length === 0 && (
            <div className="px-3 py-6 text-center text-xs text-muted-foreground">
              {placeholders.length === 0 ? "No placeholders are registered." : "No placeholders match your search."}
            </div>
          )}
        </div>
        <p className="border-t px-3 py-2 text-[10px] text-muted-foreground">Type <code>{"{{"}</code> in a supported field to open autocomplete.</p>
      </PopoverContent>
    </Popover>
  );
});

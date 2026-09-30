import { useEffect, useMemo, useState } from "react";

import IconFilePlus from "~icons/lucide/file-plus-2";
import IconSettings from "~icons/lucide/settings-2";

import { DesignPreview } from "@/components/social-image/studio/design-preview";
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
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import type { DesignPreset, PresetRepositorySummary } from "@/types/admin";

type TemplatesDialogProps = {
  open: boolean;
  hasDesign: boolean;
  canManage: boolean;
  busy: boolean;
  loadState: "loading" | "ready" | "error";
  presets: DesignPreset[];
  repositories: PresetRepositorySummary[];
  onOpenChange: (open: boolean) => void;
  onUse: (preset: DesignPreset) => void;
  onBlank: () => void;
  onManageRepositories: () => void;
};

export function TemplatesDialog({ open, hasDesign, canManage, busy, loadState, presets, repositories, onOpenChange, onUse, onBlank, onManageRepositories }: TemplatesDialogProps) {
  const categories = useMemo(() => ["All", ...new Set(presets.map((preset) => preset.category))], [presets]);
  const [category, setCategory] = useState("All");
  const [repositoryId, setRepositoryId] = useState("all");
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState(presets[0]?.key || "");

  const visiblePresets = useMemo(() => {
    const query = search.trim().toLowerCase();
    return presets.filter((preset) => {
      if (repositoryId !== "all" && preset.source.repositoryId !== repositoryId) return false;
      if (category !== "All" && preset.category !== category) return false;
      if (!query) return true;
      return `${preset.title} ${preset.description} ${preset.category} ${preset.tags?.join(" ") || ""} ${preset.source.repositoryTitle}`.toLowerCase().includes(query);
    });
  }, [category, presets, repositoryId, search]);

  const selected = visiblePresets.find((preset) => preset.key === selectedId) || visiblePresets[0] || null;

  useEffect(() => {
    if (open && selected) setSelectedId(selected.key);
  }, [open, selected]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[min(86vh,48rem)] max-w-[calc(100vw-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl lg:max-w-[64rem]">
        <DialogHeader className="shrink-0 gap-1 px-5 pb-3 pt-4 text-left">
          <DialogTitle>Templates</DialogTitle>
          <DialogDescription>{hasDesign ? "Choose a preset to replace the current layout." : "Choose a preset to start a new design."}</DialogDescription>
        </DialogHeader>

        <Separator className="shrink-0" />

        <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
          <aside className="flex max-h-48 shrink-0 flex-col overflow-hidden border-b lg:max-h-none lg:w-64 lg:border-b-0 lg:border-r">
            <ScrollArea className="min-h-0 flex-1">
              <div className="px-3 pb-2 pt-4">
                <div className="flex items-center justify-between px-2 pb-2">
                  <p className="text-xs font-medium text-muted-foreground">Repositories</p>
                  <button type="button" className="text-[10px] font-medium text-muted-foreground hover:text-foreground" onClick={onManageRepositories}>Manage</button>
                </div>
                <div className="space-y-1">
                  <button
                    type="button"
                    className={cn("flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-xs font-medium transition-colors", repositoryId === "all" ? "bg-muted text-foreground" : "text-muted-foreground hover:bg-muted/70 hover:text-foreground")}
                    onClick={() => setRepositoryId("all")}
                  >
                    All repositories<span className="text-[10px] tabular-nums opacity-65">{presets.length}</span>
                  </button>
                  {repositories.filter((repository) => repository.enabled).map((repository) => (
                    <button
                      key={repository.id}
                      type="button"
                      className={cn("flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-xs font-medium transition-colors", repositoryId === repository.id ? "bg-muted text-foreground" : "text-muted-foreground hover:bg-muted/70 hover:text-foreground")}
                      onClick={() => setRepositoryId(repository.id)}
                    >
                      <span className="truncate">{repository.title}</span><span className="text-[10px] tabular-nums opacity-65">{repository.presetCount}</span>
                    </button>
                  ))}
                </div>

                <p className="px-2 pb-2 pt-5 text-xs font-medium text-muted-foreground">Collections</p>
                <div className="space-y-1">
                {categories.map((item) => {
                  const sourcePresets = repositoryId === "all" ? presets : presets.filter((preset) => preset.source.repositoryId === repositoryId);
                  const count = item === "All" ? sourcePresets.length : sourcePresets.filter((preset) => preset.category === item).length;
                  if (count === 0) return null;
                  return (
                    <button
                      key={item}
                      type="button"
                      className={cn("flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-xs font-medium transition-colors", category === item ? "bg-muted text-foreground" : "text-muted-foreground hover:bg-muted/70 hover:text-foreground")}
                      onClick={() => setCategory(item)}
                    >
                      {item}<span className="text-[10px] tabular-nums opacity-65">{count}</span>
                    </button>
                  );
                })}
                </div>
              </div>
            </ScrollArea>
            <div className="border-t p-3">
              <Button type="button" size="sm" variant="outline" className="w-full" onClick={onManageRepositories}><IconSettings /> Manage repositories</Button>
            </div>
          </aside>

          <div className="flex min-h-0 min-w-0 flex-1 flex-col gap-4">
            <div className="shrink-0 px-5 pt-5">
              <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search templates…" autoComplete="off" />
            </div>
            <ScrollArea className="min-h-0 flex-1">
              <div className="grid grid-cols-1 gap-3 px-5 pb-5 md:grid-cols-2">
                {visiblePresets.length === 0 && (
                  <div className="grid min-h-64 place-items-center text-center md:col-span-2">
                    <div>
                      <p className="text-sm font-semibold">{loadState === "loading" ? "Loading templates…" : loadState === "error" ? "Templates could not be loaded" : "No templates match"}</p>
                      {loadState === "ready" && <p className="mt-1 text-xs text-muted-foreground">Try another search or collection.</p>}
                      {loadState === "error" && <p className="mt-1 text-xs text-muted-foreground">Reload the page to try again.</p>}
                    </div>
                  </div>
                )}
                {visiblePresets.map((preset) => (
                  <button
                    key={preset.key}
                    type="button"
                    className={cn("group overflow-hidden rounded-xl border bg-card text-left transition-all hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-sm", selected?.id === preset.id && "border-foreground/30 ring-2 ring-foreground/10")}
                    onClick={() => setSelectedId(preset.key)}
                  >
                    <DesignPreview document={preset.document} className="w-full border-b bg-muted" />
                    <span className="block min-w-0 p-3">
                      <span className="flex items-center gap-2"><strong className="truncate text-sm">{preset.title}</strong><span className="rounded bg-muted px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground">{preset.category}</span></span>
                      <span className="mt-1.5 block text-xs leading-relaxed text-muted-foreground line-clamp-2">{preset.description}</span>
                      <span className="mt-2 block truncate text-[9px] font-medium text-muted-foreground/80">{preset.source.repositoryTitle}</span>
                    </span>
                  </button>
                ))}
              </div>
            </ScrollArea>
          </div>
        </div>

        <DialogFooter className="shrink-0 flex-row items-center justify-between border-t px-5 py-4">
          <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">{loadState === "loading" ? "Loading templates…" : `${visiblePresets.length} ready${selected ? ` · ${selected.title}` : ""}`}</span>
          <div className="flex items-center gap-2">
            <Button type="button" variant="outline" onClick={onBlank} disabled={!canManage || busy}><IconFilePlus /> Blank design</Button>
            <Button type="button" onClick={() => selected && onUse(selected)} disabled={!selected || !canManage || busy}>{hasDesign ? "Apply template" : "Use template"}</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

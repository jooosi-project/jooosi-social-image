import { useEffect, useState } from "react";

import IconDatabase from "~icons/lucide/database";
import IconExternalLink from "~icons/lucide/external-link";
import IconLoader from "~icons/lucide/loader-circle";
import IconPower from "~icons/lucide/power";
import IconPlus from "~icons/lucide/plus";
import IconRefresh from "~icons/lucide/refresh-cw";
import IconTrash from "~icons/lucide/trash-2";
import IconTriangleAlert from "~icons/lucide/triangle-alert";

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
import type { PresetCatalog, PresetRepositorySummary } from "@/types/admin";

type PresetRepositoriesDialogProps = {
  open: boolean;
  catalog: PresetCatalog;
  canManage: boolean;
  busy: boolean;
  onOpenChange: (open: boolean) => void;
  onAdd: (url: string) => void;
  onToggle: (repository: PresetRepositorySummary, enabled: boolean) => void;
  onRefresh: (repository: PresetRepositorySummary) => void;
  onRemove: (repository: PresetRepositorySummary) => void;
};

function RepositoryStatus({ repository }: { repository: PresetRepositorySummary }) {
  const label = repository.status === "ready" ? "Ready" : repository.status === "stale" ? "Using cached copy" : "Unavailable";

  return (
    <span className={cn(
      "rounded-full px-2 py-0.5 text-[10px] font-medium",
      repository.status === "ready" ? "bg-emerald-50 text-emerald-700" : "bg-amber-50 text-amber-700",
    )}>
      {label}
    </span>
  );
}

export function PresetRepositoriesDialog({
  open,
  catalog,
  canManage,
  busy,
  onOpenChange,
  onAdd,
  onToggle,
  onRefresh,
  onRemove,
}: PresetRepositoriesDialogProps) {
  const [url, setUrl] = useState("");
  const externalCount = catalog.repositories.filter((repository) => !repository.bundled).length;

  useEffect(() => {
    if (open) setUrl("");
  }, [open, externalCount]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[min(84vh,44rem)] max-w-[calc(100vw-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl">
        <DialogHeader className="shrink-0 gap-1 px-5 pb-4 pt-5 text-left">
          <DialogTitle>Template repositories</DialogTitle>
          <DialogDescription>Add public Egami repository manifests. Remote data is validated and cached before it appears in Templates.</DialogDescription>
        </DialogHeader>

        {canManage && (
          <div className="shrink-0 border-y bg-muted/30 px-5 py-4">
            <label className="mb-1.5 block text-xs font-medium" htmlFor="egami-repository-url">Repository manifest URL</label>
            <div className="flex gap-2">
              <Input
                id="egami-repository-url"
                value={url}
                onChange={(event) => setUrl(event.target.value)}
                placeholder="https://example.com/egami/repository.json"
                inputMode="url"
                autoComplete="url"
                onKeyDown={(event) => {
                  if (event.key === "Enter" && url.trim() && !busy) onAdd(url.trim());
                }}
              />
              <Button type="button" onClick={() => onAdd(url.trim())} disabled={!url.trim() || busy}>
                {busy ? <IconLoader className="animate-spin" /> : <IconPlus />} Add
              </Button>
            </div>
            <p className="mt-2 text-[10px] leading-4 text-muted-foreground">External hosts require HTTPS. Same-host HTTP is accepted for local testing. Authentication headers are intentionally unsupported.</p>
          </div>
        )}

        <ScrollArea className="min-h-0 flex-1">
          <div className="space-y-3 p-5">
            {catalog.repositories.map((repository) => (
              <article key={repository.id} className="rounded-xl border bg-card p-4">
                <div className="flex items-start gap-3">
                  <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground"><IconDatabase className="size-4" /></span>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <h3 className="truncate text-sm font-semibold">{repository.title}</h3>
                      {repository.bundled && <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">Bundled</span>}
                      <RepositoryStatus repository={repository} />
                    </div>
                    <p className="mt-1 text-xs leading-5 text-muted-foreground">{repository.description || repository.url}</p>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-muted-foreground">
                      <span>{repository.presetCount} template{repository.presetCount === 1 ? "" : "s"}</span>
                      {repository.version && <span>Version {repository.version}</span>}
                      {repository.syncedAt && <span>Synced {new Date(repository.syncedAt).toLocaleString()}</span>}
                    </div>
                    {repository.error && <p className="mt-2 flex items-start gap-1.5 text-[10px] leading-4 text-amber-700"><IconTriangleAlert className="mt-0.5 size-3 shrink-0" />{repository.error}</p>}
                    {(repository.url || repository.homepage) && (
                      <a className="mt-2 inline-flex items-center gap-1 text-[10px] font-medium text-foreground underline-offset-4 hover:underline" href={repository.homepage || repository.url} target="_blank" rel="noreferrer">
                        Repository details <IconExternalLink className="size-3" />
                      </a>
                    )}
                  </div>
                  {canManage && (
                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                      {repository.capabilities.toggle && (
                        <Button type="button" size="sm" variant={repository.enabled ? "outline" : "default"} onClick={() => onToggle(repository, !repository.enabled)} disabled={busy}>
                          <IconPower /> {repository.enabled ? "Disable" : "Enable"}
                        </Button>
                      )}
                      {repository.capabilities.refresh && (
                        <Button type="button" size="sm" variant="outline" onClick={() => onRefresh(repository)} disabled={busy}>
                          <IconRefresh /> Refresh
                        </Button>
                      )}
                      {repository.capabilities.delete && (
                        <Button type="button" size="sm" variant="ghost" className="text-destructive hover:text-destructive" onClick={() => onRemove(repository)} disabled={busy}>
                          <IconTrash /> Delete
                        </Button>
                      )}
                    </div>
                  )}
                </div>
              </article>
            ))}
          </div>
        </ScrollArea>

        <DialogFooter className="shrink-0 flex-row items-center justify-between border-t px-5 py-4">
          <span className="text-xs text-muted-foreground">
            {catalog.presets.length} enabled templates from {catalog.repositories.filter((repository) => repository.enabled).length}{" "}
            {catalog.repositories.filter((repository) => repository.enabled).length === 1 ? "repository" : "repositories"}
          </span>
          <div className="flex items-center gap-2">
            <a className="text-xs text-muted-foreground underline-offset-4 hover:underline" href={catalog.schemas.repository} target="_blank" rel="noreferrer">Repository schema</a>
            <Button type="button" onClick={() => onOpenChange(false)}>Done</Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

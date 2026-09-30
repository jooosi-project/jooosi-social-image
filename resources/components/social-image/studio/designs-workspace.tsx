import IconCopy from "~icons/lucide/copy";
import IconEllipsis from "~icons/lucide/ellipsis";
import IconImagePlus from "~icons/lucide/image-plus";
import IconLoader from "~icons/lucide/loader-circle";
import IconPencil from "~icons/lucide/pencil";
import IconPlus from "~icons/lucide/plus";
import IconRefresh from "~icons/lucide/refresh-cw";
import IconTrash from "~icons/lucide/trash-2";

import { DesignPreview } from "@/components/social-image/studio/design-preview";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import type { Design } from "@/types/admin";

type DesignsWorkspaceProps = {
  designs: Design[];
  loading: boolean;
  canManage: boolean;
  busy: boolean;
  onStartNew: () => void;
  onOpen: (design: Design) => void;
  onStatusChange: (design: Design, status: Design["status"]) => void;
  onDuplicate: (design: Design) => void;
  onRegenerate: (design: Design) => void;
  onDelete: (design: Design) => void;
};

export function DesignsWorkspace({ designs, loading, canManage, busy, onStartNew, onOpen, onStatusChange, onDuplicate, onRegenerate, onDelete }: DesignsWorkspaceProps) {
  return (
    <main className="social-image-designs-workspace">
      <div className="mx-auto w-full max-w-[1120px] px-6 py-10">
        <div className="flex items-end justify-between gap-5 border-b pb-4">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-lg font-semibold">Designs</h1>
              <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">{loading ? "…" : designs.length}</span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">Saved image designs and their publishing locations.</p>
          </div>
          {canManage && <Button size="sm" onClick={onStartNew} disabled={busy || loading}><IconPlus /> New design</Button>}
        </div>

        {loading ? (
          <div className="grid min-h-80 place-items-center text-sm text-muted-foreground" role="status">
            <span className="flex items-center gap-2"><IconLoader className="size-4 animate-spin" /> Loading designs…</span>
          </div>
        ) : designs.length === 0 ? (
          <div className="grid min-h-80 place-items-center text-center">
            <div>
              <span className="mx-auto grid size-10 place-items-center rounded-lg border bg-card text-muted-foreground"><IconImagePlus /></span>
              <h2 className="mt-3 text-sm font-semibold">No designs yet</h2>
              <p className="mt-1 text-xs text-muted-foreground">Choose a template to create your first dynamic image.</p>
              {canManage && <Button size="sm" className="mt-4" onClick={onStartNew} disabled={busy}>Browse templates</Button>}
            </div>
          </div>
        ) : (
          <div className="mt-6 grid gap-x-5 gap-y-7 sm:grid-cols-2 lg:grid-cols-3">
            {designs.map((design) => (
              <article key={design.id} className="group min-w-0">
                <button
                  type="button"
                  className="w-full overflow-hidden rounded-lg border text-left shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                  onClick={() => onOpen(design)}
                >
                  <DesignPreview document={design.document} />
                </button>
                <div className="mt-2.5 flex items-start gap-2">
                  <button type="button" className="min-w-0 flex-1 text-left" onClick={() => onOpen(design)}>
                    <span className="block truncate text-xs font-semibold">{design.title}</span>
                    <span className="mt-0.5 block truncate text-[10px] text-muted-foreground">
                      {design.document.width} × {design.document.height} · {design.rules.outputs.includes("og") ? (design.rules.outputs.includes("featured") ? "Social + featured" : "Social") : "Featured"}
                    </span>
                  </button>
                  <Switch
                    aria-label={`Publish ${design.title}`}
                    containerClassName="shrink-0"
                    label={design.status === "publish" ? "Published" : "Draft"}
                    checked={design.status === "publish"}
                    disabled={!canManage || busy}
                    onChange={(event) => onStatusChange(design, event.target.checked ? "publish" : "draft")}
                  />
                  <DropdownMenu>
                    <DropdownMenuTrigger render={<Button size="icon-xs" variant="ghost" aria-label={`Actions for ${design.title}`} />}><IconEllipsis /></DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                      <DropdownMenuItem onClick={() => onOpen(design)}><IconPencil /> Edit</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => onDuplicate(design)} disabled={!canManage || busy}><IconCopy /> Duplicate</DropdownMenuItem>
                      <DropdownMenuItem onClick={() => onRegenerate(design)} disabled={!canManage || busy}><IconRefresh /> Regenerate</DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem variant="destructive" onClick={() => onDelete(design)} disabled={!canManage || busy}><IconTrash /> Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </article>
            ))}
          </div>
        )}
      </div>
    </main>
  );
}

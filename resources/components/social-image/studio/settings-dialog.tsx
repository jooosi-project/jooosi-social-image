import IconCheck from "~icons/lucide/check";
import IconRefresh from "~icons/lucide/refresh-cw";
import IconTrash from "~icons/lucide/trash-2";
import IconTriangleAlert from "~icons/lucide/triangle-alert";

import { Button } from "@/components/ui/button";
import { NativeSelect } from "@/components/ui/native-select";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { PluginSettings, SystemStatus } from "@/types/admin";

type SettingsDialogProps = {
  open: boolean;
  settings: PluginSettings;
  status: SystemStatus;
  statusLoadState: "loading" | "ready" | "error";
  canManage: boolean;
  busy: boolean;
  onOpenChange: (open: boolean) => void;
  onChange: (settings: PluginSettings) => void;
  onSave: () => void;
  onWarm: () => void;
  onFlush: () => void;
};

function ToggleRow({ label, detail, checked, onChange }: { label: string; detail: string; checked: boolean; onChange: (checked: boolean) => void }) {
  return (
    <label className="flex cursor-pointer items-start justify-between gap-5 border-t py-3 first:border-t-0">
      <span><strong className="block text-xs font-medium">{label}</strong><span className="mt-1 block max-w-sm text-[10px] leading-4 text-muted-foreground">{detail}</span></span>
      <input className="mt-1" type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
    </label>
  );
}

function StatusItem({ label, ready }: { label: string; ready: boolean }) {
  return (
    <div className="flex items-center justify-between border-t py-2.5 first:border-t-0">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className={ready ? "text-emerald-700" : "text-amber-700"}>
        <span className="sr-only">{ready ? `${label}: ready` : `${label}: needs attention`}</span>
        {ready ? <IconCheck aria-hidden="true" className="size-3.5" /> : <IconTriangleAlert aria-hidden="true" className="size-3.5" />}
      </span>
    </div>
  );
}

export function SettingsDialog({ open, settings, status, statusLoadState, canManage, busy, onOpenChange, onChange, onSave, onWarm, onFlush }: SettingsDialogProps) {
  const driver = status.renderer?.active_driver || "none";
  const diagnostics = [
    ...(status.renderer.diagnostics || []),
    status.svg.notice || (!status.svg.available ? status.svg.reason : ""),
    !status.webfont.available ? status.webfont.reason : status.webfont.notice,
    status.fonts.notice || (!status.fonts.available ? status.fonts.reason : ""),
    !status.filesystem.ready ? status.filesystem.reason : "",
    status.cron.reason,
  ].filter(Boolean);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Rendering settings</DialogTitle>
          <DialogDescription>Generation is always queued immediately in the background.</DialogDescription>
        </DialogHeader>

        <div className="grid gap-6 sm:grid-cols-[1.15fr_0.85fr]">
          <section>
            <h3 className="mb-2 text-xs font-semibold">Image output</h3>
            <div className="grid grid-cols-2 gap-3 border-b pb-4">
              <label className="space-y-1">
                <span className="text-[10px] font-medium">File format</span>
                <NativeSelect value={settings.format} onChange={(event) => onChange({ ...settings, format: event.target.value as PluginSettings["format"] })}>
                  <option value="png">PNG</option>
                  <option value="jpeg">JPEG</option>
                  <option value="webp">WebP</option>
                </NativeSelect>
              </label>
              <label className="space-y-1">
                <span className="flex justify-between text-[10px] font-medium">Quality <output>{settings.quality}</output></span>
                <input className="mt-2 w-full accent-foreground" type="range" min="1" max="100" value={settings.quality} onChange={(event) => onChange({ ...settings, quality: Number(event.target.value) })} />
              </label>
            </div>
            <ToggleRow label="Allow featured-image replacement" detail="Designs must still opt in individually." checked={settings.replace_featured} onChange={(replace_featured) => onChange({ ...settings, replace_featured })} />
            <ToggleRow label="Delete generated data on uninstall" detail="Remove designs, options, attachments, and generated files." checked={settings.delete_on_uninstall} onChange={(delete_on_uninstall) => onChange({ ...settings, delete_on_uninstall })} />
          </section>

          <section>
            <h3 className="mb-2 text-xs font-semibold">System status</h3>
            {statusLoadState === "loading" ? (
              <p className="rounded-md border px-3 py-2 text-xs text-muted-foreground">Checking system status…</p>
            ) : statusLoadState === "error" ? (
              <p className="rounded-md border px-3 py-2 text-xs text-muted-foreground">System status could not be loaded. Reload the page to retry.</p>
            ) : (
              <>
                <div className="rounded-md border px-3">
                  <StatusItem label={`Imagine (${driver})`} ready={Boolean(status.renderer?.available)} />
                  <StatusItem label="Imagick" ready={Boolean(status.renderer.extensions.imagick)} />
                  <StatusItem label="Jooosi Icon" ready={status.svg.jooosi_icon} />
                  <StatusItem label={`SVG rasterizer (${status.svg.engine})`} ready={status.svg.available} />
                  <StatusItem label={`Jooosi Fon (${status.webfont.renderable_count}/${status.webfont.fonts.length} server-ready)`} ready={status.webfont.available && status.webfont.unrenderable_count === 0} />
                  <StatusItem label="Server fallback fonts" ready={status.fonts.available} />
                  <StatusItem label="Generated-image storage" ready={status.filesystem.ready} />
                  <StatusItem label="Background generation" ready={status.cron.ready} />
                </div>
                {diagnostics.length > 0 && (
                  <ul aria-label="System diagnostics" className="mt-2 list-disc space-y-1 pl-4 text-[10px] leading-4 text-amber-700">
                    {diagnostics.map((message) => <li key={message}>{message}</li>)}
                  </ul>
                )}
              </>
            )}
            {canManage && (
              <div className="mt-3 grid grid-cols-2 gap-2">
                <Button size="sm" variant="outline" onClick={onWarm} disabled={busy}><IconRefresh /> Warm cache</Button>
                <Button size="sm" variant="destructive" onClick={onFlush} disabled={busy}><IconTrash /> Flush files</Button>
              </div>
            )}
          </section>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          {canManage && <Button onClick={onSave} disabled={busy}>Save settings</Button>}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

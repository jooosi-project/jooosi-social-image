import { useEffect, useState, type ReactNode } from "react";

import { BackgroundEditor } from "@/components/social-image/studio/background-editor";
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
import type { DesignDocument } from "@/types/admin";

type CanvasSettingsDialogProps = {
  open: boolean;
  document: DesignDocument;
  onOpenChange: (open: boolean) => void;
  onApply: (document: DesignDocument) => void;
};

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-xs font-medium text-foreground/75">{label}</span>
      {children}
    </label>
  );
}

export function CanvasSettingsDialog({ open, document, onOpenChange, onApply }: CanvasSettingsDialogProps) {
  const [draft, setDraft] = useState<DesignDocument>(() => structuredClone(document));

  useEffect(() => {
    if (open) setDraft(structuredClone(document));
  }, [document, open]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[88vh] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>Canvas settings</DialogTitle>
          <DialogDescription>Set the dimensions and background shared by every generated image from this design.</DialogDescription>
        </DialogHeader>

        <div className="space-y-5">
          <section className="space-y-3">
            <h3 className="text-xs font-semibold">Dimensions</h3>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Width">
                <Input type="number" min={200} max={2400} value={draft.width} onChange={(event) => setDraft((current) => ({ ...current, width: Number(event.target.value) }))} />
              </Field>
              <Field label="Height">
                <Input type="number" min={200} max={2400} value={draft.height} onChange={(event) => setDraft((current) => ({ ...current, height: Number(event.target.value) }))} />
              </Field>
            </div>
          </section>

          <section className="space-y-3 border-t pt-5">
            <h3 className="text-xs font-semibold">Background</h3>
            <BackgroundEditor value={draft.background} onChange={(background) => setDraft((current) => ({ ...current, background }))} />
          </section>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button onClick={() => { onApply(draft); onOpenChange(false); }}>Apply changes</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

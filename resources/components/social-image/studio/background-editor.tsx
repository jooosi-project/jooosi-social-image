import type { ReactNode } from "react";

import IconPlus from "~icons/lucide/plus";
import IconTrash from "~icons/lucide/trash-2";

import { Button } from "@/components/ui/button";
import { ColorPicker } from "@/components/ui/color-picker";
import { Input } from "@/components/ui/input";
import { NativeSelect } from "@/components/ui/native-select";
import { gradientCss, interpolateGradientColor, sortedGradientStops } from "@/lib/gradient";
import type { Background, GradientStop } from "@/types/admin";

type BackgroundEditorProps = {
  value: Background;
  onChange: (background: Background) => void;
  compact?: boolean;
};

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1.5">
      <span className="text-[11px] font-medium text-foreground/75">{label}</span>
      {children}
    </label>
  );
}

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <div className="space-y-1.5">
      <span className="block text-[11px] font-medium text-foreground/75">{label}</span>
      <ColorPicker className="h-8" label={label} value={value} onChange={onChange} />
    </div>
  );
}

function clampPosition(position: number): number {
  return Math.min(100, Math.max(0, Number.isFinite(position) ? position : 0));
}

function createGradientStop(stops: GradientStop[]): GradientStop {
  const sorted = sortedGradientStops(stops);
  let left = sorted[0] || { color: "#111827", position: 0 };
  let right = sorted[1] || { color: "#312e81", position: 100 };
  let largestGap = right.position - left.position;

  for (let index = 1; index < sorted.length; index += 1) {
    const gap = sorted[index].position - sorted[index - 1].position;
    if (gap > largestGap) {
      largestGap = gap;
      left = sorted[index - 1];
      right = sorted[index];
    }
  }

  const position = Math.round(left.position + largestGap / 2);
  return { color: interpolateGradientColor(left, right, position), position };
}

export function BackgroundEditor({ value, onChange, compact = false }: BackgroundEditorProps) {
  const update = (patch: Partial<Background>) => onChange({ ...value, ...patch });
  const updateStops = (stops: GradientStop[]) => update({ stops: sortedGradientStops(stops) });
  const updateStop = (index: number, patch: Partial<GradientStop>) => {
    updateStops(value.stops.map((stop, currentIndex) => currentIndex === index ? { ...stop, ...patch } : stop));
  };

  return (
    <div className={compact ? "space-y-3" : "space-y-4"}>
      <Field label="Background type">
        <NativeSelect value={value.type} onChange={(event) => update({ type: event.target.value as Background["type"] })}>
          <option value="solid">Solid</option>
          <option value="gradient">Gradient</option>
        </NativeSelect>
      </Field>

      {value.type === "solid" ? (
        <ColorField label="Background color" value={value.color} onChange={(color) => update({ color })} />
      ) : (
        <div className="space-y-3">
          <div className="h-12 rounded-lg border shadow-inner" style={{ background: gradientCss(value) }} />
          <Field label="Direction">
            <NativeSelect value={value.direction} onChange={(event) => update({ direction: event.target.value as Background["direction"] })}>
              <option value="horizontal">Horizontal</option>
              <option value="vertical">Vertical</option>
            </NativeSelect>
          </Field>

          <div className="flex items-center justify-between gap-2">
            <span className="text-[11px] font-semibold">Color stops</span>
            <Button
              size="sm"
              variant="outline"
              disabled={value.stops.length >= 12}
              onClick={() => updateStops([...value.stops, createGradientStop(value.stops)])}
            >
              <IconPlus /> Add stop
            </Button>
          </div>

          <div className="space-y-2">
            {value.stops.map((stop, index) => (
              <div key={`${index}-${stop.position}`} className="grid grid-cols-[minmax(0,1fr)_4.75rem_2rem] items-end gap-2 rounded-lg border bg-muted/25 p-2">
                <ColorField label={`Stop ${index + 1}`} value={stop.color} onChange={(color) => updateStop(index, { color })} />
                <Field label="Position">
                  <div className="relative">
                    <Input
                      data-suffix="percent"
                      className="h-8 pr-5 text-xs"
                      type="number"
                      min={0}
                      max={100}
                      step={1}
                      value={stop.position}
                      onChange={(event) => updateStop(index, { position: clampPosition(Number(event.target.value)) })}
                    />
                    <span className="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">%</span>
                  </div>
                </Field>
                <Button
                  size="icon-sm"
                  variant="ghost"
                  disabled={value.stops.length <= 2}
                  aria-label={`Remove stop ${index + 1}`}
                  onClick={() => updateStops(value.stops.filter((_, currentIndex) => currentIndex !== index))}
                >
                  <IconTrash />
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

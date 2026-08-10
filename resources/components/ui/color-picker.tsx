import { useEffect, useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from "react";

import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

type Rgb = { red: number; green: number; blue: number };
type Hsv = { hue: number; saturation: number; value: number };

type ColorPickerProps = {
  value: string;
  label: string;
  className?: string;
  onChange: (value: string) => void;
};

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(maximum, Math.max(minimum, value));
}

function parseColor(color: string): Rgb | null {
  const value = color.trim();
  const short = /^#([0-9a-f]{3})$/i.exec(value);

  if (short) {
    return {
      red: Number.parseInt(short[1][0] + short[1][0], 16),
      green: Number.parseInt(short[1][1] + short[1][1], 16),
      blue: Number.parseInt(short[1][2] + short[1][2], 16),
    };
  }

  const full = /^#([0-9a-f]{6})$/i.exec(value);
  if (full) {
    return {
      red: Number.parseInt(full[1].slice(0, 2), 16),
      green: Number.parseInt(full[1].slice(2, 4), 16),
      blue: Number.parseInt(full[1].slice(4, 6), 16),
    };
  }

  const functional = /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i.exec(value);
  if (!functional) return null;

  return {
    red: clamp(Number(functional[1]), 0, 255),
    green: clamp(Number(functional[2]), 0, 255),
    blue: clamp(Number(functional[3]), 0, 255),
  };
}

function rgbToHex({ red, green, blue }: Rgb): string {
  return `#${[red, green, blue].map((channel) => clamp(Math.round(channel), 0, 255).toString(16).padStart(2, "0")).join("")}`;
}

function rgbToHsv({ red, green, blue }: Rgb): Hsv {
  const r = red / 255;
  const g = green / 255;
  const b = blue / 255;
  const maximum = Math.max(r, g, b);
  const minimum = Math.min(r, g, b);
  const delta = maximum - minimum;
  let hue = 0;

  if (delta > 0) {
    if (maximum === r) hue = 60 * (((g - b) / delta) % 6);
    else if (maximum === g) hue = 60 * ((b - r) / delta + 2);
    else hue = 60 * ((r - g) / delta + 4);
  }

  if (hue < 0) hue += 360;

  return {
    hue,
    saturation: maximum === 0 ? 0 : (delta / maximum) * 100,
    value: maximum * 100,
  };
}

function hsvToRgb({ hue, saturation, value }: Hsv): Rgb {
  const s = clamp(saturation, 0, 100) / 100;
  const v = clamp(value, 0, 100) / 100;
  const chroma = v * s;
  const section = ((hue % 360) + 360) % 360 / 60;
  const x = chroma * (1 - Math.abs((section % 2) - 1));
  const match = v - chroma;
  let channels: [number, number, number];

  if (section < 1) channels = [chroma, x, 0];
  else if (section < 2) channels = [x, chroma, 0];
  else if (section < 3) channels = [0, chroma, x];
  else if (section < 4) channels = [0, x, chroma];
  else if (section < 5) channels = [x, 0, chroma];
  else channels = [chroma, 0, x];

  return {
    red: (channels[0] + match) * 255,
    green: (channels[1] + match) * 255,
    blue: (channels[2] + match) * 255,
  };
}

export function ColorPicker({ value, label, className, onChange }: ColorPickerProps) {
  const fallback = useMemo(() => parseColor(value) || { red: 0, green: 0, blue: 0 }, [value]);
  const hsv = rgbToHsv(fallback);
  const [draft, setDraft] = useState(value);
  const saturationRef = useRef<HTMLDivElement>(null);

  useEffect(() => setDraft(value), [value]);

  const emit = (next: Hsv) => onChange(rgbToHex(hsvToRgb(next)));
  const updateSaturation = (event: PointerEvent<HTMLDivElement>) => {
    const bounds = saturationRef.current?.getBoundingClientRect();
    if (!bounds) return;

    emit({
      hue: hsv.hue,
      saturation: clamp(((event.clientX - bounds.left) / bounds.width) * 100, 0, 100),
      value: clamp(100 - ((event.clientY - bounds.top) / bounds.height) * 100, 0, 100),
    });
  };

  const onSaturationKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const changes: Record<string, [number, number]> = {
      ArrowLeft: [-1, 0],
      ArrowRight: [1, 0],
      ArrowUp: [0, 1],
      ArrowDown: [0, -1],
    };
    const change = changes[event.key];
    if (!change) return;

    event.preventDefault();
    emit({
      hue: hsv.hue,
      saturation: clamp(hsv.saturation + change[0], 0, 100),
      value: clamp(hsv.value + change[1], 0, 100),
    });
  };

  return (
    <div data-slot="color-control" className={cn("flex h-9 items-center gap-2 rounded-md border bg-background px-1.5", className)}>
      <Popover>
        <PopoverTrigger
          className="relative size-6 shrink-0 overflow-hidden rounded border shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={`Open ${label.toLowerCase()} picker`}
          style={{ backgroundColor: rgbToHex(fallback) }}
        >
          <span className="sr-only">Choose color</span>
        </PopoverTrigger>
        <PopoverContent className="w-64 space-y-3" align="start" initialFocus={false}>
          <div
            ref={saturationRef}
            className="relative h-36 touch-none cursor-crosshair overflow-hidden rounded-md outline-none ring-1 ring-black/10 focus-visible:ring-2 focus-visible:ring-ring"
            style={{ backgroundColor: rgbToHex(hsvToRgb({ hue: hsv.hue, saturation: 100, value: 100 })) }}
            role="slider"
            tabIndex={0}
            aria-label={`${label} saturation and brightness`}
            aria-valuetext={`${Math.round(hsv.saturation)}% saturation, ${Math.round(hsv.value)}% brightness`}
            onKeyDown={onSaturationKeyDown}
            onPointerDown={(event) => {
              event.currentTarget.setPointerCapture(event.pointerId);
              updateSaturation(event);
            }}
            onPointerMove={(event) => {
              if (event.currentTarget.hasPointerCapture(event.pointerId)) updateSaturation(event);
            }}
          >
            <span className="pointer-events-none absolute inset-0 bg-linear-to-r from-white to-transparent" />
            <span className="pointer-events-none absolute inset-0 bg-linear-to-b from-transparent to-black" />
            <span
              className="pointer-events-none absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[0_0_0_1px_rgb(0_0_0/0.45)]"
              style={{ left: `${hsv.saturation}%`, top: `${100 - hsv.value}%` }}
            />
          </div>

          <input
            className="egami-color-hue block h-4 w-full cursor-pointer appearance-none bg-transparent"
            type="range"
            min={0}
            max={359}
            value={Math.round(hsv.hue)}
            aria-label={`${label} hue`}
            onInput={(event) => emit({ ...hsv, hue: Number(event.currentTarget.value) })}
          />

          <div className="flex items-center gap-2">
            <span className="size-7 shrink-0 rounded-md border shadow-xs" style={{ backgroundColor: rgbToHex(fallback) }} />
            <span className="text-xs text-muted-foreground">{rgbToHex(fallback)}</span>
          </div>
        </PopoverContent>
      </Popover>

      <input
        data-slot="color-value-input"
        className="min-w-0 flex-1 bg-transparent text-xs outline-none"
        value={draft}
        aria-label={label}
        spellCheck={false}
        onChange={(event) => {
          const next = event.target.value;
          setDraft(next);
          const parsed = parseColor(next);
          if (parsed) onChange(rgbToHex(parsed));
        }}
        onBlur={() => {
          const parsed = parseColor(draft);
          setDraft(parsed ? rgbToHex(parsed) : value);
        }}
      />
    </div>
  );
}

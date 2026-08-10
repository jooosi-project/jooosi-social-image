import type { Background, GradientStop } from "@/types/admin";

function clamp(value: number, minimum: number, maximum: number): number {
  return Math.min(maximum, Math.max(minimum, value));
}

function hexToRgb(color: string): [number, number, number] | null {
  const value = color.trim();
  const short = /^#([0-9a-f]{3})$/i.exec(value);

  if (short) {
    return [
      Number.parseInt(short[1][0] + short[1][0], 16),
      Number.parseInt(short[1][1] + short[1][1], 16),
      Number.parseInt(short[1][2] + short[1][2], 16),
    ];
  }

  const full = /^#([0-9a-f]{6})$/i.exec(value);
  if (!full) return null;

  return [
    Number.parseInt(full[1].slice(0, 2), 16),
    Number.parseInt(full[1].slice(2, 4), 16),
    Number.parseInt(full[1].slice(4, 6), 16),
  ];
}

function rgbToHex(red: number, green: number, blue: number): string {
  return `#${[red, green, blue].map((channel) => clamp(Math.round(channel), 0, 255).toString(16).padStart(2, "0")).join("")}`;
}

export function sortedGradientStops(stops: GradientStop[]): GradientStop[] {
  return stops
    .map((stop) => ({ color: stop.color, position: clamp(stop.position, 0, 100) }))
    .sort((left, right) => left.position - right.position);
}

export function gradientCss(background: Background): string {
  const angle = background.direction === "vertical" ? "180deg" : "90deg";
  const stops = sortedGradientStops(background.stops);

  if (stops.length < 2) {
    return `linear-gradient(${angle}, ${background.color} 0%, ${background.color} 100%)`;
  }

  return `linear-gradient(${angle}, ${stops.map((stop) => `${stop.color} ${stop.position}%`).join(", ")})`;
}

export function backgroundCss(background: Background): string {
  return background.type === "gradient" ? gradientCss(background) : background.color;
}

export function interpolateGradientColor(left: GradientStop, right: GradientStop, position: number): string {
  const leftRgb = hexToRgb(left.color);
  const rightRgb = hexToRgb(right.color);
  if (!leftRgb || !rightRgb) return left.color;

  const distance = right.position - left.position;
  const ratio = distance > 0 ? clamp((position - left.position) / distance, 0, 1) : 0;

  return rgbToHex(
    leftRgb[0] + (rightRgb[0] - leftRgb[0]) * ratio,
    leftRgb[1] + (rightRgb[1] - leftRgb[1]) * ratio,
    leftRgb[2] + (rightRgb[2] - leftRgb[2]) * ratio,
  );
}

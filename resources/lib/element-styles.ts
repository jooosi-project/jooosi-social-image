import type { ImageMask, ShapeKind } from "@/types/admin";

const FONT_STACKS: Record<string, string> = {
  system: 'Arial, "Liberation Sans", "DejaVu Sans", sans-serif',
  arial: 'Arial, "Liberation Sans", "DejaVu Sans", sans-serif',
  georgia: 'Georgia, "Liberation Serif", "DejaVu Serif", serif',
  verdana: 'Verdana, "Liberation Sans", "DejaVu Sans", sans-serif',
};

export function fontFamilyCss(family: string): string {
  const normalized = family.trim();
  const stack = FONT_STACKS[normalized.toLowerCase()];

  if (stack) return stack;
  if (!normalized) return FONT_STACKS.system;

  return `"${normalized.replace(/\\/g, "\\\\").replace(/"/g, '\\"')}"`;
}

export function shapeClipPath(shape: ShapeKind | ImageMask): string | undefined {
  if (shape === "ellipse") return "ellipse(50% 50% at 50% 50%)";
  if (shape === "triangle") return "polygon(50% 0%, 100% 100%, 0% 100%)";
  if (shape === "diamond") return "polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)";
  if (shape === "hexagon") return "polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%)";
  if (shape === "star") return "polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 92%, 50% 70%, 21% 92%, 32% 57%, 2% 35%, 39% 35%)";
  return undefined;
}

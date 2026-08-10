import type { Background, DesignDocument } from "@/types/admin";

function solid(color: string): Background {
  return {
    type: "solid",
    color,
    stops: [{ color, position: 0 }, { color, position: 100 }],
    direction: "horizontal",
  };
}

export const BLANK_DOCUMENT: DesignDocument = {
  version: 6,
  width: 1200,
  height: 630,
  background: solid("#ffffff"),
  elements: [],
};

export function instantiateDocument(source: DesignDocument): DesignDocument {
  const next = structuredClone(source);
  next.version = 6;
  next.elements = next.elements.map((element) => ({
    ...element,
    id: `${element.type}-${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`}`,
  }));
  return next;
}

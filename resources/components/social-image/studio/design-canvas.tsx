import { useCallback, useEffect, useRef, useState } from "react";

import IconExpand from "~icons/lucide/maximize-2";

import { Button } from "@/components/ui/button";
import { fontFamilyCss, shapeClipPath } from "@/lib/element-styles";
import { cn } from "@/lib/utils";
import { backgroundCss } from "@/lib/gradient";
import type { DesignDocument, DesignElement, PostOption } from "@/types/admin";

type DesignCanvasProps = {
  document: DesignDocument;
  selectedId: string | null;
  previewPost: PostOption | null;
  placeholderValues: Record<string, unknown>;
  onSelect: (id: string | null) => void;
  onDocumentChange: (document: DesignDocument, recordHistory?: boolean) => void;
  onSnapshot: () => void;
};

type PointerSession = {
  id: string;
  handle: "" | "rotate" | ResizeHandle;
  startX: number;
  startY: number;
  initial: DesignElement;
  centerX?: number;
  centerY?: number;
  startAngle?: number;
};

const HANDLES = ["nw", "n", "ne", "e", "se", "s", "sw", "w"] as const;
type ResizeHandle = typeof HANDLES[number];

const MIN_ELEMENT_SIZE = 20;

function roundedCoordinate(value: number): number {
  return Math.round(value * 10) / 10;
}

function resizeElement(initial: DesignElement, handle: ResizeHandle, dx: number, dy: number): DesignElement {
  const radians = initial.rotation * Math.PI / 180;
  const cosine = Math.cos(radians);
  const sine = Math.sin(radians);

  // Pointer coordinates arrive in canvas space. Project them onto the
  // element's rotated local axes before changing its dimensions.
  const localDx = dx * cosine + dy * sine;
  const localDy = -dx * sine + dy * cosine;
  const nextWidth = handle.includes("e")
    ? Math.max(MIN_ELEMENT_SIZE, Math.round(initial.width + localDx))
    : handle.includes("w")
      ? Math.max(MIN_ELEMENT_SIZE, Math.round(initial.width - localDx))
      : initial.width;
  const nextHeight = handle.includes("s")
    ? Math.max(MIN_ELEMENT_SIZE, Math.round(initial.height + localDy))
    : handle.includes("n")
      ? Math.max(MIN_ELEMENT_SIZE, Math.round(initial.height - localDy))
      : initial.height;

  const widthChange = nextWidth - initial.width;
  const heightChange = nextHeight - initial.height;
  const localCenterShiftX = handle.includes("e") ? widthChange / 2 : handle.includes("w") ? -widthChange / 2 : 0;
  const localCenterShiftY = handle.includes("s") ? heightChange / 2 : handle.includes("n") ? -heightChange / 2 : 0;
  const worldCenterShiftX = localCenterShiftX * cosine - localCenterShiftY * sine;
  const worldCenterShiftY = localCenterShiftX * sine + localCenterShiftY * cosine;
  const initialCenterX = initial.x + initial.width / 2;
  const initialCenterY = initial.y + initial.height / 2;

  return {
    ...initial,
    width: nextWidth,
    height: nextHeight,
    // Move the center by half the size delta along the rotated local axis.
    // This keeps the edge or corner opposite the dragged handle stationary.
    x: roundedCoordinate(initialCenterX + worldCenterShiftX - nextWidth / 2),
    y: roundedCoordinate(initialCenterY + worldCenterShiftY - nextHeight / 2),
  } as DesignElement;
}

function resizeCursor(handle: ResizeHandle, rotation: number): React.CSSProperties["cursor"] {
  const directions: Record<ResizeHandle, number> = {
    e: 0,
    se: 45,
    s: 90,
    sw: 135,
    w: 180,
    nw: 225,
    n: 270,
    ne: 315,
  };
  const cursors = ["ew-resize", "nwse-resize", "ns-resize", "nesw-resize"] as const;
  const axis = ((directions[handle] + rotation) % 180 + 180) % 180;

  return cursors[Math.round(axis / 45) % cursors.length];
}

function canvasBackground(document: DesignDocument): React.CSSProperties {
  return { background: backgroundCss(document.background) };
}

function verticalAlignment(value: string): React.CSSProperties["justifyContent"] {
  if (value === "middle") return "center";
  if (value === "bottom") return "flex-end";
  return "flex-start";
}

function linkedValue(values: Record<string, unknown>, path: string): unknown {
  let value: unknown = values;
  for (const segment of path.split(".")) {
    if (!value || typeof value !== "object" || !(segment in value)) return undefined;
    value = (value as Record<string, unknown>)[segment];
  }
  return value;
}

function linkedText(content: string, post: PostOption | null, values: Record<string, unknown>): string {
  if (!post) return content;

  const fallbacks: Record<string, string> = {
    "post.title": post?.title || "Your post title will be displayed here",
    "post.date": new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(new Date()),
    "post.excerpt": "A short excerpt from the selected post appears here.",
    "post.author.name": "Post author",
    "site.name": "Your site name",
  };

  return content.replace(/\{\{\s*([^}]+)\s*}}/g, (match, key: string) => {
    const path = key.trim();
    const resolved = linkedValue(values, path);
    if (["string", "number", "boolean"].includes(typeof resolved)) return String(resolved);
    if (Array.isArray(resolved)) return resolved.flat(Infinity).filter((item) => ["string", "number", "boolean"].includes(typeof item)).join(", ");
    return fallbacks[path] || match;
  });
}

function linkedImage(source: string, values: Record<string, unknown>, enabled: boolean): string {
  if (!enabled) return /^https?:\/\//i.test(source.trim()) ? source.trim() : "";
  const match = source.match(/^\s*\{\{\s*([^}]+)\s*}}\s*$/);
  if (!match) return /^https?:\/\//i.test(source.trim()) ? source.trim() : "";
  const resolved = linkedValue(values, match[1].trim());
  return typeof resolved === "string" && /^https?:\/\//i.test(resolved) ? resolved : "";
}

function ElementContent({ element, previewPost, placeholderValues }: { element: DesignElement; previewPost: PostOption | null; placeholderValues: Record<string, unknown> }) {
  if (element.type === "text") {
    return (
      <div
        className="flex size-full whitespace-pre-wrap break-words"
        style={{
          color: element.color,
          fontFamily: fontFamilyCss(element.fontFamily),
          fontSize: element.fontSize,
          fontWeight: element.fontWeight,
          lineHeight: element.lineHeight,
          textAlign: element.align,
          justifyContent: verticalAlignment(element.verticalAlign),
          flexDirection: "column",
        }}
      >
        {linkedText(element.content, previewPost, placeholderValues)}
      </div>
    );
  }

  if (element.type === "svg") {
    if (!element.icon) {
      return <div className="grid size-full place-items-center rounded border border-dashed border-white/45 bg-black/15 text-sm text-white/80">Choose an SVG icon</div>;
    }

    return <jooosi-icon className="block size-full" name={element.icon} width="100%" height="100%" color={element.color} aria-hidden="true" />;
  }

  if (element.type === "shape") {
    if (element.shape === "line" || element.shape === "arrow") {
      const thickness = Math.max(1, element.strokeWidth);
      return (
        <div className="relative size-full">
          <span className="absolute left-0 right-0 top-1/2 block -translate-y-1/2" style={{ height: thickness, background: element.strokeColor }} />
          {element.shape === "arrow" && (
            <span
              className="absolute right-0 top-1/2 block -translate-y-1/2"
              style={{
                borderBottom: `${Math.min(element.height * 0.45, 48)}px solid transparent`,
                borderLeft: `${Math.min(element.height * 0.45, 48)}px solid ${element.strokeColor}`,
                borderTop: `${Math.min(element.height * 0.45, 48)}px solid transparent`,
              }}
            />
          )}
        </div>
      );
    }

    return (
      <div
        className="size-full"
        style={{
          background: backgroundCss(element.background),
          clipPath: shapeClipPath(element.shape),
          borderRadius: element.shape === "rectangle" ? element.radius : 0,
          boxShadow: element.strokeWidth > 0 ? `inset 0 0 0 ${element.strokeWidth}px ${element.strokeColor}` : undefined,
        }}
      />
    );
  }

  const imageSource = element.previewUrl || linkedImage(element.source, placeholderValues, previewPost !== null);
  if (imageSource) {
    return <img className="size-full" src={imageSource} alt="" draggable={false} style={{ objectFit: element.fit, borderRadius: element.mask === "none" ? element.radius : 0, clipPath: shapeClipPath(element.mask) }} />;
  }

  return (
    <div className="grid size-full place-items-center rounded border border-dashed border-white/45 bg-black/15 px-3 text-center text-sm text-white/80" style={{ clipPath: shapeClipPath(element.mask) }}>
      {element.source || "Choose an image"}
    </div>
  );
}

type CanvasElementProps = {
  element: DesignElement;
  previewPost: PostOption | null;
  placeholderValues: Record<string, unknown>;
  onBeginPointer: (event: React.PointerEvent, element: DesignElement, handle: PointerSession["handle"]) => void;
};

function CanvasElement({
  element,
  previewPost,
  placeholderValues,
  onBeginPointer,
}: CanvasElementProps) {
  return (
    <div
      className={cn(
        "absolute select-none",
        element.locked ? "cursor-not-allowed" : "cursor-move",
      )}
      style={{
        left: element.x,
        top: element.y,
        width: element.width,
        height: element.height,
        opacity: element.hidden ? 0.2 : element.opacity,
        transform: `rotate(${element.rotation}deg)`,
      }}
      onPointerDown={(event) => onBeginPointer(event, element, "")}
    >
      <ElementContent element={element} previewPost={previewPost} placeholderValues={placeholderValues} />
    </div>
  );
}

function CanvasSelectionControls({ element, onBeginPointer }: { element: DesignElement; onBeginPointer: CanvasElementProps["onBeginPointer"] }) {
  return (
    <div
      className="pointer-events-none absolute select-none outline outline-2 outline-offset-1 outline-blue-500"
      style={{
        left: element.x,
        top: element.y,
        width: element.width,
        height: element.height,
        transform: `rotate(${element.rotation}deg)`,
      }}
    >
      {!element.locked && HANDLES.map((handle) => (
        <span
          key={handle}
          className={`social-image-resize-handle social-image-resize-${handle}`}
          style={{ cursor: resizeCursor(handle, element.rotation) }}
          onPointerDown={(event) => onBeginPointer(event, element, handle)}
        />
      ))}
      {!element.locked && (
        <>
          <span className="social-image-rotation-line" aria-hidden="true" />
          <button
            type="button"
            className="social-image-rotation-handle"
            aria-label="Rotate element"
            title="Drag to rotate. Hold Shift to snap to 15° increments."
            onPointerDown={(event) => onBeginPointer(event, element, "rotate")}
          />
        </>
      )}
    </div>
  );
}

export function DesignCanvas({
  document,
  selectedId,
  previewPost,
  placeholderValues,
  onSelect,
  onDocumentChange,
  onSnapshot,
}: DesignCanvasProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const pointerRef = useRef<PointerSession | null>(null);
  const [scale, setScale] = useState(0.6);
  const selectedElement = document.elements.find((element) => element.id === selectedId);

  const fit = useCallback(() => {
    const viewport = viewportRef.current;
    if (!viewport) return;
    const availableWidth = Math.max(300, viewport.clientWidth - 72);
    const availableHeight = Math.max(240, viewport.clientHeight - 72);
    setScale(Math.min(1, availableWidth / document.width, availableHeight / document.height));
  }, [document.height, document.width]);

  useEffect(() => {
    fit();
    const observer = new ResizeObserver(fit);
    if (viewportRef.current) observer.observe(viewportRef.current);
    return () => observer.disconnect();
  }, [fit]);

  useEffect(() => {
    const move = (event: PointerEvent) => {
      const session = pointerRef.current;
      if (!session) return;
      const dx = (event.clientX - session.startX) / scale;
      const dy = (event.clientY - session.startY) / scale;
      let next = { ...session.initial } as DesignElement;

      if (session.handle === "rotate") {
        if (session.centerX !== undefined && session.centerY !== undefined && session.startAngle !== undefined) {
          const angle = Math.atan2(event.clientY - session.centerY, event.clientX - session.centerX) * 180 / Math.PI;
          let rotation = session.initial.rotation + angle - session.startAngle;
          rotation = ((rotation + 180) % 360 + 360) % 360 - 180;
          next.rotation = event.shiftKey ? Math.round(rotation / 15) * 15 : Math.round(rotation * 10) / 10;
        }
      } else if (session.handle === "") {
        next.x = Math.round(session.initial.x + dx);
        next.y = Math.round(session.initial.y + dy);
      } else {
        next = resizeElement(session.initial, session.handle, dx, dy);
      }

      onDocumentChange({
        ...document,
        elements: document.elements.map((element) => element.id === session.id ? next : element),
      }, false);
    };
    const end = () => { pointerRef.current = null; };

    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", end);
    return () => {
      window.removeEventListener("pointermove", move);
      window.removeEventListener("pointerup", end);
    };
  }, [document, onDocumentChange, scale]);

  const beginPointer = (event: React.PointerEvent, element: DesignElement, handle: PointerSession["handle"] = "") => {
    event.preventDefault();
    event.stopPropagation();
    onSelect(element.id);
    if (element.locked) return;
    onSnapshot();
    const bounds = event.currentTarget.parentElement?.getBoundingClientRect();
    pointerRef.current = {
      id: element.id,
      handle,
      startX: event.clientX,
      startY: event.clientY,
      initial: structuredClone(element),
      centerX: handle === "rotate" ? bounds?.left !== undefined ? bounds.left + bounds.width / 2 : undefined : undefined,
      centerY: handle === "rotate" ? bounds?.top !== undefined ? bounds.top + bounds.height / 2 : undefined : undefined,
      startAngle: handle === "rotate" && bounds ? Math.atan2(event.clientY - (bounds.top + bounds.height / 2), event.clientX - (bounds.left + bounds.width / 2)) * 180 / Math.PI : undefined,
    };
  };

  return (
    <main ref={viewportRef} className="social-image-canvas-viewport" onPointerDown={() => onSelect(null)}>
      <div className="absolute bottom-3 left-3 z-10 flex items-center gap-1 rounded-md border bg-background/90 px-1.5 py-1 text-[10px] text-muted-foreground shadow-sm backdrop-blur">
        <span>{document.width} × {document.height}</span>
        <span>·</span>
        <span>{Math.round(scale * 100)}%</span>
        <Button size="icon-xs" variant="ghost" className="ml-0.5 size-5" title="Fit canvas" onClick={(event) => { event.stopPropagation(); fit(); }}><IconExpand /></Button>
      </div>

      <div className="relative shrink-0 shadow-[0_18px_45px_rgb(15_23_42/0.15)]" style={{ width: document.width * scale, height: document.height * scale }}>
        <div
          className="absolute left-0 top-0 origin-top-left overflow-hidden"
          style={{ width: document.width, height: document.height, transform: `scale(${scale})`, ...canvasBackground(document) }}
        >
          {document.elements.map((element) => (
            <CanvasElement
              key={element.id}
              element={element}
              previewPost={previewPost}
              placeholderValues={placeholderValues}
              onBeginPointer={beginPointer}
            />
          ))}
        </div>
        {selectedElement && (
          <div
            className="pointer-events-none absolute left-0 top-0 origin-top-left overflow-visible"
            style={{ width: document.width, height: document.height, transform: `scale(${scale})` }}
          >
            <CanvasSelectionControls
              element={selectedElement}
              onBeginPointer={beginPointer}
            />
          </div>
        )}
      </div>
    </main>
  );
}

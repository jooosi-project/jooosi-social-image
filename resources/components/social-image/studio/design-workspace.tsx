import { useEffect, useMemo } from "react";

import { DesignCanvas } from "@/components/social-image/studio/design-canvas";
import { ElementInspector } from "@/components/social-image/studio/element-inspector";
import { StructurePanel } from "@/components/social-image/studio/structure-panel";
import type {
  DesignElement,
  ElementType,
  ImageElement,
  IconSearchResult,
  PostOption,
  PlaceholderDefinition,
  Design,
  DesignDocument,
  SvgStatus,
  WebfontStatus,
} from "@/types/admin";

type DesignWorkspaceProps = {
  design: Design;
  selectedId: string | null;
  posts: PostOption[];
  previewPostId: number;
  canUndo: boolean;
  canRedo: boolean;
  svgStatus: SvgStatus;
  webfontStatus: WebfontStatus;
  placeholders: PlaceholderDefinition[];
  placeholderValues: Record<string, unknown>;
  onSearchIcons: (query: string) => Promise<IconSearchResult[]>;
  onDesignChange: (design: Design, recordHistory?: boolean) => void;
  onSnapshot: () => void;
  onSelect: (id: string | null) => void;
  onUndo: () => void;
  onRedo: () => void;
  onSave: () => void;
};

function nextId(type: ElementType): string {
  const suffix = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `${type}-${suffix}`.toLowerCase();
}

function createElement(type: ElementType, document: DesignDocument): DesignElement {
  const offset = Math.min(160, 72 + document.elements.length * 12);
  const base = {
    id: nextId(type),
    type,
    x: offset,
    y: offset,
    width: 480,
    height: 140,
    opacity: 1,
    rotation: 0,
    locked: false,
    hidden: false,
  };

  if (type === "text") {
    return {
      ...base,
      type: "text",
      content: "Your awesome post title will be displayed here",
      fontSize: 64,
      minFontSize: 18,
      fontFamily: "system",
      fontAttachmentId: 0,
      fontWeight: 700,
      color: "#ffffff",
      align: "left",
      verticalAlign: "middle",
      lineHeight: 1.1,
    };
  }

  if (type === "shape") {
    return {
      ...base,
      type: "shape",
      width: 520,
      height: 220,
      shape: "rectangle",
      background: { type: "solid", color: "#111827", stops: [{ color: "#111827", position: 0 }, { color: "#312e81", position: 100 }], direction: "horizontal" },
      strokeColor: "#ffffff",
      strokeWidth: 0,
      radius: 0,
    };
  }

  if (type === "svg") {
    return {
      ...base,
      type: "svg",
      width: 180,
      height: 180,
      icon: "",
      color: "#ffffff",
    };
  }

  return {
    ...base,
    type: "image",
    width: 460,
    height: 300,
    attachmentId: 0,
    source: "{{post.featured_image}}",
    previewUrl: "",
    fit: "cover",
    radius: 0,
    mask: "none",
  };
}

export function DesignWorkspace({
  design,
  selectedId,
  posts,
  previewPostId,
  canUndo,
  canRedo,
  svgStatus,
  webfontStatus,
  placeholders,
  placeholderValues,
  onSearchIcons,
  onDesignChange,
  onSnapshot,
  onSelect,
  onUndo,
  onRedo,
  onSave,
}: DesignWorkspaceProps) {
  const selected = design.document.elements.find((element) => element.id === selectedId) || null;
  const previewPost = useMemo(() => posts.find((post) => post.id === previewPostId) || null, [posts, previewPostId]);

  const updateDocument = (document: DesignDocument, recordHistory = true) => {
    onDesignChange({ ...design, document }, recordHistory);
  };

  const updateElement = (next: DesignElement) => {
    updateDocument({
      ...design.document,
      elements: design.document.elements.map((element) => element.id === next.id ? next : element),
    });
  };

  const addElement = (type: ElementType) => {
    const element = createElement(type, design.document);
    updateDocument({ ...design.document, elements: [...design.document.elements, element] });
    onSelect(element.id);
  };

  const duplicateElement = (element: DesignElement) => {
    const copy = { ...structuredClone(element), id: nextId(element.type), x: element.x + 24, y: element.y + 24 } as DesignElement;
    updateDocument({ ...design.document, elements: [...design.document.elements, copy] });
    onSelect(copy.id);
  };

  const deleteElement = (element: DesignElement) => {
    updateDocument({ ...design.document, elements: design.document.elements.filter((item) => item.id !== element.id) });
    if (selectedId === element.id) onSelect(null);
  };

  const moveElement = (element: DesignElement, direction: -1 | 1) => {
    const elements = [...design.document.elements];
    const index = elements.findIndex((item) => item.id === element.id);
    const target = index + direction;
    if (index < 0 || target < 0 || target >= elements.length) return;
    [elements[index], elements[target]] = [elements[target], elements[index]];
    updateDocument({ ...design.document, elements });
  };

  const chooseMedia = () => {
    if (!selected || !window.wp?.media) return;
    const frame = window.wp.media({
      title: "Choose an image",
      button: { text: "Use this file" },
      multiple: false,
      library: { type: "image" },
    });

    frame.on("select", () => {
      const item = frame.state().get("selection").first().toJSON();
      if (selected.type === "image") {
        updateElement({ ...selected, attachmentId: Number(item.id || 0), previewUrl: item.url || "", source: "" } as ImageElement);
      }
    });
    frame.open();
  };

  useEffect(() => {
    const keydown = (event: KeyboardEvent) => {
      const modifier = event.metaKey || event.ctrlKey;
      if (modifier && event.key.toLowerCase() === "s") {
        event.preventDefault();
        onSave();
      }
      if (modifier && event.key.toLowerCase() === "z") {
        event.preventDefault();
        event.shiftKey ? onRedo() : onUndo();
      }
      if (
        selected
        && (event.key === "Delete" || event.key === "Backspace")
        && !["INPUT", "TEXTAREA", "SELECT"].includes((globalThis.document.activeElement as HTMLElement | null)?.tagName || "")
      ) {
        deleteElement(selected);
      }
    };

    window.addEventListener("keydown", keydown);
    return () => window.removeEventListener("keydown", keydown);
  });

  return (
    <div className="social-image-design-workspace">
      <StructurePanel
        document={design.document}
        selectedId={selectedId}
        canUndo={canUndo}
        canRedo={canRedo}
        svgStatus={svgStatus}
        onAdd={addElement}
        onSelect={onSelect}
        onElementChange={updateElement}
        onDuplicate={duplicateElement}
        onMove={moveElement}
        onReorder={(elements) => updateDocument({ ...design.document, elements })}
        onDelete={deleteElement}
        onUndo={onUndo}
        onRedo={onRedo}
      />
      <DesignCanvas
        document={design.document}
        selectedId={selectedId}
        previewPost={previewPost}
        placeholderValues={placeholderValues}
        onSelect={onSelect}
        onDocumentChange={updateDocument}
        onSnapshot={onSnapshot}
      />
      <ElementInspector
        element={selected}
        onElementChange={updateElement}
        onChooseMedia={chooseMedia}
        onDelete={() => selected && deleteElement(selected)}
        onDuplicate={() => selected && duplicateElement(selected)}
        onMove={(direction) => selected && moveElement(selected, direction)}
        svgStatus={svgStatus}
        webfontStatus={webfontStatus}
        placeholders={placeholders}
        onSearchIcons={onSearchIcons}
      />
    </div>
  );
}

import IconImage from "~icons/lucide/image";

import { backgroundCss } from "@/lib/gradient";
import { fontFamilyCss, shapeClipPath } from "@/lib/element-styles";
import { cn } from "@/lib/utils";
import type { DesignDocument, DesignElement } from "@/types/admin";

type DesignPreviewProps = {
  document: DesignDocument;
  className?: string;
};

const PREVIEW_CONTENT: Record<string, string> = {
  "site.name": "SOCIAL IMAGE JOURNAL",
  "site.tagline": "Ideas worth sharing",
  "post.title": "The future is built one bold idea at a time",
  "post.excerpt": "A practical field guide for turning thoughtful ideas into work that matters.",
  "post.date": "AUGUST 7, 2026",
  "post.modified": "AUGUST 7, 2026",
  "post.author.name": "MAYA CHEN",
};

function previewContent(content: string): string {
  return content.replace(/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g, (match, key: string) => PREVIEW_CONTENT[key] ?? match);
}

function backgroundStyle(document: DesignDocument): React.CSSProperties {
  return { background: backgroundCss(document.background) };
}

function PreviewElement({ element, parentWidth, parentHeight }: { element: DesignElement; parentWidth: number; parentHeight: number }) {
  if (element.hidden) return null;
  const position: React.CSSProperties = {
    position: "absolute",
    left: `${(element.x / parentWidth) * 100}%`,
    top: `${(element.y / parentHeight) * 100}%`,
    width: `${(element.width / parentWidth) * 100}%`,
    height: `${(element.height / parentHeight) * 100}%`,
    opacity: element.opacity,
    transform: `rotate(${element.rotation}deg)`,
  };

  if (element.type === "shape") {
    if (element.shape === "line" || element.shape === "arrow") {
      return (
        <span key={element.id} style={position}>
          <span className="absolute left-0 right-0 top-1/2 block -translate-y-1/2" style={{ height: Math.max(1, element.strokeWidth / 5), background: element.strokeColor }} />
          {element.shape === "arrow" && <span className="absolute right-0 top-1/2 block -translate-y-1/2 border-y-[6px] border-y-transparent border-l-[9px]" style={{ borderLeftColor: element.strokeColor }} />}
        </span>
      );
    }

    return <span key={element.id} style={{ ...position, background: backgroundCss(element.background), borderRadius: element.shape === "rectangle" ? `${element.radius / 5}px` : 0, clipPath: shapeClipPath(element.shape), boxShadow: element.strokeWidth > 0 ? `inset 0 0 0 ${Math.max(1, element.strokeWidth / 5)}px ${element.strokeColor}` : undefined }} />;
  }

  if (element.type === "image") {
    return (
      <span
        key={element.id}
        className="grid place-items-center overflow-hidden bg-slate-600/80 text-white/70"
        style={{
          ...position,
          borderRadius: element.mask === "none" ? `${element.radius / 5}px` : 0,
          clipPath: shapeClipPath(element.mask),
          backgroundImage: element.previewUrl ? `url(${element.previewUrl})` : "linear-gradient(135deg, #334155, #0f172a)",
          backgroundSize: element.fit === "fill" ? "100% 100%" : element.fit,
          backgroundRepeat: "no-repeat",
          backgroundPosition: "center",
        }}
      >
        {!element.previewUrl && <IconImage className="size-6" />}
      </span>
    );
  }

  if (element.type === "svg") {
    return (
      <span key={element.id} className="grid place-items-center" style={position}>
        <jooosi-icon name={element.icon} width="100%" height="100%" color={element.color} aria-hidden="true" />
      </span>
    );
  }

  return (
    <span
      key={element.id}
      className="flex overflow-hidden whitespace-pre-wrap"
      style={{
        ...position,
        alignItems: element.verticalAlign === "top" ? "flex-start" : element.verticalAlign === "bottom" ? "flex-end" : "center",
        justifyContent: element.align === "center" ? "center" : element.align === "right" ? "flex-end" : "flex-start",
        color: element.color,
        fontFamily: fontFamilyCss(element.fontFamily),
        fontSize: `${Math.max(7, element.fontSize * 0.18)}px`,
        fontWeight: element.fontWeight,
        lineHeight: element.lineHeight,
        textAlign: element.align,
      }}
    >
      {previewContent(element.content)}
    </span>
  );
}

export function DesignPreview({ document, className }: DesignPreviewProps) {
  return (
    <div className={cn("relative aspect-[1200/630] w-full overflow-hidden", className)} style={backgroundStyle(document)}>
      {document.elements.map((element) => {
        return <PreviewElement key={element.id} element={element} parentWidth={document.width} parentHeight={document.height} />;
      })}
      {document.elements.length === 0 && <span className="absolute inset-0 grid place-items-center text-xs font-medium text-slate-400">Blank canvas</span>}
    </div>
  );
}

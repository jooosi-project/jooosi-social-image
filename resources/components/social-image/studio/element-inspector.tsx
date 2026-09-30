import { useEffect, useRef, useState, type ReactNode } from "react";

import IconChevronDown from "~icons/lucide/chevron-down";
import IconCopy from "~icons/lucide/copy";
import IconEllipsis from "~icons/lucide/ellipsis";
import IconImage from "~icons/lucide/image";
import IconFileCode from "~icons/lucide/file-code-2";
import IconLock from "~icons/lucide/lock";
import IconMoveDown from "~icons/lucide/move-down";
import IconMoveUp from "~icons/lucide/move-up";
import IconShapes from "~icons/lucide/shapes";
import IconText from "~icons/lucide/type";
import IconTrash from "~icons/lucide/trash-2";
import IconUnlock from "~icons/lucide/unlock";
import IconX from "~icons/lucide/x";

import { BackgroundEditor } from "@/components/social-image/studio/background-editor";
import {
  PlaceholderPicker,
  placeholderAutocompleteRange,
  type PlaceholderPickerHandle,
} from "@/components/social-image/studio/placeholder-picker";
import { Button } from "@/components/ui/button";
import { ColorPicker } from "@/components/ui/color-picker";
import { IconPickerDialog } from "@/components/social-image/studio/icon-picker-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { NativeSelect } from "@/components/ui/native-select";
import { Textarea } from "@/components/ui/textarea";
import type { DesignElement, IconSearchResult, PlaceholderDefinition, SvgStatus, WebfontStatus } from "@/types/admin";

type ElementInspectorProps = {
  element: DesignElement | null;
  onElementChange: (element: DesignElement) => void;
  onChooseMedia: () => void;
  onDelete: () => void;
  onDuplicate: () => void;
  onMove: (direction: -1 | 1) => void;
  svgStatus: SvgStatus;
  webfontStatus: WebfontStatus;
  onSearchIcons: (query: string) => Promise<IconSearchResult[]>;
  placeholders: PlaceholderDefinition[];
};

function InspectorSection({ title, children, defaultOpen = false }: { title: string; children: ReactNode; defaultOpen?: boolean }) {
  return (
    <details className="group border-b" open={defaultOpen || undefined}>
      <summary className="flex h-10 cursor-pointer list-none items-center px-3 text-xs font-semibold [&::-webkit-details-marker]:hidden">
        {title}
        <IconChevronDown className="ml-auto size-3.5 text-muted-foreground transition-transform group-open:rotate-180" />
      </summary>
      <div className="space-y-3 px-3 pb-4">{children}</div>
    </details>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block space-y-1">
      <span className="text-[11px] font-medium text-foreground/75">{label}</span>
      {children}
    </label>
  );
}

function NumberField({ label, value, onChange, ...props }: { label: string; value: number; onChange: (value: number) => void } & Omit<React.ComponentProps<"input">, "value" | "onChange">) {
  const [draft, setDraft] = useState(String(value));

  useEffect(() => setDraft(String(value)), [value]);

  const commit = (input: HTMLInputElement) => {
    if (!Number.isFinite(input.valueAsNumber)) {
      setDraft(String(value));
      return;
    }

    const minimum = props.min === undefined ? Number.NEGATIVE_INFINITY : Number(props.min);
    const maximum = props.max === undefined ? Number.POSITIVE_INFINITY : Number(props.max);
    const next = Math.min(maximum, Math.max(minimum, input.valueAsNumber));
    setDraft(String(next));
    onChange(next);
  };

  return (
    <Field label={label}>
      <Input
        {...props}
        className="h-8 text-xs"
        type="number"
        value={draft}
        onChange={(event) => {
          setDraft(event.target.value);
          if (Number.isFinite(event.target.valueAsNumber)) onChange(event.target.valueAsNumber);
        }}
        onBlur={(event) => commit(event.currentTarget)}
        onKeyDown={(event) => {
          if (event.key === "Enter") {
            commit(event.currentTarget);
            event.currentTarget.blur();
          }
        }}
      />
    </Field>
  );
}

function SelectField({ label, value, options, onChange }: { label: string; value: string; options: Array<[string, string]>; onChange: (value: string) => void }) {
  return (
    <Field label={label}>
      <NativeSelect value={value} onChange={(event) => onChange(event.target.value)}>
        {options.map(([key, text]) => <option key={key} value={key}>{text}</option>)}
      </NativeSelect>
    </Field>
  );
}

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <div className="space-y-1">
      <span className="block text-[11px] font-medium text-foreground/75">{label}</span>
      <ColorPicker className="h-8" label={label} value={value} onChange={onChange} />
    </div>
  );
}

function InspectorIcon({ element }: { element: DesignElement | null }) {
  if (!element) return null;
  if (element.type === "text") return <IconText />;
  if (element.type === "svg") return <IconFileCode />;
  if (element.type === "shape") return <IconShapes />;
  return <IconImage />;
}

function InspectorMenu({ element, onDuplicate, onMove, onDelete }: Pick<ElementInspectorProps, "element" | "onDuplicate" | "onMove" | "onDelete">) {
  if (!element) return null;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger render={<Button size="icon-xs" variant="ghost" aria-label="Element actions" />}><IconEllipsis /></DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-44">
        <DropdownMenuItem onClick={onDuplicate}><IconCopy /> Duplicate</DropdownMenuItem>
        <DropdownMenuItem onClick={() => onMove(1)}><IconMoveUp /> Move forward</DropdownMenuItem>
        <DropdownMenuItem onClick={() => onMove(-1)}><IconMoveDown /> Move backward</DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem variant="destructive" onClick={onDelete}><IconTrash /> Delete</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function ElementInspector({
  element,
  onElementChange,
  onChooseMedia,
  onDelete,
  onDuplicate,
  onMove,
  svgStatus,
  webfontStatus,
  onSearchIcons,
  placeholders,
}: ElementInspectorProps) {
  const [iconPickerOpen, setIconPickerOpen] = useState(false);
  const contentRef = useRef<HTMLTextAreaElement>(null);
  const contentPickerRef = useRef<PlaceholderPickerHandle>(null);
  const contentSelectionRef = useRef({ start: 0, end: 0 });
  const contentAutocompleteRef = useRef<{ start: number; end: number } | null>(null);
  const imageRef = useRef<HTMLInputElement>(null);
  const imagePickerRef = useRef<PlaceholderPickerHandle>(null);
  const imageSelectionRef = useRef({ start: 0, end: 0 });
  const imageAutocompleteRef = useRef<{ start: number; end: number } | null>(null);

  useEffect(() => {
    const length = element?.type === "text" ? element.content.length : 0;
    contentSelectionRef.current = { start: length, end: length };
    contentAutocompleteRef.current = null;
    const imageLength = element?.type === "image" ? element.source.length : 0;
    imageSelectionRef.current = { start: imageLength, end: imageLength };
    imageAutocompleteRef.current = null;
  }, [element?.id]);

  if (!element) {
    return (
      <aside className="social-image-element-inspector" aria-label="Element inspector">
        <div className="grid min-h-0 flex-1 place-items-center px-6 text-center">
          <div>
            <p className="text-xs font-semibold">No element selected</p>
            <p className="mt-1 text-[11px] leading-4 text-muted-foreground">Choose an item from Structure or select it on the canvas to edit its properties.</p>
          </div>
        </div>
      </aside>
    );
  }

  const setBase = (key: "x" | "y" | "width" | "height" | "opacity" | "rotation" | "locked" | "hidden", value: number | boolean) => {
    onElementChange({ ...element, [key]: value } as DesignElement);
  };

  const label = element.type.charAt(0).toUpperCase() + element.type.slice(1);

  const insertTextPlaceholder = (token: string, source: "autocomplete" | "manual") => {
    if (element.type !== "text") return;
    const range = source === "autocomplete" ? contentAutocompleteRef.current || contentSelectionRef.current : contentSelectionRef.current;
    const { start, end } = range;
    const content = `${element.content.slice(0, start)}${token}${element.content.slice(end)}`;
    onElementChange({ ...element, content });
    contentAutocompleteRef.current = null;
    contentSelectionRef.current = { start: start + token.length, end: start + token.length };
    requestAnimationFrame(() => {
      contentRef.current?.focus();
      contentRef.current?.setSelectionRange(start + token.length, start + token.length);
    });
  };

  const updateImageSource = (source: string) => {
    if (element.type !== "image") return;
    onElementChange({
      ...element,
      attachmentId: 0,
      source,
      previewUrl: /^https?:\/\//i.test(source.trim()) ? source.trim() : "",
    });
  };

  const insertImagePlaceholder = (token: string, insertionSource: "autocomplete" | "manual") => {
    if (element.type !== "image") return;
    const range = insertionSource === "autocomplete" ? imageAutocompleteRef.current || imageSelectionRef.current : imageSelectionRef.current;
    const { start, end } = range;
    const nextSource = `${element.source.slice(0, start)}${token}${element.source.slice(end)}`;
    updateImageSource(nextSource);
    imageAutocompleteRef.current = null;
    imageSelectionRef.current = { start: start + token.length, end: start + token.length };
    requestAnimationFrame(() => {
      imageRef.current?.focus();
      imageRef.current?.setSelectionRange(start + token.length, start + token.length);
    });
  };

  const imagePlaceholders = placeholders.filter((placeholder) => placeholder.type === "image" || placeholder.type === "url");
  const hasImage = element.type === "image" && Boolean(element.attachmentId || element.previewUrl || element.source.trim());

  return (
    <>
      <aside className="social-image-element-inspector" aria-label="Element inspector">
      <header className="flex h-11 shrink-0 items-center gap-2 border-b px-3">
        <span className="text-muted-foreground"><InspectorIcon element={element} /></span>
        <span className="text-xs font-semibold">{label}</span>
        <div className="ml-auto"><InspectorMenu element={element} onDuplicate={onDuplicate} onMove={onMove} onDelete={onDelete} /></div>
      </header>

      <div className="social-image-component-form min-h-0 flex-1 overflow-y-auto">
        {element.type === "text" && (
          <section className="space-y-2 border-b px-3 py-3">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-medium">Content</span>
              <PlaceholderPicker
                ref={contentPickerRef}
                placeholders={placeholders}
                onSelect={insertTextPlaceholder}
              />
            </div>
            <Textarea
              ref={contentRef}
              aria-label="Text content"
              value={element.content}
              onSelect={(event) => {
                contentSelectionRef.current = {
                  start: event.currentTarget.selectionStart,
                  end: event.currentTarget.selectionEnd,
                };
              }}
              onChange={(event) => onElementChange({ ...element, content: event.target.value })}
              onInput={(event) => {
                const caret = event.currentTarget.selectionStart;
                const range = placeholderAutocompleteRange(event.currentTarget.value, caret);
                if (!range) {
                  contentAutocompleteRef.current = null;
                  contentPickerRef.current?.close();
                  return;
                }
                contentAutocompleteRef.current = { start: range.start, end: range.end };
                contentPickerRef.current?.openAutocomplete(range.query);
              }}
              onKeyDown={(event) => { contentPickerRef.current?.handleKeyDown(event); }}
            />
          </section>
        )}

        {element.type === "image" && (
          <section className="space-y-3 border-b px-3 py-3">
            <Button size="sm" className="w-full" onClick={onChooseMedia}><IconImage /> Choose Media Library image</Button>
            {hasImage && (
              <Button size="sm" variant="outline" className="w-full" onClick={() => updateImageSource("")}>
                <IconX /> Remove image
              </Button>
            )}
            <div className="space-y-1">
              <div className="flex items-center justify-between"><span className="text-[11px] font-medium text-foreground/75">Image source</span><PlaceholderPicker ref={imagePickerRef} placeholders={imagePlaceholders} label="Insert dynamic image" onSelect={insertImagePlaceholder} /></div>
              <Input
                ref={imageRef}
                aria-label="Image source"
                className="h-8 text-xs"
                value={element.source}
                placeholder="https://example.com/image.jpg or {{post.featured_image}}"
                onSelect={(event) => {
                  imageSelectionRef.current = {
                    start: event.currentTarget.selectionStart || 0,
                    end: event.currentTarget.selectionEnd || 0,
                  };
                }}
                onChange={(event) => updateImageSource(event.target.value)}
                onInput={(event) => {
                  const caret = event.currentTarget.selectionStart || 0;
                  const range = placeholderAutocompleteRange(event.currentTarget.value, caret);
                  if (!range) {
                    imageAutocompleteRef.current = null;
                    imagePickerRef.current?.close();
                    return;
                  }
                  imageAutocompleteRef.current = { start: range.start, end: range.end };
                  imagePickerRef.current?.openAutocomplete(range.query);
                }}
                onKeyDown={(event) => { imagePickerRef.current?.handleKeyDown(event); }}
              />
            </div>
            <p className="text-[10px] leading-4 text-muted-foreground">Paste a public HTTP(S) image URL, or use a dynamic placeholder. External images are validated and cached on the server when rendered.</p>
          </section>
        )}

        {element.type === "svg" && (
          <section className="space-y-3 border-b px-3 py-3">
            <div className="grid min-h-24 place-items-center rounded-md border bg-muted/30 p-3">
              {element.icon
                ? <jooosi-icon name={element.icon} width="56" height="56" color={element.color} aria-hidden="true" />
                : <span className="text-xs text-muted-foreground">No icon selected</span>}
            </div>
            <Button size="sm" className="w-full" disabled={!svgStatus.available} title={svgStatus.available ? undefined : svgStatus.reason} onClick={() => setIconPickerOpen(true)}>
              <IconFileCode /> Choose from Jooosi Icon
            </Button>
            {!svgStatus.available && <p className="text-[10px] leading-4 text-amber-700">{svgStatus.reason}</p>}
            {svgStatus.notice && <p className="text-[10px] leading-4 text-amber-700">{svgStatus.notice}</p>}
            <Field label="Icon name">
              <Input className="h-8 text-xs" value={element.icon} placeholder="mdi:home" onChange={(event) => onElementChange({ ...element, icon: event.target.value.toLowerCase().trim() })} />
            </Field>
            <ColorField label="Icon color" value={element.color} onChange={(color) => onElementChange({ ...element, color })} />
          </section>
        )}

        {element.type === "shape" && (
          <section className="space-y-3 border-b px-3 py-3">
            <SelectField
              label="Shape"
              value={element.shape}
              options={[["rectangle", "Rectangle"], ["ellipse", "Ellipse"], ["triangle", "Triangle"], ["diamond", "Diamond"], ["hexagon", "Hexagon"], ["star", "Star"], ["line", "Line"], ["arrow", "Arrow"]]}
              onChange={(shape) => onElementChange({ ...element, shape: shape as typeof element.shape })}
            />
          </section>
        )}

        <InspectorSection title="Position" defaultOpen>
          <div className="grid grid-cols-2 gap-2">
            <NumberField label="X" value={element.x} onChange={(value) => setBase("x", value)} />
            <NumberField label="Y" value={element.y} onChange={(value) => setBase("y", value)} />
          </div>
        </InspectorSection>

        <InspectorSection title="Layout" defaultOpen>
          <div className="grid grid-cols-2 gap-2">
            <NumberField label="Width" min={1} value={element.width} onChange={(value) => setBase("width", value)} />
            <NumberField label="Height" min={1} value={element.height} onChange={(value) => setBase("height", value)} />
          </div>
          {element.type === "image" && (
            <>
              <SelectField label="Object fit" value={element.fit} options={[["cover", "Cover"], ["contain", "Contain"], ["fill", "Fill"], ["none", "Original"]]} onChange={(fit) => onElementChange({ ...element, fit: fit as typeof element.fit })} />
              <SelectField label="Image mask" value={element.mask} options={[["none", "None"], ["ellipse", "Ellipse"], ["triangle", "Triangle"], ["diamond", "Diamond"], ["hexagon", "Hexagon"]]} onChange={(mask) => onElementChange({ ...element, mask: mask as typeof element.mask })} />
            </>
          )}
        </InspectorSection>

        {element.type === "text" && (
          <InspectorSection title="Typography" defaultOpen>
            <Field label="Font family">
              <NativeSelect value={element.fontFamily} onChange={(event) => onElementChange({ ...element, fontFamily: event.target.value, fontAttachmentId: 0 })}>
                <optgroup label="System fonts">
                  <option value="system">System Sans</option>
                  <option value="Arial">Arial</option>
                  <option value="Georgia">Georgia</option>
                  <option value="Verdana">Verdana</option>
                </optgroup>
                {webfontStatus.fonts.length > 0 && (
                  <optgroup label="Jooosi Fon">
                    {webfontStatus.fonts.map((font) => (
                      <option key={`${font.family}-${font.type}`} value={font.family} disabled={!font.renderable}>
                        {font.title}{font.renderable ? "" : " — server renderer unavailable"}
                      </option>
                    ))}
                  </optgroup>
                )}
              </NativeSelect>
            </Field>
            {!webfontStatus.available && <p className="text-[10px] leading-4 text-muted-foreground">{webfontStatus.reason}</p>}
            <ColorField label="Text color" value={element.color} onChange={(color) => onElementChange({ ...element, color })} />
            <div className="grid grid-cols-2 gap-2">
              <NumberField label="Font size" min={6} max={400} value={element.fontSize} onChange={(fontSize) => onElementChange({ ...element, fontSize })} />
              <NumberField label="Minimum" min={6} max={400} value={element.minFontSize} onChange={(minFontSize) => onElementChange({ ...element, minFontSize })} />
              <NumberField label="Weight" min={100} max={900} step={100} value={element.fontWeight} onChange={(fontWeight) => onElementChange({ ...element, fontWeight })} />
              <NumberField label="Line height" min={0.7} max={3} step={0.05} value={element.lineHeight} onChange={(lineHeight) => onElementChange({ ...element, lineHeight })} />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <SelectField label="Text align" value={element.align} options={[["left", "Left"], ["center", "Center"], ["right", "Right"]]} onChange={(align) => onElementChange({ ...element, align: align as typeof element.align })} />
              <SelectField label="Vertical" value={element.verticalAlign} options={[["top", "Top"], ["middle", "Middle"], ["bottom", "Bottom"]]} onChange={(verticalAlign) => onElementChange({ ...element, verticalAlign: verticalAlign as typeof element.verticalAlign })} />
            </div>
          </InspectorSection>
        )}

        {element.type === "shape" && !["line", "arrow"].includes(element.shape) && (
          <InspectorSection title="Background" defaultOpen>
            <BackgroundEditor value={element.background} compact onChange={(background) => onElementChange({ ...element, background })} />
          </InspectorSection>
        )}

        {element.type === "shape" && (
          <InspectorSection title="Stroke" defaultOpen>
            <ColorField label="Stroke color" value={element.strokeColor} onChange={(strokeColor) => onElementChange({ ...element, strokeColor })} />
            <NumberField label="Stroke width" min={0} max={100} value={element.strokeWidth} onChange={(strokeWidth) => onElementChange({ ...element, strokeWidth })} />
            {element.shape === "rectangle" && <NumberField label="Corner radius" min={0} max={1000} value={element.radius} onChange={(radius) => onElementChange({ ...element, radius })} />}
          </InspectorSection>
        )}

        {element.type === "image" && element.mask === "none" && (
          <InspectorSection title="Corners">
            <NumberField label="Border radius" min={0} max={1000} value={element.radius} onChange={(radius) => onElementChange({ ...element, radius })} />
          </InspectorSection>
        )}

        <InspectorSection title="Design">
          <div className="grid grid-cols-2 gap-2">
            <NumberField label="Opacity" min={0} max={1} step={0.05} value={element.opacity} onChange={(value) => setBase("opacity", value)} />
            <NumberField label="Rotation" min={-360} max={360} value={element.rotation} onChange={(value) => setBase("rotation", value)} />
          </div>
        </InspectorSection>

        <InspectorSection title="Other">
          <div className="grid grid-cols-2 gap-2">
            <Button size="sm" variant="outline" onClick={() => setBase("locked", !element.locked)}>{element.locked ? <IconUnlock /> : <IconLock />}{element.locked ? "Unlock" : "Lock"}</Button>
            <Button size="sm" variant="outline" onClick={onDuplicate}><IconCopy /> Duplicate</Button>
          </div>
          <Button size="sm" variant="destructive" className="w-full" onClick={onDelete}><IconTrash /> Delete element</Button>
        </InspectorSection>
      </div>
      </aside>
      {element.type === "svg" && (
        <IconPickerDialog
          open={iconPickerOpen}
          currentIcon={element.icon}
          onOpenChange={setIconPickerOpen}
          onSearch={onSearchIcons}
          onSelect={(icon) => onElementChange({ ...element, icon })}
        />
      )}
    </>
  );
}

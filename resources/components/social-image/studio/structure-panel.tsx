import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";

import IconBringFront from "~icons/lucide/bring-to-front";
import IconCopy from "~icons/lucide/copy";
import IconEllipsis from "~icons/lucide/ellipsis";
import IconEye from "~icons/lucide/eye";
import IconEyeOff from "~icons/lucide/eye-off";
import IconGripVertical from "~icons/lucide/grip-vertical";
import IconImage from "~icons/lucide/image";
import IconFileCode from "~icons/lucide/file-code-2";
import IconLock from "~icons/lucide/lock";
import IconPlus from "~icons/lucide/plus";
import IconRedo from "~icons/lucide/redo-2";
import IconSendBack from "~icons/lucide/send-to-back";
import IconShapes from "~icons/lucide/shapes";
import IconText from "~icons/lucide/type";
import IconTrash from "~icons/lucide/trash-2";
import IconUndo from "~icons/lucide/undo-2";
import IconUnlock from "~icons/lucide/unlock";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import type { DesignDocument, DesignElement, ElementType, SvgStatus } from "@/types/admin";

type StructurePanelProps = {
  document: DesignDocument;
  selectedId: string | null;
  canUndo: boolean;
  canRedo: boolean;
  svgStatus: SvgStatus;
  onAdd: (type: ElementType) => void;
  onSelect: (id: string | null) => void;
  onElementChange: (element: DesignElement) => void;
  onDuplicate: (element: DesignElement) => void;
  onMove: (element: DesignElement, direction: -1 | 1) => void;
  onReorder: (elements: DesignElement[]) => void;
  onDelete: (element: DesignElement) => void;
  onUndo: () => void;
  onRedo: () => void;
};

function ElementIcon({ type }: { type: ElementType }) {
  if (type === "text") return <IconText />;
  if (type === "svg") return <IconFileCode />;
  if (type === "shape") return <IconShapes />;
  return <IconImage />;
}

function elementLabel(element: DesignElement): string {
  if (element.type === "text") return element.content || "Text";
  if (element.type === "svg") return element.icon || "SVG";
  if (element.type === "shape") return element.shape.charAt(0).toUpperCase() + element.shape.slice(1);
  return element.source || "Image";
}

type SortableElementProps = {
  element: DesignElement;
  selected: boolean;
  onSelect: (id: string) => void;
  onElementChange: (element: DesignElement) => void;
  onDuplicate: (element: DesignElement) => void;
  onMove: (element: DesignElement, direction: -1 | 1) => void;
  onDelete: (element: DesignElement) => void;
};

function SortableElement({ element, selected, onSelect, onElementChange, onDuplicate, onMove, onDelete }: SortableElementProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: element.id });

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        "group flex h-8 items-center gap-1 rounded-md border bg-background px-1 text-xs shadow-[0_1px_1px_rgb(0_0_0/0.02)] transition-colors",
        selected ? "border-foreground/25 bg-muted" : "border-border hover:border-foreground/20",
        element.hidden && "opacity-55",
        isDragging && "relative z-10 opacity-70 shadow-lg",
      )}
    >
      <button
        type="button"
        className="grid size-6 shrink-0 touch-none place-items-center rounded text-muted-foreground/70 outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing"
        aria-label={`Reorder ${elementLabel(element)}`}
        {...attributes}
        {...listeners}
      >
        <IconGripVertical className="size-3.5" />
      </button>
      <button type="button" className="flex min-w-0 flex-1 items-center gap-2 text-left" onClick={() => onSelect(element.id)}>
        <span className="text-muted-foreground"><ElementIcon type={element.type} /></span>
        <span className="min-w-0 flex-1 truncate">{elementLabel(element)}</span>
        {element.locked && <IconLock className="size-3 text-muted-foreground" />}
      </button>
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button size="icon-xs" variant="ghost" className={cn("-mr-0.5 data-[popup-open]:opacity-100", selected ? "opacity-100" : "opacity-0 group-hover:opacity-100")} aria-label={`Actions for ${elementLabel(element)}`} />}>
          <IconEllipsis />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" side="right" className="w-44">
          <DropdownMenuItem onClick={() => onElementChange({ ...element, hidden: !element.hidden })}>
            {element.hidden ? <IconEye /> : <IconEyeOff />} {element.hidden ? "Show" : "Hide"}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => onElementChange({ ...element, locked: !element.locked })}>
            {element.locked ? <IconUnlock /> : <IconLock />} {element.locked ? "Unlock" : "Lock"}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => onDuplicate(element)}><IconCopy /> Duplicate</DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => onMove(element, 1)}><IconBringFront /> Move forward</DropdownMenuItem>
          <DropdownMenuItem onClick={() => onMove(element, -1)}><IconSendBack /> Move backward</DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem variant="destructive" onClick={() => onDelete(element)}><IconTrash /> Delete</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}

export function StructurePanel({
  document,
  selectedId,
  canUndo,
  canRedo,
  svgStatus,
  onAdd,
  onSelect,
  onElementChange,
  onDuplicate,
  onMove,
  onReorder,
  onDelete,
  onUndo,
  onRedo,
}: StructurePanelProps) {
  const displayedElements = [...document.elements].reverse();
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const reorder = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const oldIndex = displayedElements.findIndex((element) => element.id === active.id);
    const newIndex = displayedElements.findIndex((element) => element.id === over.id);
    if (oldIndex < 0 || newIndex < 0) return;
    onReorder(arrayMove(displayedElements, oldIndex, newIndex).reverse());
  };

  return (
    <aside className="social-image-structure-panel" aria-label="Structure">
      <div className="flex h-11 shrink-0 items-center gap-0.5 border-b px-3">
        <h2 className="mr-auto text-xs font-semibold">Structure</h2>
        <DropdownMenu>
          <DropdownMenuTrigger render={<Button size="icon-xs" variant="ghost" aria-label="Add element" />}>
            <IconPlus />
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="w-44">
            <DropdownMenuItem onClick={() => onAdd("text")}><IconText /> Text</DropdownMenuItem>
            <DropdownMenuItem onClick={() => onAdd("image")}><IconImage /> Image</DropdownMenuItem>
            <DropdownMenuItem onClick={() => onAdd("shape")}><IconShapes /> Shape</DropdownMenuItem>
            <DropdownMenuItem disabled={!svgStatus.available} title={svgStatus.available ? (svgStatus.notice || "Add a Jooosi Icon SVG") : svgStatus.reason} onClick={() => onAdd("svg")}>
              <IconFileCode /> SVG
              {!svgStatus.available && <span className="ml-auto text-[9px] text-muted-foreground">Unavailable</span>}
              {svgStatus.limited && <span className="ml-auto text-[9px] text-amber-700">MSVG</span>}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
        <div className="flex items-center gap-0.5">
          <Button size="icon-xs" variant="ghost" title="Undo" disabled={!canUndo} onClick={onUndo}><IconUndo /></Button>
          <Button size="icon-xs" variant="ghost" title="Redo" disabled={!canRedo} onClick={onRedo}><IconRedo /></Button>
        </div>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={reorder}>
        <SortableContext items={displayedElements.map((element) => element.id)} strategy={verticalListSortingStrategy}>
          <div className="min-h-0 flex-1 space-y-1 overflow-y-auto px-2 py-3">
            {displayedElements.map((element) => (
              <SortableElement
                key={element.id}
                element={element}
                selected={selectedId === element.id}
                onSelect={onSelect}
                onElementChange={onElementChange}
                onDuplicate={onDuplicate}
                onMove={onMove}
                onDelete={onDelete}
              />
            ))}
          </div>
        </SortableContext>
      </DndContext>
    </aside>
  );
}

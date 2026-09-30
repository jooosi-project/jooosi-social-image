import IconCopy from "~icons/lucide/copy";
import IconEllipsis from "~icons/lucide/ellipsis";
import IconEye from "~icons/lucide/eye";
import IconInfo from "~icons/lucide/info";
import IconLayoutTemplate from "~icons/lucide/layout-template";
import IconMapPin from "~icons/lucide/map-pin";
import IconRefresh from "~icons/lucide/refresh-cw";
import IconSave from "~icons/lucide/save";
import IconPanelRight from "~icons/lucide/panel-right";
import IconSettings from "~icons/lucide/settings-2";
import IconTrash from "~icons/lucide/trash-2";
import SocialImageLogo from "~/jooosi-social-image.svg?react";

import { Button } from "@/components/ui/button";
import { NativeSelect } from "@/components/ui/native-select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import type { Design, PostOption } from "@/types/admin";

export type StudioWorkspace = "designs" | "design" | "about";

type CommandBarProps = {
  workspace: StudioWorkspace;
  version: string;
  design: Design | null;
  posts: PostOption[];
  previewPostId: number;
  matchingPostsLoading: boolean;
  canManage: boolean;
  settingsLoadState: "loading" | "ready" | "error";
  busy: boolean;
  dirty: boolean;
  onOpenDesigns: () => void;
  onOpenAbout: () => void;
  onTitleChange: (title: string) => void;
  onPreviewPostChange: (postId: number) => void;
  onPreview: () => void;
  onSave: () => void;
  onOpenSettings: () => void;
  onOpenCanvasSettings: () => void;
  onOpenTemplates: () => void;
  onOpenLocations: () => void;
  onDuplicate: () => void;
  onRegenerate: () => void;
  onDelete: () => void;
};

export function CommandBar({
  workspace,
  version,
  design,
  posts,
  previewPostId,
  matchingPostsLoading,
  canManage,
  settingsLoadState,
  busy,
  dirty,
  onOpenDesigns,
  onOpenAbout,
  onTitleChange,
  onPreviewPostChange,
  onPreview,
  onSave,
  onOpenSettings,
  onOpenCanvasSettings,
  onOpenTemplates,
  onOpenLocations,
  onDuplicate,
  onRegenerate,
  onDelete,
}: CommandBarProps) {
  const editorWorkspace = workspace === "design";

  return (
    <header className="social-image-command-bar">
      <div className="flex h-full shrink-0 items-center border-r px-2">
        <button type="button" className="grid size-8 shrink-0 place-items-center rounded-md bg-foreground text-background" title="All designs" aria-label="All designs" onClick={onOpenDesigns}>
          <SocialImageLogo className="size-8 rounded-md text-foreground bg-background" aria-hidden="true" />
        </button>
      </div>

      {design && editorWorkspace && (
        <div className="flex min-w-0 shrink items-center px-3">
          <input
            aria-label="Design title"
            className="h-8 w-56 min-w-0 rounded-md border border-transparent bg-transparent px-2 text-sm font-semibold outline-none hover:border-border focus:border-ring focus:bg-background max-md:w-36"
            value={design.title}
            onChange={(event) => onTitleChange(event.target.value)}
          />
        </div>
      )}

      {!editorWorkspace && (
        <div className="flex h-full items-center gap-2 px-4 pr-2">
          <span className="text-sm font-semibold">Jooosi Social Image</span>
          <span className="rounded-full bg-muted px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground">v{version}</span>
        </div>
      )}

      {!editorWorkspace && (
        <div className="flex h-full items-center gap-1 border-l px-2" role="tablist" aria-label="Social Image pages">
          <Button
            id="social-image-designs-tab"
            role="tab"
            aria-controls="social-image-designs-panel"
            aria-selected={workspace === "designs"}
            size="sm"
            variant={workspace === "designs" ? "secondary" : "ghost"}
            onClick={onOpenDesigns}
          >
            Designs
          </Button>
          <Button
            id="social-image-about-tab"
            role="tab"
            aria-controls="social-image-about-panel"
            aria-selected={workspace === "about"}
            size="sm"
            variant={workspace === "about" ? "secondary" : "ghost"}
            onClick={onOpenAbout}
          >
            About
          </Button>
        </div>
      )}

      <div className="ml-auto flex min-w-0 items-center gap-1.5 px-2">
        {design && editorWorkspace && (
          <>
            <NativeSelect
              aria-label="Choose content to preview"
              containerClassName="w-52 max-md:hidden"
              value={previewPostId}
              disabled={matchingPostsLoading}
              onChange={(event) => onPreviewPostChange(Number(event.target.value))}
            >
              <option value="0">{matchingPostsLoading ? "Finding matching content…" : posts.length > 0 ? "Choose content to preview" : "No matching content"}</option>
              {posts.map((post) => <option key={post.id} value={post.id}>{post.title} · {post.type} · {post.status}</option>)}
            </NativeSelect>
            <Button size="icon-sm" variant="outline" title="Server preview" onClick={onPreview} disabled={busy}>
              <IconEye />
            </Button>
          </>
        )}

        {editorWorkspace && (
          <DropdownMenu>
            <DropdownMenuTrigger render={<Button size="icon-sm" variant="outline" aria-label="More actions" />}>
              <IconEllipsis />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
              <DropdownMenuItem
                onClick={onOpenSettings}
                disabled={settingsLoadState !== "ready"}
                title={settingsLoadState === "loading" ? "Settings are still loading" : settingsLoadState === "error" ? "Reload the page to retry loading settings" : undefined}
              >
                <IconSettings /> Rendering settings{settingsLoadState === "loading" ? " (loading…)" : settingsLoadState === "error" ? " (unavailable)" : ""}
              </DropdownMenuItem>
              <DropdownMenuItem onClick={onOpenAbout}><IconInfo /> About Social Image</DropdownMenuItem>
              {design && (
                <>
                  <DropdownMenuItem onClick={onOpenCanvasSettings}><IconPanelRight /> Canvas settings</DropdownMenuItem>
                  <DropdownMenuItem onClick={onOpenLocations}><IconMapPin /> Locations</DropdownMenuItem>
                  <DropdownMenuItem onClick={onOpenTemplates}><IconLayoutTemplate /> Templates</DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={onDuplicate} disabled={!canManage || busy}><IconCopy /> Duplicate design</DropdownMenuItem>
                  <DropdownMenuItem onClick={onRegenerate} disabled={!canManage || busy}><IconRefresh /> Regenerate matches</DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem variant="destructive" onClick={onDelete} disabled={!canManage || busy}><IconTrash /> Delete design</DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        )}

        {design && editorWorkspace && canManage && (
          <Button
            size="sm"
            className="bg-neutral-900 text-white hover:bg-neutral-700"
            aria-label={dirty ? "Save design — unsaved changes" : "Save design — saved"}
            title={dirty ? "Unsaved changes" : "Saved"}
            onClick={onSave}
            disabled={busy}
          >
            <IconSave /> Save
            <span
              aria-hidden="true"
              className={cn("size-1.5 rounded-full", dirty ? "bg-amber-400" : "bg-emerald-400")}
            />
          </Button>
        )}
      </div>
    </header>
  );
}

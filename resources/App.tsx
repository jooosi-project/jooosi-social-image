import { useCallback, useEffect, useMemo, useState } from "react";
import { createRoot, type Root } from "react-dom/client";
import { toast, Toaster } from "sonner";

import IconX from "~icons/lucide/x";

import { CommandBar, type StudioWorkspace } from "@/components/social-image/studio/command-bar";
import { AboutWorkspace } from "@/components/social-image/studio/about-workspace";
import { CanvasSettingsDialog } from "@/components/social-image/studio/canvas-settings-dialog";
import { DesignWorkspace } from "@/components/social-image/studio/design-workspace";
import { DesignsWorkspace } from "@/components/social-image/studio/designs-workspace";
import { LocationsDialog } from "@/components/social-image/studio/locations-workspace";
import { PresetRepositoriesDialog } from "@/components/social-image/studio/preset-repositories-dialog";
import { SettingsDialog } from "@/components/social-image/studio/settings-dialog";
import { TemplatesDialog } from "@/components/social-image/studio/templates-workspace";
import { Button } from "@/components/ui/button";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AdminApi } from "@/lib/admin-api";
import { BLANK_DOCUMENT, instantiateDocument } from "@/lib/design-presets";
import { isolateWordPressAdminStyles } from "@/lib/wp-admin-style-isolation";
import type {
  Design,
  DesignPreset,
  SocialImageConfig,
  IconSearchResult,
  MatchingPostCatalog,
  PluginSettings,
  PlaceholderCatalog,
  PostOption,
  PresetCatalog,
  PresetRepositorySummary,
  PreviewResult,
  SystemStatus,
} from "@/types/admin";

import "@/styles/app.css";

void isolateWordPressAdminStyles().catch(() => {
  // Keep the studio usable if WordPress admin stylesheet isolation fails.
});

const EMPTY_PRESET_CATALOG: PresetCatalog = {
  schemaVersion: 1,
  presets: [],
  repositories: [],
  schemas: { preset: "", repository: "" },
};

function withoutProcessing(design: Design): Design {
  const clean = structuredClone(design);
  delete clean.processing;
  return clean;
}

function PreviewModal({ result, onClose }: { result: PreviewResult; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[100000] grid place-items-center bg-black/55 p-6" role="dialog" aria-modal="true" aria-label="Server-rendered preview" onMouseDown={onClose}>
      <div className="w-full max-w-4xl overflow-hidden rounded-xl border bg-card shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
        <header className="flex items-center justify-between border-b px-4 py-3">
          <div>
            <p className="text-sm font-semibold">Server-rendered preview</p>
            <p className="mt-0.5 text-xs text-muted-foreground">{result.width} × {result.height}</p>
          </div>
          <Button size="icon-sm" variant="ghost" onClick={onClose}><IconX /></Button>
        </header>
        <div className="bg-muted/50 p-4"><img className="mx-auto max-h-[70vh] w-full object-contain shadow-lg" src={result.url} alt="Generated design preview" /></div>
        {result.warnings.length > 0 && <div className="border-t px-4 py-3 text-xs text-amber-800">{result.warnings.join(" · ")}</div>}
      </div>
    </div>
  );
}

function AdminApp({ config }: { config: SocialImageConfig }) {
  const api = useMemo(() => new AdminApi(config), [config]);
  const [workspace, setWorkspace] = useState<StudioWorkspace>("designs");
  const [designs, setDesigns] = useState<Design[]>([]);
  const [settings, setSettings] = useState<PluginSettings>({ format: "png", quality: 90, replace_featured: false, delete_on_uninstall: false });
  const [status, setStatus] = useState<SystemStatus>({
    version: config.version,
    renderer: { available: false, active_driver: "none", extensions: {}, diagnostics: [] },
    svg: { available: false, jooosi_icon: false, imagick: false, svg_format: false, librsvg: false, limited: false, engine: "none", reason: "Checking SVG support…", notice: "" },
    webfont: { available: false, stylesheet_url: "", reason: "Checking Jooosi Fon…", notice: "", renderable_count: 0, unrenderable_count: 0, fonts: [] },
    fonts: { available: false, families: [], reason: "Checking server fonts…", notice: "" },
    filesystem: { ready: false, uploads_available: false, directory_exists: false, writable: false, reason: "Checking generated-image storage…" },
    cron: { ready: false, disabled: false, alternate: false, last_error: null, reason: "Checking background generation…" },
  });
  const [posts, setPosts] = useState<PostOption[]>([]);
  const [placeholders, setPlaceholders] = useState<PlaceholderCatalog>({ definitions: [], values: {} });
  const [presetCatalog, setPresetCatalog] = useState<PresetCatalog>(EMPTY_PRESET_CATALOG);
  const [current, setCurrent] = useState<Design | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [previewPostId, setPreviewPostId] = useState(0);
  const [preview, setPreview] = useState<PreviewResult | null>(null);
  const [history, setHistory] = useState<Design[]>([]);
  const [future, setFuture] = useState<Design[]>([]);
  const [dirty, setDirty] = useState(false);
  const [busy, setBusy] = useState(false);
  const [matchingPostsLoading, setMatchingPostsLoading] = useState(false);
  const [designsLoading, setDesignsLoading] = useState(true);
  const [settingsLoadState, setSettingsLoadState] = useState<"loading" | "ready" | "error">("loading");
  const [statusLoadState, setStatusLoadState] = useState<"loading" | "ready" | "error">("loading");
  const [presetCatalogLoadState, setPresetCatalogLoadState] = useState<"loading" | "ready" | "error">("loading");
  const [startupError, setStartupError] = useState("");
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [canvasSettingsOpen, setCanvasSettingsOpen] = useState(false);
  const [templatesOpen, setTemplatesOpen] = useState(false);
  const [locationsOpen, setLocationsOpen] = useState(false);
  const [presetRepositoriesOpen, setPresetRepositoriesOpen] = useState(false);

  useEffect(() => {
    let active = true;

    api.request<Design[]>("/designs")
      .then((nextDesigns) => {
        if (active) setDesigns(nextDesigns.map(withoutProcessing));
      })
      .catch((error: unknown) => {
        if (active) setStartupError(error instanceof Error ? error.message : "Social Image could not start.");
      })
      .finally(() => { if (active) setDesignsLoading(false); });

    api.request<PluginSettings>("/settings")
      .then((nextSettings) => {
        if (!active) return;
        setSettings(nextSettings);
        setSettingsLoadState("ready");
      })
      .catch((error: unknown) => {
        if (!active) return;
        setSettingsLoadState("error");
        toast.error(`Rendering settings could not be loaded. Reload the page before saving settings. ${error instanceof Error ? error.message : ""}`.trim());
      });

    api.request<SystemStatus>("/status")
      .then((nextStatus) => {
        if (!active) return;
        setStatus(nextStatus);
        setStatusLoadState("ready");
      })
      .catch((error: unknown) => {
        if (!active) return;
        setStatusLoadState("error");
        toast.error(`System status could not be loaded. ${error instanceof Error ? error.message : ""}`.trim());
      });

    api.request<PresetCatalog>("/presets")
      .then((nextPresetCatalog) => {
        if (!active) return;
        setPresetCatalog(nextPresetCatalog);
        setPresetCatalogLoadState("ready");
      })
      .catch((error: unknown) => {
        if (!active) return;
        setPresetCatalogLoadState("error");
        toast.error(`Templates could not be loaded. ${error instanceof Error ? error.message : "Reload the page to retry."}`);
      });

    return () => { active = false; };
  }, [api]);

  useEffect(() => {
    let active = true;

    if (!current) {
      setPosts([]);
      setMatchingPostsLoading(false);
      return () => { active = false; };
    }

    setMatchingPostsLoading(true);
    api.request<MatchingPostCatalog>("/matching-posts", {
      method: "POST",
      body: JSON.stringify({ rules: current.rules }),
    })
      .then((catalog) => {
        if (!active) return;
        setPosts(catalog.items);
        setPreviewPostId((postId) => catalog.items.some((post) => post.id === postId) ? postId : 0);
      })
      .catch(() => {
        if (!active) return;
        setPosts([]);
        setPreviewPostId(0);
      })
      .finally(() => { if (active) setMatchingPostsLoading(false); });

    return () => { active = false; };
  }, [api, current?.id, current?.rules]);

  useEffect(() => {
    let active = true;
    api.request<PlaceholderCatalog>(`/placeholders?post_id=${previewPostId}`)
      .then((catalog) => { if (active) setPlaceholders(catalog); })
      .catch(() => { /* Keep the last usable catalogue if preview data cannot be loaded. */ });
    return () => { active = false; };
  }, [api, previewPostId]);

  const snapshot = useCallback(() => {
    if (!current) return;
    setHistory((items) => [...items, structuredClone(current)].slice(-50));
    setFuture([]);
  }, [current]);

  const searchIcons = useCallback(async (query: string): Promise<IconSearchResult[]> => {
    const response = await api.request<{ results: IconSearchResult[] }>(`/icons/search?query=${encodeURIComponent(query)}`);

    return response.results;
  }, [api]);

  const changeDesign = useCallback((next: Design, recordHistory = true) => {
    if (recordHistory && current) {
      setHistory((items) => [...items, structuredClone(current)].slice(-50));
      setFuture([]);
    }
    setCurrent(next);
    setDirty(true);
  }, [current]);

  const undo = useCallback(() => {
    if (!current || history.length === 0) return;
    const previous = history.at(-1)!;
    setHistory((items) => items.slice(0, -1));
    setFuture((items) => [structuredClone(current), ...items].slice(0, 50));
    setCurrent(structuredClone(previous));
    setDirty(true);
  }, [current, history]);

  const redo = useCallback(() => {
    if (!current || future.length === 0) return;
    const next = future[0];
    setFuture((items) => items.slice(1));
    setHistory((items) => [...items, structuredClone(current)].slice(-50));
    setCurrent(structuredClone(next));
    setDirty(true);
  }, [current, future]);

  const openDesign = (design: Design) => {
    if (current && current.id !== design.id && dirty && !window.confirm("Discard unsaved design changes?")) return;
    const next = withoutProcessing(design);
    setCurrent(structuredClone(next));
    setSelectedId(next.document.elements.at(-1)?.id || null);
    setPreviewPostId(0);
    setHistory([]);
    setFuture([]);
    setDirty(false);
    setWorkspace("design");
    window.scrollTo({ top: 0 });
  };

  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    try {
      await work();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : "The request failed.");
    } finally {
      setBusy(false);
    }
  };

  const startNewDesign = () => {
    if (current && dirty && !window.confirm("Discard unsaved design changes and start a new design?")) return;
    setCurrent(null);
    setSelectedId(null);
    setHistory([]);
    setFuture([]);
    setDirty(false);
    setWorkspace("designs");
    setTemplatesOpen(true);
  };

  const createDesign = (document = BLANK_DOCUMENT, title = "Untitled design") => run(async () => {
    const seededDocument = instantiateDocument(document);
    const design = withoutProcessing(await api.request<Design>("/designs", { method: "POST", body: JSON.stringify({ title, document: seededDocument }) }));
    setDesigns((items) => [...items, design]);
    openDesign(design);
  });

  const usePreset = (preset: DesignPreset) => {
    if (!current) {
      setTemplatesOpen(false);
      void createDesign(preset.document, preset.title);
      return;
    }
    if (!window.confirm(`Replace the current layout with the “${preset.title}” template?`)) return;
    setTemplatesOpen(false);
    const document = instantiateDocument(preset.document);
    changeDesign({ ...current, document });
    setSelectedId(document.elements.at(-1)?.id || null);
    setWorkspace("design");
    toast.success(`“${preset.title}” applied. Save the design to keep it.`);
  };

  const useBlankDesign = () => {
    if (!current) {
      setTemplatesOpen(false);
      void createDesign(BLANK_DOCUMENT);
      return;
    }
    if (!window.confirm("Replace the current layout with a blank canvas?")) return;
    setTemplatesOpen(false);
    const document = instantiateDocument(BLANK_DOCUMENT);
    changeDesign({ ...current, document });
    setSelectedId(null);
    setWorkspace("design");
    toast.success("Blank canvas applied. Save the design to keep it.");
  };

  const duplicateDesign = (design: Design) => run(async () => {
    const copy = withoutProcessing(await api.request<Design>(`/designs/${design.id}/duplicate`, { method: "POST", body: "{}" }));
    setDesigns((items) => [...items, copy]);
    toast.success("Design duplicated.");
  });

  const regenerateDesign = (design: Design) => run(async () => {
    const result = await api.request<{ scheduled: number }>("/cache/warm", { method: "POST", body: JSON.stringify({ design_id: design.id }) });
    toast.success(`${result.scheduled} matching post(s) queued.`);
  });

  const deleteDesign = (design: Design) => {
    if (!window.confirm(`Delete “${design.title}” and its generated images?`)) return;
    void run(async () => {
      await api.request(`/designs/${design.id}`, { method: "DELETE" });
      setDesigns((items) => items.filter((item) => item.id !== design.id));
      if (current?.id === design.id) {
        setCurrent(null);
        setSelectedId(null);
        setDirty(false);
        setWorkspace("designs");
      }
      toast.success("Design and generated images deleted.");
    });
  };

  const saveDesign = () => {
    if (!current || !config.canManage) return;
    void run(async () => {
      const result = await api.request<Design>(`/designs/${current.id}`, {
        method: "PUT",
        body: JSON.stringify({ title: current.title, status: current.status, document: current.document, rules: current.rules }),
      });
      const saved = withoutProcessing(result);
      setCurrent(saved);
      setDesigns((items) => items.map((item) => item.id === saved.id ? structuredClone(saved) : item));
      setDirty(false);
      setHistory([]);
      setFuture([]);
      toast.success(`Design saved. ${result.processing?.scheduled || 0} post(s) queued for regeneration.`);
    });
  };

  const updateDesignStatus = (design: Design, designStatus: Design["status"]) => {
    if (!config.canManage || design.status === designStatus) return;

    void run(async () => {
      const result = await api.request<Design>(`/designs/${design.id}`, {
        method: "PUT",
        body: JSON.stringify({ status: designStatus }),
      });
      const saved = withoutProcessing(result);
      setDesigns((items) => items.map((item) => item.id === saved.id ? structuredClone(saved) : item));
      setCurrent((item) => item?.id === saved.id ? {
        ...item,
        status: saved.status,
        revision: saved.revision,
        modified: saved.modified,
      } : item);
      toast.success(`Design status changed to ${saved.status === "publish" ? "Published" : "Draft"}.`);
    });
  };

  const showServerPreview = () => {
    if (!current) return;
    void run(async () => {
      const result = await api.request<PreviewResult>(`/designs/${current.id}/preview`, {
        method: "POST",
        body: JSON.stringify({ document: current.document, post_id: previewPostId }),
      });
      setPreview(result);
    });
  };

  const saveSettings = () => run(async () => {
    const result = await api.request<PluginSettings>("/settings", { method: "PUT", body: JSON.stringify(settings) });
    setSettings(result);
    setSettingsOpen(false);
    toast.success("Settings saved.");
  });

  const applyPresetCatalog = (catalog: PresetCatalog) => {
    setPresetCatalog(catalog);
    setPresetCatalogLoadState("ready");
  };

  const addPresetRepository = (url: string) => run(async () => {
    const catalog = await api.request<PresetCatalog>("/preset-repositories", { method: "POST", body: JSON.stringify({ url }) });
    applyPresetCatalog(catalog);
    toast.success("Template repository added.");
  });

  const togglePresetRepository = (repository: PresetRepositorySummary, enabled: boolean) => run(async () => {
    const catalog = await api.request<PresetCatalog>(`/preset-repositories/${repository.id}`, { method: "PUT", body: JSON.stringify({ enabled }) });
    applyPresetCatalog(catalog);
    toast.success(`${repository.title} ${enabled ? "enabled" : "disabled"}.`);
  });

  const refreshPresetRepository = (repository: PresetRepositorySummary) => run(async () => {
    try {
      const catalog = await api.request<PresetCatalog>(`/preset-repositories/${repository.id}/refresh`, { method: "POST", body: "{}" });
      applyPresetCatalog(catalog);
      toast.success(`${repository.title} refreshed.`);
    } catch (error) {
      try {
        applyPresetCatalog(await api.request<PresetCatalog>("/presets"));
      } catch {
        // Preserve the original refresh error when the follow-up catalog read also fails.
      }
      throw error;
    }
  });

  const removePresetRepository = (repository: PresetRepositorySummary) => {
    if (!window.confirm(`Delete the “${repository.title}” template repository and its cached manifest?`)) return;
    void run(async () => {
      const catalog = await api.request<PresetCatalog>(`/preset-repositories/${repository.id}`, { method: "DELETE" });
      applyPresetCatalog(catalog);
      toast.success("Template repository deleted.");
    });
  };

  const warmAll = () => run(async () => {
    const result = await api.request<{ scheduled: number }>("/cache/warm", { method: "POST", body: "{}" });
    toast.success(`${result.scheduled} post(s) queued.`);
  });

  const flushAll = () => {
    if (!window.confirm("Delete all generated Social Image cache files?")) return;
    void run(async () => {
      const result = await api.request<{ deleted: number }>("/cache/flush", { method: "POST", body: "{}" });
      toast.success(`${result.deleted} generated file(s) deleted.`);
    });
  };

  if (startupError) {
    return (
      <div className="grid min-h-[calc(100vh-32px)] place-items-center bg-background p-6">
        <div className="max-w-md rounded-xl border bg-card p-6 shadow-sm"><h1 className="font-semibold">Social Image could not start</h1><p className="mt-2 text-sm text-muted-foreground">{startupError}</p></div>
      </div>
    );
  }

  return (
    <div className="social-image-studio">
      <CommandBar
        workspace={workspace}
        version={config.version}
        design={current}
        posts={posts}
        previewPostId={previewPostId}
        matchingPostsLoading={matchingPostsLoading}
        canManage={config.canManage}
        settingsLoadState={settingsLoadState}
        busy={busy}
        dirty={dirty}
        onOpenDesigns={() => setWorkspace("designs")}
        onOpenAbout={() => setWorkspace("about")}
        onTitleChange={(title) => current && changeDesign({ ...current, title })}
        onPreviewPostChange={setPreviewPostId}
        onPreview={showServerPreview}
        onSave={saveDesign}
        onOpenSettings={() => setSettingsOpen(true)}
        onOpenCanvasSettings={() => {
          if (!current) return;
          setWorkspace("design");
          setCanvasSettingsOpen(true);
        }}
        onOpenTemplates={() => setTemplatesOpen(true)}
        onOpenLocations={() => current && setLocationsOpen(true)}
        onDuplicate={() => current && void duplicateDesign(current)}
        onRegenerate={() => current && void regenerateDesign(current)}
        onDelete={() => current && deleteDesign(current)}
      />

      <div className="min-h-0 flex-1">
        {workspace === "designs" && (
          <div id="social-image-designs-panel" role="tabpanel" aria-labelledby="social-image-designs-tab" className="h-full">
            <DesignsWorkspace
              designs={designs}
              loading={designsLoading}
              canManage={config.canManage}
              busy={busy}
              onStartNew={startNewDesign}
              onOpen={openDesign}
              onStatusChange={updateDesignStatus}
              onDuplicate={(design) => { void duplicateDesign(design); }}
              onRegenerate={(design) => { void regenerateDesign(design); }}
              onDelete={deleteDesign}
            />
          </div>
        )}
        {workspace === "about" && (
          <div id="social-image-about-panel" role="tabpanel" aria-labelledby="social-image-about-tab" className="h-full overflow-auto">
            <AboutWorkspace version={config.version} />
          </div>
        )}
        {workspace === "design" && current && (
          <DesignWorkspace
            design={current}
            selectedId={selectedId}
            posts={posts}
            previewPostId={previewPostId}
            canUndo={history.length > 0}
            canRedo={future.length > 0}
            svgStatus={status.svg}
            webfontStatus={status.webfont}
            placeholders={placeholders.definitions}
            placeholderValues={placeholders.values}
            onSearchIcons={searchIcons}
            onDesignChange={changeDesign}
            onSnapshot={snapshot}
            onSelect={setSelectedId}
            onUndo={undo}
            onRedo={redo}
            onSave={saveDesign}
          />
        )}
      </div>

      <TemplatesDialog
        open={templatesOpen}
        hasDesign={current !== null}
        loadState={presetCatalogLoadState}
        canManage={config.canManage}
        busy={busy}
        presets={presetCatalog.presets}
        repositories={presetCatalog.repositories}
        onOpenChange={setTemplatesOpen}
        onUse={usePreset}
        onBlank={useBlankDesign}
        onManageRepositories={() => {
          setTemplatesOpen(false);
          setPresetRepositoriesOpen(true);
        }}
      />
      <PresetRepositoriesDialog
        open={presetRepositoriesOpen}
        catalog={presetCatalog}
        canManage={config.canManage}
        busy={busy}
        onOpenChange={(open) => {
          setPresetRepositoriesOpen(open);
          if (!open) setTemplatesOpen(true);
        }}
        onAdd={(url) => { void addPresetRepository(url); }}
        onToggle={(repository, enabled) => { void togglePresetRepository(repository, enabled); }}
        onRefresh={(repository) => { void refreshPresetRepository(repository); }}
        onRemove={removePresetRepository}
      />
      {current && (
        <CanvasSettingsDialog
          open={canvasSettingsOpen}
          document={current.document}
          onOpenChange={setCanvasSettingsOpen}
          onApply={(document) => changeDesign({ ...current, document })}
        />
      )}
      {current && (
        <LocationsDialog
          open={locationsOpen}
          rules={current.rules}
          status={current.status}
          postTypes={config.postTypes}
          onOpenChange={setLocationsOpen}
          onChange={(rules) => changeDesign({ ...current, rules })}
          onStatusChange={(designStatus) => changeDesign({ ...current, status: designStatus })}
        />
      )}

      <SettingsDialog
        open={settingsOpen}
        settings={settings}
        status={status}
        statusLoadState={statusLoadState}
        canManage={config.canManage}
        busy={busy}
        onOpenChange={setSettingsOpen}
        onChange={setSettings}
        onSave={() => { void saveSettings(); }}
        onWarm={() => { void warmAll(); }}
        onFlush={flushAll}
      />
      {preview && <PreviewModal result={preview} onClose={() => setPreview(null)} />}
    </div>
  );
}

type SocialImageRootElement = HTMLElement & { socialImageReactRoot?: Root };

const root = document.getElementById("social-image-admin") as SocialImageRootElement | null;
const config = window.SocialImageConfig;

if (root && config) {
  const reactRoot = root.socialImageReactRoot ?? createRoot(root);
  root.socialImageReactRoot = reactRoot;
  reactRoot.render(
    <TooltipProvider>
      <div className="social-image-ui">
        <AdminApp config={config} />
        <Toaster richColors position="bottom-right" />
      </div>
    </TooltipProvider>,
  );
}

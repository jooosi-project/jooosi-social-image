export type OutputType = "og" | "twitter" | "featured";
export type ElementType = "text" | "image" | "svg" | "shape";
export type ShapeKind = "rectangle" | "ellipse" | "triangle" | "diamond" | "hexagon" | "star" | "line" | "arrow";
export type ImageMask = "none" | "ellipse" | "triangle" | "diamond" | "hexagon";

export type GradientStop = {
  color: string;
  position: number;
};

export type Background = {
  type: "solid" | "gradient";
  color: string;
  stops: GradientStop[];
  direction: "horizontal" | "vertical";
};

export type BaseElement = {
  id: string;
  type: ElementType;
  x: number;
  y: number;
  width: number;
  height: number;
  opacity: number;
  rotation: number;
  locked: boolean;
  hidden: boolean;
};

export type TextElement = BaseElement & {
  type: "text";
  content: string;
  fontSize: number;
  minFontSize: number;
  fontFamily: string;
  fontAttachmentId: number;
  fontWeight: number;
  color: string;
  align: "left" | "center" | "right";
  verticalAlign: "top" | "middle" | "bottom";
  lineHeight: number;
};

export type ImageElement = BaseElement & {
  type: "image";
  attachmentId: number;
  source: string;
  previewUrl: string;
  fit: "cover" | "contain" | "fill" | "none";
  radius: number;
  mask: ImageMask;
};

export type SvgElement = BaseElement & {
  type: "svg";
  icon: string;
  color: string;
};

export type ShapeElement = BaseElement & {
  type: "shape";
  shape: ShapeKind;
  background: Background;
  strokeColor: string;
  strokeWidth: number;
  radius: number;
};

export type DesignElement = TextElement | ImageElement | SvgElement | ShapeElement;

export type MatchingPostCatalog = {
  items: PostOption[];
  scanned: number;
  truncated: boolean;
};

export type DesignDocument = {
  version: number;
  width: number;
  height: number;
  background: Background;
  elements: DesignElement[];
};

export type AssignmentRules = {
  outputs: OutputType[];
  query: AssignmentGroup;
  priority: number;
  replaceFeatured: boolean;
};

export type AssignmentConditionField =
  | "post_type"
  | "post_status"
  | "post_id"
  | "author"
  | "parent"
  | "page_template"
  | "taxonomy"
  | "meta"
  | "post_title"
  | "post_slug"
  | "post_excerpt"
  | "date";

export type AssignmentCondition = {
  id: string;
  type: "condition";
  field: AssignmentConditionField;
  operator: string;
  key: string;
  values: string[];
};

export type AssignmentGroup = {
  id: string;
  type: "group";
  relation: "and" | "or";
  children: AssignmentQueryNode[];
};

export type AssignmentQueryNode = AssignmentCondition | AssignmentGroup;

export type PlaceholderDefinition = {
  key: string;
  label: string;
  group: string;
  type: "text" | "image" | "url" | "number" | "date";
  description: string;
};

export type PlaceholderCatalog = {
  definitions: PlaceholderDefinition[];
  values: Record<string, unknown>;
};

export type Design = {
  id: number;
  title: string;
  status: "publish" | "draft";
  document: DesignDocument;
  rules: AssignmentRules;
  revision: number;
  modified: string;
  processing?: ProcessingResult;
};

export type DesignPreset = {
  schemaVersion: 1;
  key: string;
  id: string;
  title: string;
  description: string;
  category: string;
  tags?: string[];
  document: DesignDocument;
  source: {
    repositoryId: string;
    repositoryTitle: string;
    repositoryUrl: string;
    bundled: boolean;
  };
};

export type PresetRepositorySummary = {
  id: string;
  version: string;
  title: string;
  description: string;
  homepage: string;
  url: string;
  bundled: boolean;
  enabled: boolean;
  presetCount: number;
  updatedAt: string;
  syncedAt: string;
  status: "ready" | "stale" | "error";
  error: string;
  capabilities: {
    toggle: boolean;
    refresh: boolean;
    delete: boolean;
  };
};

export type PresetCatalog = {
  schemaVersion: 1;
  presets: DesignPreset[];
  repositories: PresetRepositorySummary[];
  schemas: {
    preset: string;
    repository: string;
  };
};

export type ProcessingResult = {
  posts_scanned: number;
  posts_invalidated: number;
  attachments_deleted: number;
  files_deleted: number;
  scheduled: number;
};

export type PluginSettings = {
  format: "png" | "jpeg" | "webp";
  quality: number;
  replace_featured: boolean;
  delete_on_uninstall: boolean;
};

export type RendererStatus = {
  available: boolean;
  active_driver: string;
  extensions: Record<string, boolean>;
  diagnostics: string[];
};

export type SvgStatus = {
  available: boolean;
  jooosi_icon: boolean;
  imagick: boolean;
  svg_format: boolean;
  librsvg: boolean;
  limited: boolean;
  engine: "librsvg" | "msvg" | "none";
  reason: string;
  notice: string;
};

export type WebfontOption = {
  title: string;
  family: string;
  type: string;
  variants: string[];
  renderable: boolean;
  reason: string;
};

export type WebfontStatus = {
  available: boolean;
  stylesheet_url: string;
  reason: string;
  notice: string;
  renderable_count: number;
  unrenderable_count: number;
  fonts: WebfontOption[];
};

export type FontStatus = {
  available: boolean;
  families: Array<{ family: string; regular: boolean; bold: boolean }>;
  reason: string;
  notice: string;
};

export type FilesystemStatus = {
  ready: boolean;
  uploads_available: boolean;
  directory_exists: boolean;
  writable: boolean;
  reason: string;
};

export type CronStatus = {
  ready: boolean;
  disabled: boolean;
  alternate: boolean;
  last_error: { code: string; message: string; time: number } | null;
  reason: string;
};

export type SystemStatus = {
  version: string;
  renderer: RendererStatus;
  svg: SvgStatus;
  webfont: WebfontStatus;
  fonts: FontStatus;
  filesystem: FilesystemStatus;
  cron: CronStatus;
};

export type IconSearchResult = {
  name: string;
  prefix: string;
  iconName: string;
};

export type PostOption = {
  id: number;
  title: string;
  type: string;
  status: string;
};

export type PostTypeOption = {
  value: string;
  label: string;
};

export type SocialImageConfig = {
  restUrl: string;
  nonce: string;
  adminUrl: string;
  pluginUrl: string;
  version: string;
  postTypes: PostTypeOption[];
  canManage: boolean;
};

export type PreviewResult = {
  url: string;
  path: string;
  width: number;
  height: number;
  warnings: string[];
};

type MediaSelection = {
  first: () => { toJSON: () => { id?: number; url?: string } };
};

type MediaFrame = {
  on: (event: "select", callback: () => void) => void;
  state: () => { get: (key: "selection") => MediaSelection };
  open: () => void;
};

declare global {
  interface Window {
    SocialImageConfig?: SocialImageConfig;
    wp?: {
      media: (options: Record<string, unknown>) => MediaFrame;
    };
  }
}

import { useMemo, useState } from "react";

import IconGitBranch from "~icons/lucide/git-branch-plus";
import IconPlus from "~icons/lucide/plus";
import IconTrash from "~icons/lucide/trash-2";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { NativeSelect } from "@/components/ui/native-select";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import type {
  AssignmentCondition,
  AssignmentConditionField,
  AssignmentGroup,
  AssignmentQueryNode,
  AssignmentRules,
  PostTypeOption,
} from "@/types/admin";

type DesignStatus = "publish" | "draft";
type Relation = "and" | "or";

type LocationsDialogProps = {
  open: boolean;
  rules: AssignmentRules;
  status: DesignStatus;
  postTypes: PostTypeOption[];
  onOpenChange: (open: boolean) => void;
  onChange: (rules: AssignmentRules) => void;
  onStatusChange: (status: DesignStatus) => void;
};

type FieldDefinition = {
  label: string;
  operators: Array<[string, string]>;
  keyLabel?: string;
  keyPlaceholder?: string;
};

const FIELDS: Record<AssignmentConditionField, FieldDefinition> = {
  post_type: { label: "Post type", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  post_status: { label: "Post status", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  post_id: { label: "Post ID", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  author: { label: "Author ID", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  parent: { label: "Parent post ID", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  page_template: { label: "Page template", operators: [["in", "is any of"], ["not_in", "is not any of"]] },
  taxonomy: {
    label: "Taxonomy term",
    operators: [["has_any", "has any"], ["has_all", "has all"], ["has_none", "has none"]],
    keyLabel: "Taxonomy",
    keyPlaceholder: "category",
  },
  meta: {
    label: "Custom field",
    operators: [
      ["exists", "exists"], ["not_exists", "does not exist"], ["equals", "equals"], ["not_equals", "does not equal"],
      ["contains", "contains"], ["not_contains", "does not contain"], ["in", "is any of"], ["not_in", "is not any of"],
      ["greater", "is greater than"], ["greater_or_equal", "is at least"], ["less", "is less than"], ["less_or_equal", "is at most"],
    ],
    keyLabel: "Meta key",
    keyPlaceholder: "event_date",
  },
  post_title: { label: "Post title", operators: [["equals", "equals"], ["not_equals", "does not equal"], ["contains", "contains"], ["not_contains", "does not contain"], ["starts_with", "starts with"], ["ends_with", "ends with"]] },
  post_slug: { label: "Post slug", operators: [["equals", "equals"], ["not_equals", "does not equal"], ["contains", "contains"], ["not_contains", "does not contain"], ["starts_with", "starts with"], ["ends_with", "ends with"]] },
  post_excerpt: { label: "Post excerpt", operators: [["equals", "equals"], ["not_equals", "does not equal"], ["contains", "contains"], ["not_contains", "does not contain"], ["starts_with", "starts with"], ["ends_with", "ends with"]] },
  date: { label: "Content date", operators: [["before", "is before"], ["after", "is after"], ["on", "is on"], ["between", "is between"]], keyLabel: "Date field" },
};

const FIELD_OPTIONS = Object.entries(FIELDS) as Array<[AssignmentConditionField, FieldDefinition]>;

function nextId(prefix: string): string {
  return `${prefix}-${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`}`;
}

function nextCondition(field: AssignmentConditionField, postTypes: PostTypeOption[]): AssignmentCondition {
  const definition = FIELDS[field];
  let values = [""];
  let key = "";

  if (field === "post_type") values = [postTypes[0]?.value || "post"];
  if (field === "post_status") values = ["publish"];
  if (field === "meta") key = "custom_field";
  if (field === "taxonomy") key = "category";
  if (field === "date") {
    key = "published";
    values = [new Date().toISOString().slice(0, 10)];
  }

  return {
    id: nextId("condition"),
    type: "condition",
    field,
    operator: definition.operators[0][0],
    key,
    values,
  };
}

function nextGroup(postTypes: PostTypeOption[]): AssignmentGroup {
  return {
    id: nextId("group"),
    type: "group",
    relation: "and",
    children: [nextCondition("post_type", postTypes)],
  };
}

function csvValues(value: string): string[] {
  return value.split(",").map((item) => item.trim()).filter(Boolean);
}

function ConditionValues({ condition, postTypes, onChange }: { condition: AssignmentCondition; postTypes: PostTypeOption[]; onChange: (condition: AssignmentCondition) => void }) {
  const hasNoValue = condition.field === "meta" && ["exists", "not_exists"].includes(condition.operator);

  if (hasNoValue) return <p className="text-[11px] text-muted-foreground">No comparison value is needed.</p>;

  if (condition.field === "post_type") {
    return (
      <div className="flex flex-wrap gap-x-4 gap-y-2 rounded-md border bg-background px-3 py-2">
        {postTypes.map((postType) => (
          <Checkbox
            key={postType.value}
            label={postType.label}
            checked={condition.values.includes(postType.value)}
            onChange={(event) => {
              const values = event.target.checked
                ? [...new Set([...condition.values, postType.value])]
                : condition.values.filter((value) => value !== postType.value);
              if (values.length > 0) onChange({ ...condition, values });
            }}
          />
        ))}
      </div>
    );
  }

  if (condition.field === "date") {
    const updateDate = (index: number, value: string) => {
      const values = [...condition.values];
      values[index] = value;
      onChange({ ...condition, values: values.filter((item, valueIndex) => item || valueIndex <= index) });
    };
    return (
      <div className="grid gap-2 sm:grid-cols-2">
        <Input type="date" value={condition.values[0] || ""} onChange={(event) => updateDate(0, event.target.value)} />
        {condition.operator === "between" && <Input type="date" value={condition.values[1] || ""} onChange={(event) => updateDate(1, event.target.value)} />}
      </div>
    );
  }

  const isText = ["post_title", "post_slug", "post_excerpt"].includes(condition.field)
    || (condition.field === "meta" && !["in", "not_in"].includes(condition.operator));

  return (
    <Input
      value={isText ? (condition.values[0] || "") : condition.values.join(", ")}
      placeholder={condition.field === "taxonomy" ? "Term IDs, slugs, or names" : condition.field === "page_template" ? "default, templates/landing.php" : "Comma-separated values"}
      onChange={(event) => onChange({ ...condition, values: isText ? [event.target.value] : csvValues(event.target.value) })}
    />
  );
}

function Connector({ relation }: { relation: Relation | "where" }) {
  return <span className="absolute -top-2 left-3 rounded bg-muted px-1.5 text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">{relation}</span>;
}

function ConditionCard({ condition, postTypes, connector, onChange, onRemove }: {
  condition: AssignmentCondition;
  postTypes: PostTypeOption[];
  connector: Relation | "where";
  onChange: (condition: AssignmentCondition) => void;
  onRemove: () => void;
}) {
  const definition = FIELDS[condition.field];

  return (
    <div className="relative rounded-lg border bg-card p-3 shadow-xs">
      <Connector relation={connector} />
      <div className="grid gap-2 sm:grid-cols-[1.15fr_1fr_auto]">
        <NativeSelect
          aria-label="Condition field"
          value={condition.field}
          onChange={(event) => onChange({ ...nextCondition(event.target.value as AssignmentConditionField, postTypes), id: condition.id })}
        >
          {FIELD_OPTIONS.map(([field, item]) => <option key={field} value={field}>{item.label}</option>)}
        </NativeSelect>
        <NativeSelect
          aria-label="Condition operator"
          value={condition.operator}
          onChange={(event) => onChange({ ...condition, operator: event.target.value, values: ["exists", "not_exists"].includes(event.target.value) ? [] : condition.values.length ? condition.values : [""] })}
        >
          {definition.operators.map(([operator, label]) => <option key={operator} value={operator}>{label}</option>)}
        </NativeSelect>
        <Button size="icon-sm" variant="ghost" className="text-destructive" aria-label="Remove condition" onClick={onRemove}><IconTrash /></Button>
      </div>

      <div className={cn("mt-2 grid gap-2", definition.keyLabel && "sm:grid-cols-[0.65fr_1.35fr]") }>
        {condition.field === "date" ? (
          <NativeSelect aria-label="Date field" value={condition.key || "published"} onChange={(event) => onChange({ ...condition, key: event.target.value })}>
            <option value="published">Published date</option>
            <option value="modified">Modified date</option>
          </NativeSelect>
        ) : definition.keyLabel ? (
          <Input aria-label={definition.keyLabel} value={condition.key} placeholder={definition.keyPlaceholder} onChange={(event) => onChange({ ...condition, key: event.target.value })} />
        ) : null}
        <ConditionValues condition={condition} postTypes={postTypes} onChange={onChange} />
      </div>
    </div>
  );
}

function QueryGroupEditor({ group, postTypes, depth = 0, connector = "where", root = false, onChange, onRemove }: {
  group: AssignmentGroup;
  postTypes: PostTypeOption[];
  depth?: number;
  connector?: Relation | "where";
  root?: boolean;
  onChange: (group: AssignmentGroup) => void;
  onRemove?: () => void;
}) {
  const [newField, setNewField] = useState<AssignmentConditionField>("post_type");
  const updateChild = (index: number, child: AssignmentQueryNode) => {
    onChange({ ...group, children: group.children.map((item, itemIndex) => itemIndex === index ? child : item) });
  };
  const removeChild = (index: number) => onChange({ ...group, children: group.children.filter((_, itemIndex) => itemIndex !== index) });

  return (
    <div className={cn("relative", !root && "rounded-lg border border-dashed border-foreground/20 bg-muted/25 p-3")}>
      {!root && <Connector relation={connector} />}
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">{root ? "Root group" : `Nested group ${depth}`}</span>
        <span className="ml-auto text-[11px] text-muted-foreground">Match</span>
        <NativeSelect className="w-32" value={group.relation} onChange={(event) => onChange({ ...group, relation: event.target.value as Relation })}>
          <option value="and">All in group</option>
          <option value="or">Any in group</option>
        </NativeSelect>
        {!root && <Button size="icon-sm" variant="ghost" className="text-destructive" aria-label="Remove group" onClick={onRemove}><IconTrash /></Button>}
      </div>

      <div className="mt-4 grid gap-3">
        {group.children.map((node, index) => {
          const nodeConnector = index === 0 ? "where" : group.relation;
          return node.type === "group" ? (
            <QueryGroupEditor
              key={node.id}
              group={node}
              postTypes={postTypes}
              depth={depth + 1}
              connector={nodeConnector}
              onChange={(child) => updateChild(index, child)}
              onRemove={() => removeChild(index)}
            />
          ) : (
            <ConditionCard
              key={node.id}
              condition={node}
              postTypes={postTypes}
              connector={nodeConnector}
              onChange={(child) => updateChild(index, child)}
              onRemove={() => removeChild(index)}
            />
          );
        })}
      </div>

      <div className="mt-3 flex flex-wrap gap-2">
        <NativeSelect containerClassName="min-w-48 flex-1" value={newField} onChange={(event) => setNewField(event.target.value as AssignmentConditionField)}>
          {FIELD_OPTIONS.map(([field, definition]) => <option key={field} value={field}>{definition.label}</option>)}
        </NativeSelect>
        <Button variant="secondary" size="sm" onClick={() => onChange({ ...group, children: [...group.children, nextCondition(newField, postTypes)] })}>
          <IconPlus /> Add rule
        </Button>
        <Button variant="outline" size="sm" disabled={depth >= 3} title={depth >= 3 ? "Groups can be nested up to three levels" : undefined} onClick={() => onChange({ ...group, children: [...group.children, nextGroup(postTypes)] })}>
          <IconGitBranch /> Add group
        </Button>
      </div>

      {group.children.length === 0 && <p className="mt-3 rounded-md border border-dashed p-3 text-[11px] text-muted-foreground">{root ? "No rules means this published design can match every content item." : "Empty nested groups are ignored when the design is saved."}</p>}
    </div>
  );
}

export function LocationsDialog({ open, rules, status, postTypes, onOpenChange, onChange, onStatusChange }: LocationsDialogProps) {
  const social = rules.outputs.includes("og") || rules.outputs.includes("twitter");
  const featured = rules.outputs.includes("featured");
  const outputSummary = useMemo(() => [social ? "Social image" : "", featured ? "Featured image" : ""].filter(Boolean).join(" + "), [social, featured]);

  const setOutput = (target: "social" | "featured", enabled: boolean) => {
    const nextSocial = target === "social" ? enabled : social;
    const nextFeatured = target === "featured" ? enabled : featured;
    if (!nextSocial && !nextFeatured) return;
    onChange({
      ...rules,
      outputs: [...(nextSocial ? ["og", "twitter"] as const : []), ...(nextFeatured ? ["featured"] as const : [])],
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="flex h-[min(90vh,56rem)] max-w-[calc(100vw-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
        <DialogHeader className="shrink-0 gap-1 px-5 pb-3 pt-4 text-left">
          <DialogTitle>Locations</DialogTitle>
          <DialogDescription>Publish the design, choose its output, and compose a grouped query for the content it should match.</DialogDescription>
        </DialogHeader>
        <Separator className="shrink-0" />

        <ScrollArea className="min-h-0 flex-1 bg-muted/30">
          <div className="mx-auto grid w-full max-w-[920px] gap-4 px-5 py-5">
            <section className="rounded-lg border bg-card p-4 shadow-xs">
              <div className="flex items-start justify-between gap-5">
                <div>
                  <h3 className="text-xs font-semibold">Design status</h3>
                  <p className="mt-1 text-[11px] leading-4 text-muted-foreground">Only published designs participate in automatic matching.</p>
                </div>
                <NativeSelect className="w-32" value={status} onChange={(event) => onStatusChange(event.target.value as DesignStatus)}>
                  <option value="publish">Published</option>
                  <option value="draft">Draft</option>
                </NativeSelect>
              </div>
            </section>

            <section className="rounded-lg border bg-card p-4 shadow-xs">
              <h3 className="text-xs font-semibold">Generated outputs</h3>
              <p className="mt-1 text-[11px] text-muted-foreground">Open Graph and X/Twitter share one social image assignment.</p>
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                <Checkbox label="Open Graph + X/Twitter" checked={social} containerClassName="rounded-md border bg-background px-3 py-3" onChange={(event) => setOutput("social", event.target.checked)} />
                <Checkbox label="Featured image" checked={featured} containerClassName="rounded-md border bg-background px-3 py-3" onChange={(event) => setOutput("featured", event.target.checked)} />
              </div>
              <p className="mt-2 text-[10px] text-muted-foreground">Current output: {outputSummary}</p>
              {featured && <Checkbox label="Replace an existing featured image when global settings allow it" checked={rules.replaceFeatured} containerClassName="mt-3 border-t pt-3" onChange={(event) => onChange({ ...rules, replaceFeatured: event.target.checked })} />}
            </section>

            <section className="rounded-lg border bg-card p-4 shadow-xs">
              <div>
                <h3 className="text-xs font-semibold">Content query</h3>
                <p className="mt-1 text-[11px] text-muted-foreground">Combine all/any groups to express nested Boolean matching. Up to 30 rules and three nested group levels are saved.</p>
              </div>
              <div className="mt-4">
                <QueryGroupEditor root group={rules.query} postTypes={postTypes} onChange={(query) => onChange({ ...rules, query })} />
              </div>
            </section>

            <section className="rounded-lg border bg-card p-4 shadow-xs">
              <div className="grid items-center gap-3 sm:grid-cols-[1fr_140px]">
                <div>
                  <h3 className="text-xs font-semibold">Priority</h3>
                  <p className="mt-1 text-[11px] text-muted-foreground">Lower numbers win when multiple published designs match the same output.</p>
                </div>
                <Input type="number" min={-1000} max={1000} value={rules.priority} onChange={(event) => onChange({ ...rules, priority: Number(event.target.value) || 0 })} />
              </div>
            </section>
          </div>
        </ScrollArea>

        <DialogFooter className="shrink-0 flex-row items-center justify-between border-t px-5 py-4">
          <p className="text-xs text-muted-foreground">Location changes are applied when you save the design.</p>
          <Button type="button" onClick={() => onOpenChange(false)}>Done</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

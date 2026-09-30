import { readFileSync } from "node:fs";

const repository = JSON.parse(readFileSync(new URL("../presets/repository.json", import.meta.url), "utf8"));
const presetSchema = JSON.parse(readFileSync(new URL("../schemas/preset.schema.json", import.meta.url), "utf8"));
const repositorySchema = JSON.parse(readFileSync(new URL("../schemas/repository.schema.json", import.meta.url), "utf8"));
const expectedCategories = ["Editorial", "Minimal", "Bold", "Photography", "Business", "Technology", "Creative", "Podcast", "Events", "Lifestyle", "Culture", "Sports", "Luxury"];
const complexCategories = new Set(["Culture", "Sports", "Luxury"]);
const issues = [];

if (repository.schemaVersion !== 1) issues.push("Repository schemaVersion must be 1.");
if (repository.id !== "social-image-essentials") issues.push("Bundled repository id must remain social-image-essentials.");
if (!Array.isArray(repository.presets) || repository.presets.length !== 65) issues.push(`Expected 65 presets, received ${repository.presets?.length ?? 0}.`);
if (presetSchema.$schema !== "https://json-schema.org/draft/2020-12/schema") issues.push("Preset schema must use JSON Schema Draft 2020-12.");
if (repositorySchema.$schema !== "https://json-schema.org/draft/2020-12/schema") issues.push("Repository schema must use JSON Schema Draft 2020-12.");

const presets = Array.isArray(repository.presets) ? repository.presets : [];
const ids = presets.map((preset) => preset.id);
const titles = presets.map((preset) => preset.title);
if (new Set(ids).size !== ids.length) issues.push("Preset IDs must be unique.");
if (new Set(titles).size !== titles.length) issues.push("Preset titles must be unique.");

for (const category of expectedCategories) {
  const count = presets.filter((preset) => preset.category === category).length;
  if (count !== 5) issues.push(`${category} must contain exactly 5 presets; received ${count}.`);
}

for (const preset of presets) {
  if (preset.schemaVersion !== 1) issues.push(`${preset.id} must use preset schema version 1.`);
  if (preset.document?.version !== 6) issues.push(`${preset.id} must use document schema version 6.`);
  if (preset.document?.width !== 1200 || preset.document?.height !== 630) issues.push(`${preset.id} must use a 1200×630 canvas.`);
  const elements = preset.document?.elements ?? [];
  if (!Array.isArray(preset.document?.elements) || elements.length === 0 || elements.length > 100) issues.push(`${preset.id} has an invalid element count.`);
  if (complexCategories.has(preset.category) && elements.length < 14) issues.push(`${preset.id} must retain at least 14 elements as a complex preset.`);

  const elementIds = elements.map((element) => element.id);
  if (new Set(elementIds).size !== elementIds.length) issues.push(`${preset.id} contains duplicate element IDs.`);
  if (elements.some((element) => element.type === "rect")) issues.push(`${preset.id} contains an unsupported rect element; use Shape with rectangle geometry.`);
}

const serialized = JSON.stringify(repository);
const unsplashUrls = [...serialized.matchAll(/https:\/\/images\.unsplash\.com\/[^\"\\]+/g)].map((match) => match[0]);
const uniqueUnsplashUrls = [...new Set(unsplashUrls)];
if (uniqueUnsplashUrls.length !== 18) issues.push(`Expected 18 curated Unsplash URLs, received ${uniqueUnsplashUrls.length}.`);
if (serialized.includes("source.unsplash.com")) issues.push("Random or deprecated source.unsplash.com URLs are not allowed.");

for (const url of uniqueUnsplashUrls) {
  if (!url.includes("auto=format") || !url.includes("fit=crop") || !url.includes("w=1600") || !url.includes("q=85")) {
    issues.push(`Unsplash URL is missing the standard crop and quality parameters: ${url}`);
  }
}

if (issues.length > 0) throw new Error(`Invalid Social Image preset repository:\n${issues.join("\n")}`);

process.stdout.write("Preset repository valid: 65 presets, 13 collections, 18 curated Unsplash images.\n");

import { defineConfig, devices } from "@playwright/test";

const baseURL = process.env.WP_BASE_URL || "http://localhost:8888";
const storageState = process.env.STORAGE_STATE_PATH || "artifacts/storage-states/admin.json";
const hostResolverRules = process.env.PLAYWRIGHT_HOST_RESOLVER_RULES?.trim();

export default defineConfig({
  testDir: "./tests/e2e",
  globalSetup: "./tests/e2e/global-setup.ts",
  outputDir: "artifacts/test-results",
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["line"], ["html", { outputFolder: "playwright-report", open: "never" }]] : "list",
  use: {
    baseURL,
    storageState,
    launchOptions: hostResolverRules
      ? { args: [`--host-resolver-rules=${hostResolverRules}`] }
      : undefined,
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});

import { mkdir } from "node:fs/promises";
import { dirname } from "node:path";
import { chromium, type FullConfig } from "@playwright/test";
import { RequestUtils } from "@wordpress/e2e-test-utils-playwright";

export default async function globalSetup(config: FullConfig) {
  const project = config.projects[0];
  const baseURL = String(project.use.baseURL || process.env.WP_BASE_URL || "http://localhost:8888");
  const storageStatePath = String(project.use.storageState || process.env.STORAGE_STATE_PATH || "artifacts/storage-states/admin.json");
  const username = process.env.WP_USERNAME || "admin";
  const password = process.env.WP_PASSWORD || "password";
  const hostResolverRules = process.env.PLAYWRIGHT_HOST_RESOLVER_RULES?.trim();

  await mkdir(dirname(storageStatePath), { recursive: true });

  if (hostResolverRules) {
    const browser = await chromium.launch({
      args: [`--host-resolver-rules=${hostResolverRules}`],
    });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
      await page.goto(new URL("/wp-login.php", baseURL).toString());
      await page.getByLabel("Username or Email Address").fill(username);
      await page.getByLabel("Password", { exact: true }).fill(password);
      await page.getByRole("button", { name: "Log In" }).click();
      await page.waitForURL(/\/wp-admin\//);
      await context.storageState({ path: storageStatePath });
    } finally {
      await browser.close();
    }

    return;
  }

  const requestUtils = await RequestUtils.setup({
    baseURL,
    storageStatePath,
    user: {
      username,
      password,
    },
  });

  await requestUtils.login();
  await requestUtils.request.storageState({ path: storageStatePath });
  await requestUtils.request.dispose();
}

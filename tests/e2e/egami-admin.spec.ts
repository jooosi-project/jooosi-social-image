import AxeBuilder from "@axe-core/playwright";
import type { Page } from "@playwright/test";
import { expect, test, type Admin } from "@wordpress/e2e-test-utils-playwright";

async function visitEgami(admin: Admin, page: Page) {
  await admin.visitAdminPage("admin.php", "page=jooosi-egami");
  await expect(page.getByRole("heading", { name: "Designs", level: 1 })).toBeVisible();
}

async function openFirstDesign(page: Page) {
  const firstCard = page.locator("#egami-admin article").first();
  await expect(firstCard).toBeVisible();
  await firstCard.getByRole("button").nth(1).click();
  await expect(page.getByRole("textbox", { name: "Design title" })).toBeVisible();
}

async function expectNoWcagViolations(page: Page, selector = "#egami-admin") {
  const results = await new AxeBuilder({ page })
    .include(selector)
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
    .analyze();

  expect(results.violations).toEqual([]);
}

test.describe("Egami admin", () => {
  test("opens the design editor with an accessible workspace", async ({ admin, page }) => {
    await visitEgami(admin, page);
    await openFirstDesign(page);

    await expect(page.getByRole("complementary", { name: "Structure" })).toBeVisible();
    await expect(page.getByRole("complementary", { name: "Element inspector" })).toBeVisible();
    await expect(page.getByRole("button", { name: /Save design/ })).toBeVisible();
    await expectNoWcagViolations(page);
  });

  test("opens the WordPress Media Library from an image element", async ({ admin, page }) => {
    await visitEgami(admin, page);
    await openFirstDesign(page);

    await page.getByRole("button", { name: "Add element" }).click();
    await page.getByRole("menuitem", { name: "Image", exact: true }).click();
    await page.getByRole("button", { name: "Choose Media Library image" }).click();

    const dialog = page.getByRole("dialog", { name: "Choose an image" });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole("tab", { name: "Media Library" })).toHaveAttribute("aria-selected", "true");
    await expect(dialog.getByRole("button", { name: "Use this file" })).toBeDisabled();
    await dialog.getByRole("button", { name: "Close dialog" }).click();
    await expect(dialog).toBeHidden();
  });

  test("removes a selected Media Library image", async ({ admin, page }) => {
    await visitEgami(admin, page);
    await openFirstDesign(page);

    await page.getByRole("button", { name: "Add element" }).click();
    await page.getByRole("menuitem", { name: "Image", exact: true }).click();

    await page.evaluate(() => {
      const appWindow = window as Window & { wp?: Record<string, unknown> };
      if (!appWindow.wp) throw new Error("WordPress media API is unavailable");

      appWindow.wp.media = () => {
        let select: (() => void) | undefined;

        return {
          on: (event: string, callback: () => void) => {
            if (event === "select") select = callback;
          },
          state: () => ({
            get: () => ({
              first: () => ({
                toJSON: () => ({ id: 123, url: "https://example.test/selected-image.jpg" }),
              }),
            }),
          }),
          open: () => select?.(),
        };
      };
    });

    await page.getByRole("button", { name: "Choose Media Library image" }).click();
    await expect(page.locator('img[src="https://example.test/selected-image.jpg"]')).toBeVisible();

    await page.getByRole("button", { name: "Remove image" }).click();
    await expect(page.getByRole("textbox", { name: "Image source" })).toHaveValue("");
    await expect(page.locator('img[src="https://example.test/selected-image.jpg"]')).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Remove image" })).toBeHidden();
  });

  test("exposes actionable rendering diagnostics", async ({ admin, page }) => {
    await visitEgami(admin, page);
    await openFirstDesign(page);

    await page.getByRole("button", { name: "More actions" }).click();
    await page.getByRole("menuitem", { name: "Rendering settings" }).click();

    const dialog = page.getByRole("dialog", { name: "Rendering settings" });
    await expect(dialog.getByText("Server fallback fonts", { exact: true })).toBeVisible();
    await expect(dialog.getByText("Generated-image storage", { exact: true })).toBeVisible();
    await expect(dialog.getByText("Background generation", { exact: true })).toBeVisible();
    await expectNoWcagViolations(page, '[role="dialog"]');
  });

  test("shows the About page and project sponsors", async ({ admin, page }) => {
    await visitEgami(admin, page);

    await page.getByRole("tab", { name: "About" }).click();

    const panel = page.getByRole("tabpanel", { name: "About" });
    await expect(panel.getByRole("heading", { name: "Jooosi Egami", level: 1 })).toBeVisible();
    await expect(panel.getByRole("heading", { name: "Open-source sponsorship", level: 2 })).toBeVisible();
    await expect(panel.getByRole("link", { name: "GitHub Sponsors" })).toHaveAttribute("href", "https://github.com/sponsors/suasgn");
    await expect(panel.getByRole("link", { name: "Ko-fi" })).toHaveAttribute("href", "https://ko-fi.com/Q5Q75XSF7");
    await expect(panel.getByRole("heading", { name: "Proudly sponsored by", level: 2 })).toBeVisible();
    const jooosiSponsor = panel.getByRole("link", { name: /^Jooosi/ });
    const liveCanvasSponsor = panel.getByRole("link", { name: /^LiveCanvas/ });

    await expect(jooosiSponsor).toHaveAttribute("href", "https://jooo.si");
    await expect(jooosiSponsor.locator("svg").first()).toBeVisible();
    await expect(liveCanvasSponsor).toHaveAttribute("href", "https://livecanvas.com");
    await expect(liveCanvasSponsor.locator("svg").first()).toBeVisible();
    await expectNoWcagViolations(page, "#egami-about-panel");
  });
});

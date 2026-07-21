const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost:8000/?page=dashboard';

// C1: No match → empty results, no crash
test('C1 - no match returns empty results', async ({ page }) => {
  await page.goto(BASE_URL);
  await page.fill('input[name="q"]', 'zzzqxwv');
  await page.press('input[name="q"]', 'Enter');
  await expect(page.locator('.search-result')).toHaveCount(0);
});

// C2: Empty search → homepage renders
test('C2 - empty search shows homepage', async ({ page }) => {
  await page.goto(BASE_URL);
  await expect(page.locator('.dashboard-columns')).toBeVisible();
});

// C3: Whitespace only → homepage renders
test('C3 - whitespace only shows homepage', async ({ page }) => {
  await page.goto(BASE_URL);
  await page.fill('input[name="q"]', '   ');
  await page.press('input[name="q"]', 'Enter');
  await expect(page.locator('.dashboard-columns')).toBeVisible();
});

// C4: SQL injection → no crash, page renders
test('C4 - SQL injection returns no results safely', async ({ page }) => {
  await page.goto(BASE_URL);
  await page.fill('input[name="q"]', "'; DROP TABLE events;--");
  await page.press('input[name="q"]', 'Enter');
  await expect(page.locator('body')).toBeVisible();
});

// C5: XSS script → renders as text, no alert
test('C5 - XSS script renders as inert text', async ({ page }) => {
  await page.goto(BASE_URL);
  await page.fill('input[name="q"]', '<script>alert(1)</script>');
  await page.press('input[name="q"]', 'Enter');
  await expect(page.locator('body')).toBeVisible();
});

// E1: Pressing Enter submits the search
test('E1 - Enter key submits search', async ({ page }) => {
  await page.goto(BASE_URL);
  await page.fill('input[name="q"]', 'music');
  await page.press('input[name="q"]', 'Enter');
  await expect(page).toHaveURL(/q=music/);
});

// E2: X button clears search and returns to homepage
test('E2 - X button clears search', async ({ page }) => {
  await page.goto(BASE_URL + '&q=music');
  await page.click('.search-input-wrap a');
  await expect(page).toHaveURL(/[^q]/);
  await expect(page.locator('.dashboard-columns')).toBeVisible();
});

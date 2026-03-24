const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false }); // Headless false for screenshots
  const page = await browser.newPage();

  // Set desktop viewport
  await page.setViewportSize({ width: 1920, height: 1080 });

  const requests = [];
  const consoleEvents = [];
  const pageErrors = [];

  page.on('requestfinished', async (req) => {
    const url = req.url();
    if (url.includes('admin-ajax.php')) {
      const res = await req.response();
      let bodySnippet = '';
      try {
        const text = await res.text();
        bodySnippet = text.slice(0, 500);
      } catch (e) {}
      requests.push({
        url,
        method: req.method(),
        status: res ? res.status() : null,
        bodySnippet
      });
    }
  });

  page.on('console', (msg) => {
    if (['error', 'warning'].includes(msg.type())) {
      consoleEvents.push({
        type: msg.type(),
        text: msg.text()
      });
    }
  });

  page.on('pageerror', (err) => {
    pageErrors.push(String(err));
  });

  await page.goto('https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/', {
    waitUntil: 'networkidle'
  });

  // Screenshot 1: before opening
  await page.screenshot({ path: 'before_open.png' });

  // Check window.vanaDrawer
  const vanaDrawer = await page.evaluate(() => window.vanaDrawer);
  console.log('window.vanaDrawer:', vanaDrawer);

  // Click the tour drawer button
  const drawerBtn = await page.locator('[data-drawer="vana-tour-drawer"]');
  console.log('drawerBtn count:', await drawerBtn.count());
  if (await drawerBtn.count() > 0) {
    await drawerBtn.click();

    // Screenshot 2: right after click
    await page.screenshot({ path: 'after_click.png' });

    await page.waitForTimeout(3000); // Wait for load

    // Screenshot 3: after load
    await page.screenshot({ path: 'after_load.png' });

    // Try to click on first tour item if exists
    const firstTour = await page.locator('#vana-drawer-tour-list button').first();
    if (await firstTour.count() > 0) {
      await firstTour.click();
      await page.waitForTimeout(2000);

      // Screenshot 4: after clicking tour
      await page.screenshot({ path: 'after_tour_click.png' });
    }
  }

  const domInfo = await page.evaluate(() => {
    const drawer = document.getElementById('vana-tour-drawer');
    const overlay = document.getElementById('vana-drawer-overlay');
    const tourList = document.getElementById('vana-drawer-tour-list');
    const tourBody = document.getElementById('vana-drawer-body');
    const tourLoading = document.getElementById('vana-drawer-loading');
    const visitsBody = document.getElementById('vana-drawer-visits');
    const visitsLoading = document.getElementById('vana-drawer-visits-loading');
    const visitsList = document.getElementById('vana-drawer-visit-list');

    // Audit all potential spinners
    const allSpinners = Array.from(document.querySelectorAll('[id*="loading"], [id*="spinner"], [class*="loading"], [class*="spinner"]')).map(el => ({
      id: el.id,
      className: el.className,
      hidden: el.hidden,
      ariaHidden: el.getAttribute('aria-hidden'),
      style: el.getAttribute('style'),
      computedVisibility: window.getComputedStyle(el).visibility,
      display: window.getComputedStyle(el).display
    }));

    return {
      drawerVisible: drawer ? !drawer.hidden : false,
      overlayVisible: overlay ? overlay.style.display !== 'none' : false,
      tourListVisible: tourList ? !tourList.hidden : false,
      tourBodyVisible: tourBody ? !tourBody.hidden : false,
      tourLoadingVisible: tourLoading ? !tourLoading.hidden : false,
      visitsBodyVisible: visitsBody ? !visitsBody.hidden : false,
      visitsLoadingVisible: visitsLoading ? !visitsLoading.hidden : false,
      visitsListVisible: visitsList ? !visitsList.hidden : false,
      tourListChildren: tourList ? tourList.children.length : 0,
      visitsListChildren: visitsList ? visitsList.children.length : 0,
      tourListText: tourList ? tourList.textContent.trim().slice(0, 200) : '',
      visitsListText: visitsList ? visitsList.textContent.trim().slice(0, 200) : '',
      allSpinners
    };
  });

  console.log(JSON.stringify({
    domInfo,
    requests,
    consoleEvents,
    pageErrors
  }, null, 2));

  // Test mobile viewport
  await page.setViewportSize({ width: 375, height: 667 });
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

  // Repeat click and screenshot for mobile
  const drawerBtnMobile = await page.locator('[data-drawer="vana-tour-drawer"]');
  if (await drawerBtnMobile.count() > 0) {
    await drawerBtnMobile.click();
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'mobile_after_load.png' });
  }

  const domInfoMobile = await page.evaluate(() => {
    const tourLoading = document.getElementById('vana-drawer-loading');
    const allSpinners = Array.from(document.querySelectorAll('[id*="loading"], [id*="spinner"], [class*="loading"], [class*="spinner"]')).map(el => ({
      id: el.id,
      className: el.className,
      hidden: el.hidden,
      display: window.getComputedStyle(el).display
    }));
    return {
      tourLoadingVisible: tourLoading ? !tourLoading.hidden : false,
      allSpinners
    };
  });

  console.log('Mobile DOM:', JSON.stringify(domInfoMobile, null, 2));

  await browser.close();
})();
#!/usr/bin/env node
/*
  Fase 5 Smoke Tester (Playwright)
  Usage:
    node beta/fase5-smoke-tester.mjs "https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/?lang=pt"

  Optional env vars:
    HEADLESS=true|false (default: false)
    LANG_CODE=pt|en      (default: pt)
*/

const args = process.argv.slice(2);
const targetUrl = args.find((a) => /^https?:\/\//i.test(a)) || "https://beta.vanamadhuryamdaily.com/visit/dia-1-vrindavan/?lang=pt";
const headless = args.includes("--headless") || String(process.env.HEADLESS || "false").toLowerCase() === "true";
const defaultLang = process.env.LANG_CODE || "pt";
const preferChromeChannel = !args.includes("--no-channel-chrome") && String(process.env.CHROME_CHANNEL || "true").toLowerCase() !== "false";
const useSystemProxy = args.includes("--system-proxy") || String(process.env.SYSTEM_PROXY || "false").toLowerCase() === "true";
const forceDirectConnection = args.includes("--direct") || String(process.env.NO_PROXY || "false").toLowerCase() === "true";

let chromium;
try {
  ({ chromium } = await import("playwright"));
} catch (err) {
  console.error("Playwright nao encontrado.");
  console.error("Instale com: npm i -D playwright");
  process.exit(2);
}

const requiredChecks = {
  endpoint200: false,
  htmlInjected: false,
  urlUpdated: false,
};

const desirableChecks = {
  stageV2Header: false,
  transitionVisible: false,
  backForwardWorks: false,
};

const regressions = {
  uncaughtConsoleErrors: [],
  blankStage: false,
  requestLoop: false,
  resourceErrors: [],
};

const networkTrace = [];

const launchArgs = [
  "--no-sandbox",
  "--disable-setuid-sandbox",
  "--disable-web-security",
  "--disable-features=IsolateOrigins,site-per-process",
];

if (forceDirectConnection) {
  launchArgs.push("--no-proxy-server");
}

let browser;
try {
  browser = await chromium.launch({
    headless,
    channel: preferChromeChannel ? "chrome" : undefined,
    args: launchArgs,
  });
  console.log("Browser launch:", preferChromeChannel ? "chrome channel" : "bundled chromium");
} catch (err) {
  console.warn("Primary browser launch failed; retrying with bundled chromium.");
  console.warn("Reason:", err?.message || err);
  browser = await chromium.launch({ headless, args: launchArgs });
  console.log("Browser launch: bundled chromium (fallback)");
}

const contextOptions = {};
if (useSystemProxy) {
  contextOptions.proxy = { server: "per-sys" };
}

const context = await browser.newContext(contextOptions);
const page = await context.newPage();

page.on("console", (msg) => {
  if (msg.type() === "error") {
    regressions.uncaughtConsoleErrors.push(msg.text());
  }
});

page.on("response", async (resp) => {
  const url = resp.url();
  if (resp.status() >= 400) {
    regressions.resourceErrors.push({
      url,
      status: resp.status(),
    });
  }

  if (url.includes("/wp-json/vana/v1/stage") || url.includes("/wp-json/vana/v1/stage-fragment")) {
    const headers = resp.headers();
    networkTrace.push({
      url,
      status: resp.status(),
      contentType: headers["content-type"] || "",
      xVanaEndpoint: headers["x-vana-endpoint"] || "",
      xVanaFragment: headers["x-vana-fragment"] || "",
    });
  }
});

function logSection(title) {
  console.log("\n" + "=".repeat(72));
  console.log(title);
  console.log("=".repeat(72));
}

function toAbsoluteUrl(path) {
  return new URL(path, targetUrl).toString();
}

try {
  logSection("FASE 5 SMOKE TEST");
  console.log("Target:", targetUrl);
  console.log("Headless:", headless);
  console.log("Prefer chrome channel:", preferChromeChannel);
  console.log("System proxy:", useSystemProxy);
  console.log("Direct connection:", forceDirectConnection);

  await page.goto(targetUrl, { waitUntil: "domcontentloaded", timeout: 60000 });
  await page.waitForTimeout(800);

  const btns = await page.$$('[data-vana-event-key]');
  console.log("Event buttons found:", btns.length);

  if (btns.length < 2) {
    console.log("FAIL BLOCKER: visita sem 2+ eventos no selector.");
    process.exitCode = 1;
    await browser.close();
    process.exit(process.exitCode);
  }

  const firstMeta = await page.evaluate(() => {
    const btn = document.querySelector('[data-vana-event-key]');
    return {
      eventKey: btn?.dataset?.vanaEventKey || null,
      visitId: btn?.dataset?.vanaVisitId || null,
      lang: btn?.dataset?.vanaLang || null,
    };
  });

  console.log("Sample event:", firstMeta);
  if (!firstMeta.eventKey || !firstMeta.visitId) {
    console.log("FAIL BLOCKER: metadata insuficiente (event_key/visit_id).")
    process.exitCode = 1;
    await browser.close();
    process.exit(process.exitCode);
  }

  logSection("PASSO 2 - Endpoint real");
  const endpoint = `/wp-json/vana/v1/stage/${encodeURIComponent(firstMeta.eventKey)}?visit_id=${firstMeta.visitId}&lang=${firstMeta.lang || defaultLang}`;
  const apiResp = await page.request.get(toAbsoluteUrl(endpoint));
  const apiText = await apiResp.text();
  const apiHeaders = apiResp.headers();

  console.log("status:", apiResp.status());
  console.log("content-type:", apiHeaders["content-type"] || "");
  console.log("x-vana-endpoint:", apiHeaders["x-vana-endpoint"] || "");
  console.log("html length:", apiText.length);
  console.log("preview:", apiText.slice(0, 200).replace(/\s+/g, " "));

  if (apiResp.status() === 200) requiredChecks.endpoint200 = true;
  if ((apiHeaders["x-vana-endpoint"] || "") === "stage-v2") desirableChecks.stageV2Header = true;

  logSection("PASSO 3 - Clique real + Network");

  const before = await page.locator("#vana-stage").innerHTML();
  const clickStart = Date.now();

  // Re-query on each iteration: HTMX replaces DOM after click, cached handles go stale.
  for (let i = 0; i < btns.length; i++) {
    const freshBtns = await page.$$('[data-vana-event-key]');
    if (i >= freshBtns.length) break;
    await freshBtns[i].click();
    await page.waitForTimeout(550);
  }

  const after = await page.locator("#vana-stage").innerHTML();
  requiredChecks.htmlInjected = before !== after;
  desirableChecks.transitionVisible = await page.evaluate(() => {
    const el = document.getElementById("vana-stage");
    return !!el && (el.style.transition || "").includes("opacity");
  });

  const currentUrl = page.url();
  requiredChecks.urlUpdated = currentUrl.includes("event_key=");
  console.log("Current URL:", currentUrl);

  // crude loop detector: too many stage requests in a short window
  const stageReqCount = networkTrace.length;
  const elapsed = Date.now() - clickStart;
  regressions.requestLoop = stageReqCount > btns.length * 4 && elapsed < 10000;

  // back/forward quick probe
  const urlBeforeBack = page.url();
  await page.goBack({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(300);
  const urlAfterBack = page.url();
  await page.goForward({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(300);
  const urlAfterForward = page.url();
  desirableChecks.backForwardWorks = urlBeforeBack !== urlAfterBack || urlAfterForward !== urlAfterBack;

  // blank stage detection
  const stageText = await page.locator("#vana-stage").innerText();
  regressions.blankStage = stageText.trim().length === 0;

  logSection("PASSO 4 - Fallback legado");
  const legacyUrl = `/wp-json/vana/v1/stage-fragment?visit_id=${firstMeta.visitId}&item_id=${encodeURIComponent(firstMeta.eventKey)}&item_type=event&lang=${firstMeta.lang || defaultLang}`;
  const legacyResp = await page.request.get(toAbsoluteUrl(legacyUrl));
  console.log("legacy status:", legacyResp.status());
  console.log("legacy x-vana-fragment:", legacyResp.headers()["x-vana-fragment"] || "");

  logSection("RESULTADO");
  console.log("Required:", requiredChecks);
  console.log("Desirable:", desirableChecks);
  console.log("Regressions:", regressions);
  console.log("Network traces:", networkTrace.slice(-10));
  if (regressions.resourceErrors.length) {
    console.log("Resource errors:", regressions.resourceErrors.slice(-20));
  }

  const hardFail =
    !requiredChecks.endpoint200 ||
    !requiredChecks.htmlInjected ||
    !requiredChecks.urlUpdated ||
    regressions.blankStage ||
    regressions.requestLoop ||
    regressions.uncaughtConsoleErrors.length > 0;

  if (hardFail) {
    console.log("\nSMOKE: FAIL");
    process.exitCode = 1;
  } else {
    console.log("\nSMOKE: PASS");
    process.exitCode = 0;
  }
} catch (err) {
  console.error("SMOKE: ERROR", err);
  process.exitCode = 1;
} finally {
  await browser.close();
  process.exit(process.exitCode);
}

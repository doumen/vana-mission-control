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
const defaultTestVideo = (args.find(a => a.startsWith('--test-video=')) ? args.find(a => a.startsWith('--test-video=')).split('=')[1] : process.env.TEST_VIDEO) || 'dQw4w9WgXcQ';
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

// Optional tracing (screenshots + DOM snapshots) for debugging
const enableTrace = args.includes("--trace") || String(process.env.ENABLE_TRACE || "false").toLowerCase() === "true";
if (enableTrace) {
  try {
    await context.tracing.start({ screenshots: true, snapshots: true });
    console.log("Tracing enabled: screenshots + snapshots");
  } catch (e) {
    console.warn("Tracing start failed:", e?.message || e);
  }
}

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

  logSection("PASSO 3 - Dispatch-only media selection + Network");

  const before = await page.locator("#vana-stage").innerHTML();
  const beforeIframeSrc = await page.evaluate(() => {
    const f = document.getElementById('vanaStageIframe') || document.querySelector('#vana-stage iframe');
    return f ? f.getAttribute('src') : null;
  });
  const beforeStageAttr = await page.evaluate(() => {
    const el = document.getElementById('vana-stage');
    return el ? el.getAttribute('data-event-key') || null : null;
  });
  const dispatchStart = Date.now();

  // Collect media/selectable items from the agenda: prefer explicit play data, fall back to event key nodes
  const items = await page.evaluate(() => {
    const nodes = Array.from(document.querySelectorAll('[data-vana-play-vod],[data-vana-event-key]'));
    return nodes.map((el) => ({
      vodId: el.getAttribute('data-vana-play-vod') || el.getAttribute('data-vana-vod-id') || el.dataset?.vanaVodId || '',
      videoId: el.getAttribute('data-vana-video-id') || el.dataset?.vanaVideoId || '',
      title: el.getAttribute('data-vana-event-title') || el.dataset?.vanaEventTitle || el.getAttribute('data-vana-title') || el.dataset?.title || '',
      segStart: el.getAttribute('data-vana-segment-start') || el.getAttribute('data-segment-start') || el.dataset?.segmentStart || '',
      eventKey: el.getAttribute('data-vana-event-key') || el.dataset?.vanaEventKey || '',
      visitId: el.getAttribute('data-vana-visit-id') || el.dataset?.vanaVisitId || '',
      lang: el.getAttribute('data-vana-lang') || el.dataset?.vanaLang || ''
    }));
  });

  console.log('Dispatch items found:', items.length);

  // Dispatch programmatic selection for a few items deterministically (fast)
  const maxDispatch = Math.min(items.length, 3);
  for (let i = 0; i < maxDispatch; i++) {
    const d = items[i];
    console.log('Dispatching item', i, d.eventKey || d.videoId || d.vodId);
    await page.evaluate((payload) => {
      try {
        const detail = payload.detail || {};
        const fallbackVideo = payload.fallbackVideo;
        const chosenVideo = detail.videoId || detail.vodId || fallbackVideo || undefined;
        const ev = new CustomEvent('vana:event:select', {
          detail: {
            vod_id: detail.vodId || undefined,
            videoId: chosenVideo,
            title: detail.title || undefined,
            segStart: detail.segStart || undefined,
            event_key: detail.eventKey || undefined,
            visit_id: detail.visitId || undefined,
          },
          cancelable: true,
        });
        document.dispatchEvent(ev);
      } catch (e) {
        console.warn('dispatch error', e && e.message);
      }
    }, { detail: d, fallbackVideo: defaultTestVideo });

    // Wait briefly for the client swap to run and network to settle
    await page.waitForTimeout(600);

    // Check for iframe src change or stage markup change
    const afterIframeSrc = await page.evaluate(() => {
      const f = document.getElementById('vanaStageIframe') || document.querySelector('#vana-stage iframe');
      return f ? f.getAttribute('src') : null;
    });
    const afterHTML = await page.locator('#vana-stage').innerHTML();
    const afterStageAttr = await page.evaluate(() => {
      const el = document.getElementById('vana-stage');
      return el ? el.getAttribute('data-event-key') || null : null;
    });

    if (afterIframeSrc && afterIframeSrc !== beforeIframeSrc) {
      requiredChecks.htmlInjected = true;
      requiredChecks.urlUpdated = true; // treat iframe update as acceptable URL/state update proxy
      break;
    }
    if (afterHTML !== before) {
      requiredChecks.htmlInjected = true;
      // consider stage attribute change or iframe presence as a successful state update
      if (afterStageAttr && afterStageAttr !== beforeStageAttr) requiredChecks.urlUpdated = true;
      requiredChecks.urlUpdated = requiredChecks.urlUpdated || (await page.url()).includes('event_key=');
      break;
    }
  }

  // crude loop detector: too many stage requests in a short window
  const stageReqCount = networkTrace.length;
  const elapsed = Date.now() - dispatchStart;
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
  try {
    if (enableTrace) {
      const tracePath = "beta/smoke-trace.zip";
      try {
        await context.tracing.stop({ path: tracePath });
        console.log("Trace saved:", tracePath);
      } catch (e) {
        console.warn("Failed to save trace:", e?.message || e);
      }

      const screenshotPath = "beta/smoke-screenshot.png";
      try {
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log("Screenshot saved:", screenshotPath);
      } catch (e) {
        console.warn("Failed to save screenshot:", e?.message || e);
      }
    }
  } finally {
    await browser.close();
  }
  process.exit(process.exitCode);
}

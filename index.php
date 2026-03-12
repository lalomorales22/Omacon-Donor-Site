<?php
declare(strict_types=1);

require __DIR__ . '/api/bootstrap.php';

$bootstrap = prime_api_payload();
$paymentState = prime_compact_text($_GET['payment'] ?? '', 24);
$sessionId = prime_compact_text($_GET['session_id'] ?? '', 120);
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(prime_app_name(), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <div class="backdrop-grid" aria-hidden="true"></div>
  <div class="backdrop-orb orb-one" aria-hidden="true"></div>
  <div class="backdrop-orb orb-two" aria-hidden="true"></div>

  <div class="app-shell">
    <div class="app-frame">
      <header class="chrome-bar">
        <div class="chrome-title">
          <span>OMACON // SPONSORSHIP DESK</span>
          <span id="clockLabel">--:--</span>
        </div>
        <div class="chrome-actions">
          <span class="status-pill <?= $bootstrap['stripeReady'] ? 'status-live' : 'status-warn' ?>">
            <?= $bootstrap['stripeReady'] ? 'stripe online' : 'stripe keys missing' ?>
          </span>
          <a href="#viewerModal" class="chrome-button" data-action="open-viewer">open wall</a>
        </div>
      </header>

      <main class="workspace">
        <section class="tile tile-command">
          <div class="tile-header">
            <div>
              <p class="tile-kicker">omacon sponsorship</p>
              <h2>Video + conference package</h2>
            </div>
            <button type="button" class="chrome-button subtle" id="refreshBtn">refresh</button>
          </div>
          <div class="tile-body tile-scroll">
            <div class="hero-block">
              <div class="hero-ledger">
                <span class="hero-ping"></span>
                april 10 // omacon.org
              </div>
              <h1>Sponsor Omacon and the media run after it.</h1>
              <p>
                Teej and ThePrimeagen are cohosting a conference that sold out instantly. This sponsorship package covers the conference talks,
                the published YouTube videos for <strong>ThePrimeTimeagen</strong> and <strong>teej_dv</strong>, plus vlogs, funny side events, and follow-up media.
                Serious inquiries can dig into the specifics. Conference info lives at
                <a class="hero-link" href="https://www.omacon.org/" target="_blank" rel="noreferrer">omacon.org</a>.
              </p>
              <div class="status-tags hero-tags">
                <span class="inline-tag">sold out instantly</span>
                <span class="inline-tag">ThePrimeTimeagen 1m+</span>
                <span class="inline-tag">teej_dv 100k+</span>
              </div>
            </div>

            <div class="metric-grid">
              <article class="metric-card">
                <span class="metric-label">total raised</span>
                <strong class="metric-value" id="metricRaised">$0</strong>
                <span class="metric-foot">package volume</span>
              </article>
              <article class="metric-card">
                <span class="metric-label">partners</span>
                <strong class="metric-value" id="metricDonors">0</strong>
                <span class="metric-foot">live on the wall</span>
              </article>
              <article class="metric-card">
                <span class="metric-label">avg package</span>
                <strong class="metric-value" id="metricAverage">$0</strong>
                <span class="metric-foot">per checkout</span>
              </article>
              <article class="metric-card">
                <span class="metric-label">top tier</span>
                <strong class="metric-value" id="metricTier">starter</strong>
                <span class="metric-foot">highest live package</span>
              </article>
            </div>

            <div class="status-card">
              <div class="status-head">
                <span class="status-signal <?= $bootstrap['stripeReady'] ? 'status-live' : 'status-warn' ?>"></span>
                <strong id="stripeStateLabel"><?= $bootstrap['stripeReady'] ? 'Sponsorship checkout can go live now.' : 'Demo mode is loaded.' ?></strong>
              </div>
              <p id="stripeStateCopy">
                <?= $bootstrap['stripeReady']
                  ? 'Stripe Checkout Sessions are ready. Paid sponsorships flow into SQLite through the webhook or the return-to-app session sync and show up on the wall.'
                  : 'Three sample sponsors are preloaded so the wall feels alive. Add STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET in .env whenever Omacon is ready to go live.' ?>
              </p>
              <div class="status-tags">
                <span class="inline-tag">conference package</span>
                <span class="inline-tag">stripe checkout</span>
                <span class="inline-tag">3d wall</span>
              </div>
            </div>

            <div class="subpanel">
              <div class="subpanel-head">
                <span class="subpanel-title">activity feed</span>
                <span class="subpanel-meta">most recent events first</span>
              </div>
              <div id="feedList" class="feed-list"></div>
            </div>
          </div>
        </section>

        <section class="tile tile-console">
          <div class="tile-header">
            <div>
              <p class="tile-kicker">sponsor intake</p>
              <h2>Launch sponsorship</h2>
            </div>
            <button type="button" class="chrome-button subtle" id="demoFillBtn">fill demo data</button>
          </div>
          <div class="tile-body tile-scroll">
            <form id="sponsorForm" class="sponsor-form">
              <div class="field-grid">
                <label class="field">
                  <span>name / org</span>
                  <input id="companyInput" name="company" type="text" maxlength="80" placeholder="Pane Forge" required>
                </label>
                <label class="field">
                  <span>contact</span>
                  <input id="contactInput" name="contact" type="text" maxlength="80" placeholder="Micah Trent" required>
                </label>
                <label class="field">
                  <span>email</span>
                  <input id="emailInput" name="email" type="email" maxlength="120" placeholder="micah@paneforge.io" required>
                </label>
                <label class="field">
                  <span>website / github</span>
                  <input id="websiteInput" name="website" type="text" maxlength="120" placeholder="paneforge.io">
                </label>
              </div>

              <label class="field">
                <span>headline</span>
                <input id="headlineInput" name="headline" type="text" maxlength="120" placeholder="Sponsor the sold-out event, the published talks, and the post-event media.">
              </label>

              <label class="field">
                <span>bio</span>
                <textarea id="bioInput" name="bio" rows="4" maxlength="360" placeholder="Tell the wall what you do and why you want to sponsor Omacon, ThePrimeTimeagen, and teej_dv media around the conference."></textarea>
              </label>

              <div class="field">
                <span>tier</span>
                <div id="tierOptions" class="tier-options"></div>
              </div>

              <div class="field">
                <span>logo</span>
                <div class="upload-box">
                  <input id="logoFileInput" type="file" accept="image/png,image/jpeg,image/gif,image/webp">
                  <div class="file-row">
                    <label for="logoFileInput" class="file-trigger">choose logo</label>
                    <span class="file-name" id="logoFileName">no file selected</span>
                  </div>
                  <p id="logoStatus" class="field-note">Optional. Add a logo, avatar, or distro sigil before checkout.</p>
                </div>
              </div>

              <section class="preview-card" aria-label="sponsorship preview">
                <div class="preview-top">
                  <div class="preview-avatar" id="previewAvatar">DW</div>
                  <div>
                    <span class="preview-tier" id="previewTier">legend</span>
                    <h3 id="previewCompany">Omacon sponsor</h3>
                  </div>
                </div>
                <p class="preview-headline" id="previewHeadline">Sponsorship card preview for the Omacon wall.</p>
                <div class="preview-meta">
                  <span id="previewAmount">$999</span>
                  <span id="previewWebsite">awaiting website</span>
                </div>
              </section>

              <div class="form-actions">
                <button type="submit" class="launch-button" id="checkoutButton"><?= $bootstrap['stripeReady'] ? 'open sponsor checkout' : 'connect stripe to go live' ?></button>
                <p class="field-note">Paid sponsorships write into SQLite through the webhook or the return sync.</p>
              </div>
            </form>
          </div>
        </section>

        <section class="tile tile-wall">
          <div class="tile-header">
            <div>
              <p class="tile-kicker">release wall</p>
              <h2>Omacon field</h2>
            </div>
            <div class="inline-actions">
              <button type="button" class="chrome-button subtle" id="miniFocusBtn">focus selected</button>
              <a href="#viewerModal" class="chrome-button" data-action="open-viewer">open viewer</a>
            </div>
          </div>
          <div class="tile-body viewer-tile-body">
            <div class="viewer-frame">
              <div id="miniViewer" class="viewer-surface"></div>
              <div class="viewer-overlay">
                <span class="inline-tag">auto orbit</span>
                <div>
                  <strong id="overlayCompany">Loading sponsors...</strong>
                  <p id="overlayTier">Select a sponsor to inspect it.</p>
                </div>
              </div>
            </div>
            <div class="viewer-meta-grid">
              <article class="meta-card">
                <span class="metric-label">selected sponsor</span>
                <strong id="compactCompany">Pane Forge</strong>
                <span id="compactTier">Legend // live on wall</span>
              </article>
              <article class="meta-card">
                <span class="metric-label">viewer</span>
                <strong>Inspect the support field</strong>
                <span>Orbit, pan, zoom, and read every card without leaving the app.</span>
              </article>
            </div>
          </div>
        </section>

        <section class="tile tile-directory">
          <div class="tile-header">
            <div>
              <p class="tile-kicker">sponsor index</p>
              <h2>Sponsors + notes</h2>
            </div>
            <input id="searchInput" class="search-input" type="search" placeholder="search sponsors">
          </div>
          <div class="tile-body directory-body">
            <div id="donorList" class="donor-list"></div>
            <div id="detailPanel" class="detail-panel"></div>
          </div>
        </section>
      </main>
    </div>
  </div>

  <div class="viewer-modal" id="viewerModal" aria-hidden="true">
    <div class="viewer-dialog">
      <div class="viewer-dialog-head">
        <div>
          <p class="tile-kicker">full wall</p>
          <h2>Inspect the Omacon support field</h2>
        </div>
        <div class="inline-actions">
          <button type="button" class="chrome-button subtle" id="focusSelectedBtn">focus selected</button>
          <button type="button" class="chrome-button subtle" id="shuffleCameraBtn">shuffle orbit</button>
          <a href="#" class="chrome-button" id="closeViewerBtn">close</a>
        </div>
      </div>
      <div class="viewer-dialog-body">
        <div class="viewer-stage">
          <div id="fullViewer" class="viewer-surface viewer-surface-full"></div>
          <div class="viewer-hint">drag to orbit • wheel to zoom • right drag to pan</div>
        </div>
        <aside id="modalDetailPanel" class="detail-panel detail-panel-modal"></aside>
      </div>
    </div>
  </div>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script>
    window.APP_BOOTSTRAP = <?= json_encode($bootstrap, $jsonFlags) ?>;
    window.APP_VIEW_STATE = <?= json_encode([
        'payment' => $paymentState,
        'sessionId' => $sessionId,
    ], $jsonFlags) ?>;
  </script>
  <script type="module" src="/assets/app.js"></script>
</body>
</html>

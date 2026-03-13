# Omacon Sponsorship Wall

A Stripe-powered sponsorship platform for Omacon, the sold-out conference cohosted by ThePrimeagen and Teej. Sponsors check out through Stripe, land in SQLite, and appear on an interactive 3D wall built with Three.js.

<img width="1382" height="1034" alt="Screenshot 2026-03-13 at 12 14 20 AM" src="https://github.com/user-attachments/assets/117ac678-7155-4b2e-bb16-9fa45f6c53d6" />
<img width="1373" height="909" alt="Screenshot 2026-03-13 at 12 14 30 AM" src="https://github.com/user-attachments/assets/5a75be12-15f3-4dbb-a7a7-2fae91a740a4" />

## Stack

- **Backend:** PHP (vanilla, no framework) + SQLite
- **Frontend:** Vanilla JS + Three.js (v0.180) + OrbitControls
- **Payments:** Stripe Checkout Sessions API
- **Styling:** Custom CSS design system (no framework)

## Quick Start

```bash
cp .env.example .env
# Fill in your Stripe keys in .env
php -S 127.0.0.1:8000
```

Without Stripe keys the app runs in **demo mode** with three seeded sponsors.

## Interface

- **Desktop tiling workspace:** The main app is a four-pane 2x2 layout tuned for desktop screens, closer to a tiling window manager than a long scrolling dashboard.
- **Quadrant swapping:** Drag any pane by its title bar to swap it into another quadrant. The order is stored in `localStorage` and restored on reload.
- **Hover-only highlight:** Panes highlight only while the mouse is over them or while they are a drop target during a drag. No pane stays visually highlighted after the pointer leaves it.
- **Scrollbar-free panes:** The text-heavy panes auto-scale inside their quadrant so the default desktop layout stays visible without internal scrollbars.
- **Release wall behavior:** The compact wall pane defaults to a centered show-all view so the entire sponsor field stays visible. `focus selected` is still available when you want to isolate one card.
- **Viewer interaction:** The wall viewers stay still by default. Orbiting happens when the user clicks and drags, rather than from passive hover or default auto-rotation.
- **Expanded wall mode:** The modal wall viewer keeps orbit, pan, zoom, WASD movement, card selection, `show all`, and `focus selected`.
- **Selection sync:** Clicking a sponsor row or a wall card updates the selected sponsor details across the directory, detail panel, and viewer overlays.

## What Changed

- Replaced the fixed dashboard feel with a quadrant tiling workspace that can be rearranged by dragging pane headers.
- Added hover and drop-target feedback so panes behave more like movable windows.
- Tightened typography, spacing, and component sizing so four dense panes fit on screen at once.
- Added a pane-fit pass in the frontend so dense content scales down instead of producing nested scrollbars.
- Kept the 3D viewer interactive, but stopped auto-jumping the compact wall pane to a single selected card.
- Updated the compact wall camera behavior so `focus selected` centers on the chosen card more directly.
- Preserved the full-screen wall modal for a larger inspection view.

## Environment Variables

| Variable | Purpose |
|---|---|
| `APP_NAME` | Display name (default: "Omacon Fund Wall") |
| `APP_URL` | Base URL for Stripe redirect URLs |
| `STRIPE_SECRET_KEY` | Stripe secret key (`sk_test_...` or `sk_live_...`) |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_...`) |

## Sponsorship Tiers

| Tier | Amount |
|---|---|
| Starter | $99 |
| Booster | $249 |
| Ship-It | $499 |
| Legend | $999 |

## API Endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/donors.php` | GET | Fetch all donors, feed, stats, and tiers |
| `/api/create-checkout-session.php` | POST | Create a Stripe Checkout Session |
| `/api/session-status.php` | GET | Verify payment status after checkout return |
| `/api/upload-logo.php` | POST | Upload a sponsor logo (PNG/JPG/GIF/WEBP, 2.5MB max) |
| `/api/webhook.php` | POST | Stripe webhook receiver |

## Security

- **CSP** with per-request nonce for inline scripts
- **CSRF** protection via Origin header validation on POST endpoints
- **Webhook signature** verification (HMAC-SHA256 + timestamp tolerance)
- **X-Content-Type-Options**, **X-Frame-Options**, **Referrer-Policy** headers
- All SQL uses PDO prepared statements
- All HTML output escaped server-side (`htmlspecialchars`) and client-side (`escapeHtml`)
- File uploads validated by MIME type, capped at 2.5MB, saved with random filenames
- Upload directory blocks PHP execution via `.htaccess`
- JSON bootstrap uses `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`

## Webhook Setup

Point your Stripe webhook at `https://yourdomain.com/api/webhook.php` and subscribe to:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.expired`

## Next Phase: 3D Viewer Improvements

The current Three.js viewer is too zoomed in. Planned work:

- **Increase max zoom-out distance** on OrbitControls so the full sponsor wall is visible
- **Wider default camera position** so the initial view shows all cards, not just one
- **Better pan/orbit limits** to let users freely explore the scene without getting lost
- **Responsive card layout** that spreads cards out more as the donor count grows
- **Zoom-to-fit** button that auto-frames all sponsors in view
- **Scroll-wheel sensitivity tuning** for smoother zoom transitions

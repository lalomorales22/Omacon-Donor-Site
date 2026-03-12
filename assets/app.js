import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.180.0/build/three.module.js';
import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.180.0/examples/jsm/controls/OrbitControls.js';

const bootstrap = window.APP_BOOTSTRAP || {};
const viewState = window.APP_VIEW_STATE || {};

const moneyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 0,
});

const timeFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
});

const tierMap = new Map((bootstrap.tiers || []).map((tier) => [tier.key, tier]));

const state = {
  donors: Array.isArray(bootstrap.donors) ? bootstrap.donors : [],
  feed: Array.isArray(bootstrap.feed) ? bootstrap.feed : [],
  stats: bootstrap.stats || {
    totalRaisedCents: 0,
    donorCount: 0,
    averageGiftCents: 0,
    highestTier: 'starter',
  },
  stripeReady: Boolean(bootstrap.stripeReady),
  selectedId: (bootstrap.donors && bootstrap.donors[0] && bootstrap.donors[0].id) || null,
  search: '',
  uploadedLogoUrl: '',
  uploadingLogo: false,
  refreshing: false,
};

const elements = {
  clock: document.getElementById('clockLabel'),
  metricRaised: document.getElementById('metricRaised'),
  metricDonors: document.getElementById('metricDonors'),
  metricAverage: document.getElementById('metricAverage'),
  metricTier: document.getElementById('metricTier'),
  stripeStateLabel: document.getElementById('stripeStateLabel'),
  stripeStateCopy: document.getElementById('stripeStateCopy'),
  feedList: document.getElementById('feedList'),
  refreshBtn: document.getElementById('refreshBtn'),
  form: document.getElementById('sponsorForm'),
  companyInput: document.getElementById('companyInput'),
  contactInput: document.getElementById('contactInput'),
  emailInput: document.getElementById('emailInput'),
  websiteInput: document.getElementById('websiteInput'),
  headlineInput: document.getElementById('headlineInput'),
  bioInput: document.getElementById('bioInput'),
  tierOptions: document.getElementById('tierOptions'),
  logoFileInput: document.getElementById('logoFileInput'),
  logoFileName: document.getElementById('logoFileName'),
  logoStatus: document.getElementById('logoStatus'),
  checkoutButton: document.getElementById('checkoutButton'),
  demoFillBtn: document.getElementById('demoFillBtn'),
  previewAvatar: document.getElementById('previewAvatar'),
  previewTier: document.getElementById('previewTier'),
  previewCompany: document.getElementById('previewCompany'),
  previewHeadline: document.getElementById('previewHeadline'),
  previewAmount: document.getElementById('previewAmount'),
  previewWebsite: document.getElementById('previewWebsite'),
  overlayCompany: document.getElementById('overlayCompany'),
  overlayTier: document.getElementById('overlayTier'),
  compactCompany: document.getElementById('compactCompany'),
  compactTier: document.getElementById('compactTier'),
  searchInput: document.getElementById('searchInput'),
  donorList: document.getElementById('donorList'),
  detailPanel: document.getElementById('detailPanel'),
  modalDetailPanel: document.getElementById('modalDetailPanel'),
  miniViewer: document.getElementById('miniViewer'),
  fullViewer: document.getElementById('fullViewer'),
  viewerModal: document.getElementById('viewerModal'),
  closeViewerBtn: document.getElementById('closeViewerBtn'),
  focusSelectedBtn: document.getElementById('focusSelectedBtn'),
  shuffleCameraBtn: document.getElementById('shuffleCameraBtn'),
  miniFocusBtn: document.getElementById('miniFocusBtn'),
  toast: document.getElementById('toast'),
};

const demoPayloads = [
  {
    company: 'Pane Forge',
    contact: 'Micah Trent',
    email: 'micah@paneforge.io',
    website: 'paneforge.io',
    headline: 'Backing Omacon so release work and desktop polish keep landing.',
    bio: 'We care about packaging, onboarding, and the kind of defaults that make a Linux distro feel deliberate instead of accidental.',
    tier: 'ship-it',
  },
  {
    company: 'Dotfile Syndicate',
    contact: 'Rin Vale',
    email: 'rin@dotfilesyndicate.dev',
    website: 'dotfilesyndicate.dev',
    headline: 'Funding Omacon because fast terminals and sharp themes matter.',
    bio: 'Shell scripts, wallpapers, keybinds, and all the tiny quality-of-life details that make Omarchy-flavored desktops hit harder.',
    tier: 'legend',
  },
  {
    company: 'Kernel Roast',
    contact: 'Jae Soto',
    email: 'jae@kernelroast.com',
    website: 'kernelroast.com',
    headline: 'Coffee money for docs, packages, wallpapers, and release nights.',
    bio: 'A tiny roastery for people living in terminals. We are here for the distro, the community, and the late-night release energy.',
    tier: 'booster',
  },
];

class DonorWallViewer {
  constructor(container, options = {}) {
    this.container = container;
    this.interactive = Boolean(options.interactive);
    this.autoRotate = options.autoRotate ?? true;
    this.onSelect = options.onSelect ?? (() => {});
    this.selectedId = null;
    this.clock = new THREE.Clock();
    this.pointer = new THREE.Vector2();
    this.raycaster = new THREE.Raycaster();
    this.cardEntries = [];

    this.scene = new THREE.Scene();
    this.scene.fog = new THREE.Fog(0x050608, 10, 28);
    this.group = new THREE.Group();
    this.scene.add(this.group);

    this.camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
    this.camera.position.set(0, 1.2, 13);
    this.cameraGoal = this.camera.position.clone();
    this.targetGoal = new THREE.Vector3(0, 0.4, -1.6);

    this.renderer = new THREE.WebGLRenderer({
      antialias: true,
      alpha: true,
      powerPreference: 'high-performance',
    });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.setClearColor(0x000000, 0);
    this.container.appendChild(this.renderer.domElement);

    if (this.interactive) {
      this.controls = new OrbitControls(this.camera, this.renderer.domElement);
      this.controls.enableDamping = true;
      this.controls.enablePan = true;
      this.controls.autoRotate = true;
      this.controls.autoRotateSpeed = 0.45;
      this.controls.minDistance = 7;
      this.controls.maxDistance = 23;
      this.controls.target.copy(this.targetGoal);
    } else {
      this.controls = null;
    }

    this.buildEnvironment();
    this.bindEvents();
    this.resizeObserver = new ResizeObserver(() => this.resize());
    this.resizeObserver.observe(this.container);
    this.resize();
    this.animate = this.animate.bind(this);
    this.animate();
  }

  buildEnvironment() {
    const ambient = new THREE.AmbientLight(0xf4f0e8, 1.2);
    const key = new THREE.DirectionalLight(0xffb347, 1.4);
    key.position.set(4, 8, 10);
    const rim = new THREE.DirectionalLight(0x63c9ff, 0.8);
    rim.position.set(-10, 3, -8);
    this.scene.add(ambient, key, rim);

    const ring = new THREE.Mesh(
      new THREE.TorusGeometry(4.8, 0.04, 12, 100),
      new THREE.MeshBasicMaterial({ color: 0xff8b38, transparent: true, opacity: 0.38 })
    );
    ring.rotation.x = Math.PI / 2;
    ring.position.set(0, -2.8, -4.6);
    this.scene.add(ring);

    const grid = new THREE.GridHelper(28, 28, 0xff8b38, 0x26303a);
    grid.position.y = -4.2;
    grid.material.opacity = 0.28;
    grid.material.transparent = true;
    this.scene.add(grid);

    const starsGeometry = new THREE.BufferGeometry();
    const stars = [];
    for (let index = 0; index < 700; index += 1) {
      stars.push(
        (Math.random() - 0.5) * 36,
        (Math.random() - 0.5) * 18,
        -Math.random() * 34
      );
    }
    starsGeometry.setAttribute('position', new THREE.Float32BufferAttribute(stars, 3));
    const starField = new THREE.Points(
      starsGeometry,
      new THREE.PointsMaterial({
        color: 0xf6ead9,
        size: 0.06,
        transparent: true,
        opacity: 0.64,
      })
    );
    this.scene.add(starField);
  }

  bindEvents() {
    this.renderer.domElement.addEventListener('pointermove', (event) => this.onPointerMove(event));
    this.renderer.domElement.addEventListener('click', (event) => this.onClick(event));
  }

  resize() {
    const width = this.container.clientWidth;
    const height = this.container.clientHeight;

    if (!width || !height) {
      return;
    }

    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(width, height, false);
  }

  createCardTexture(donor) {
    const canvas = document.createElement('canvas');
    canvas.width = 960;
    canvas.height = 560;
    const ctx = canvas.getContext('2d');
    const tier = getTier(donor.tier);
    const accent = tier?.accent || '#ffb347';

    ctx.fillStyle = '#06090c';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
    gradient.addColorStop(0, hexToRgba(accent, 0.34));
    gradient.addColorStop(0.45, 'rgba(11, 16, 21, 0.92)');
    gradient.addColorStop(1, 'rgba(6, 9, 12, 0.98)');

    roundRect(ctx, 0, 0, canvas.width, canvas.height, 48);
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.strokeStyle = hexToRgba(accent, 0.52);
    ctx.lineWidth = 5;
    roundRect(ctx, 2.5, 2.5, canvas.width - 5, canvas.height - 5, 46);
    ctx.stroke();

    ctx.fillStyle = 'rgba(255,255,255,0.06)';
    for (let x = 48; x < canvas.width; x += 72) {
      ctx.fillRect(x, 0, 1, canvas.height);
    }
    for (let y = 48; y < canvas.height; y += 72) {
      ctx.fillRect(0, y, canvas.width, 1);
    }

    ctx.fillStyle = hexToRgba(accent, 0.92);
    ctx.fillRect(40, 38, 220, 8);

    ctx.fillStyle = '#f7f2eb';
    ctx.font = '700 58px "Space Grotesk", sans-serif';
    ctx.fillText(donor.company, 40, 142, 620);

    ctx.fillStyle = '#a6b0bc';
    ctx.font = '500 28px "IBM Plex Mono", monospace';
    ctx.fillText((tier?.label || donor.tier).toUpperCase(), 40, 198);

    ctx.font = '500 24px "Space Grotesk", sans-serif';
    ctx.fillStyle = '#dde4ec';
    wrapText(ctx, donor.headline || donor.bio || 'Backer profile ready for spotlight.', 40, 258, 520, 36, 4);

    ctx.fillStyle = '#fff0db';
    ctx.font = '600 34px "IBM Plex Mono", monospace';
    ctx.fillText(formatMoney(donor.amountCents), 40, 492);

    ctx.fillStyle = '#9ba7b5';
    ctx.font = '500 22px "IBM Plex Mono", monospace';
    ctx.fillText('CONTACT // ' + donor.contact.toUpperCase(), 40, 530, 430);

    ctx.fillStyle = hexToRgba(accent, 0.16);
    ctx.beginPath();
    ctx.arc(768, 172, 118, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = accent;
    ctx.beginPath();
    ctx.arc(768, 172, 102, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#1e1107';
    ctx.font = '700 76px "IBM Plex Mono", monospace';
    ctx.textAlign = 'center';
    ctx.fillText(initials(donor.company), 768, 196);
    ctx.textAlign = 'left';

    ctx.fillStyle = 'rgba(255,255,255,0.08)';
    ctx.fillRect(620, 310, 240, 2);
    ctx.fillRect(620, 354, 180, 2);
    ctx.fillRect(620, 398, 210, 2);

    const texture = new THREE.CanvasTexture(canvas);
    texture.needsUpdate = true;
    return texture;
  }

  setDonors(donors) {
    this.cardEntries.forEach((entry) => {
      entry.texture.dispose();
      entry.mesh.material.dispose();
      entry.mesh.geometry.dispose();
      this.group.remove(entry.mesh);
    });
    this.cardEntries = [];

    const list = Array.isArray(donors) ? donors : [];
    const columns = Math.min(5, Math.max(3, Math.ceil(Math.sqrt(list.length || 1) + 1)));
    const rows = Math.max(1, Math.ceil(list.length / columns));
    const spread = Math.min(Math.PI * 1.26, Math.max(Math.PI * 0.92, columns * 0.32));
    const radius = 8.5;

    list.forEach((donor, index) => {
      const texture = this.createCardTexture(donor);
      const material = new THREE.MeshStandardMaterial({
        map: texture,
        roughness: 0.56,
        metalness: 0.08,
        emissive: new THREE.Color(0x120702),
        emissiveIntensity: 0.48,
      });
      const mesh = new THREE.Mesh(new THREE.PlaneGeometry(3.2, 1.88, 1, 1), material);
      const row = Math.floor(index / columns);
      const column = index % columns;
      const ratio = columns === 1 ? 0.5 : column / (columns - 1);
      const angle = -spread / 2 + ratio * spread;
      const y = ((rows - 1) / 2 - row) * 2.28 + ((column % 2) * 0.16);
      const x = Math.sin(angle) * radius;
      const z = Math.cos(angle) * radius - radius - 2.2;

      mesh.position.set(x, y, z);
      mesh.lookAt(new THREE.Vector3(0, y * 0.32, 8));
      mesh.userData.donorId = donor.id;

      this.group.add(mesh);
      this.cardEntries.push({
        donor,
        mesh,
        texture,
        basePosition: mesh.position.clone(),
        baseRotation: mesh.rotation.clone(),
        seed: Math.random() * Math.PI * 2,
      });
    });
  }

  setSelected(id) {
    this.selectedId = id;
  }

  focusSelected() {
    const entry = this.cardEntries.find((card) => card.donor.id === this.selectedId);
    if (!entry) {
      return;
    }

    this.targetGoal.set(entry.basePosition.x * 0.14, entry.basePosition.y * 0.22, entry.basePosition.z + 0.8);
    this.cameraGoal.set(entry.basePosition.x * 0.28, entry.basePosition.y * 0.32 + 1.4, 8.9);
  }

  shuffleCamera() {
    const angle = Math.random() * Math.PI * 0.8 - Math.PI * 0.4;
    this.cameraGoal.set(Math.sin(angle) * 5.6, 1.4 + Math.random() * 1.6, 10.2);
    this.targetGoal.set(0, 0.4, -1.8);
  }

  pick(pointerEvent) {
    const rect = this.renderer.domElement.getBoundingClientRect();
    this.pointer.x = ((pointerEvent.clientX - rect.left) / rect.width) * 2 - 1;
    this.pointer.y = -((pointerEvent.clientY - rect.top) / rect.height) * 2 + 1;
    this.raycaster.setFromCamera(this.pointer, this.camera);
    const intersects = this.raycaster.intersectObjects(this.cardEntries.map((entry) => entry.mesh));
    return intersects[0]?.object?.userData?.donorId || null;
  }

  onPointerMove(event) {
    const donorId = this.pick(event);
    this.renderer.domElement.style.cursor = donorId ? 'pointer' : 'default';
  }

  onClick(event) {
    const donorId = this.pick(event);
    if (donorId) {
      this.onSelect(donorId);
    }
  }

  animate() {
    const elapsed = this.clock.getElapsedTime();

    this.camera.position.lerp(this.cameraGoal, 0.08);

    this.cardEntries.forEach((entry, index) => {
      const selected = entry.donor.id === this.selectedId;
      entry.mesh.position.y = entry.basePosition.y + Math.sin(elapsed * 0.7 + entry.seed) * 0.09;
      entry.mesh.position.z = entry.basePosition.z + (selected ? 0.24 : 0) + Math.cos(elapsed * 0.4 + index) * 0.03;
      entry.mesh.scale.lerp(
        new THREE.Vector3(selected ? 1.08 : 1, selected ? 1.08 : 1, 1),
        0.12
      );
      entry.mesh.material.emissiveIntensity += ((selected ? 0.82 : 0.48) - entry.mesh.material.emissiveIntensity) * 0.08;
    });

    if (this.controls) {
      this.controls.target.lerp(this.targetGoal, 0.08);
      this.controls.update();
    } else if (this.autoRotate) {
      this.group.rotation.y += 0.0016;
      this.camera.lookAt(this.targetGoal);
    } else {
      this.camera.lookAt(this.targetGoal);
    }

    this.renderer.render(this.scene, this.camera);
    requestAnimationFrame(this.animate);
  }
}

function escapeHtml(value = '') {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function initials(value = '') {
  const words = String(value).trim().split(/\s+/).filter(Boolean);
  const result = words.slice(0, 2).map((word) => word[0]?.toUpperCase() || '').join('');
  return result || 'DW';
}

function formatMoney(amountCents = 0) {
  return moneyFormatter.format((Number(amountCents) || 0) / 100);
}

function formatDate(value) {
  if (!value) {
    return 'just now';
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'just now' : timeFormatter.format(date);
}

function hexToRgba(hex, alpha) {
  const sanitized = String(hex).replace('#', '');
  if (sanitized.length !== 6) {
    return `rgba(255, 179, 71, ${alpha})`;
  }
  const red = parseInt(sanitized.slice(0, 2), 16);
  const green = parseInt(sanitized.slice(2, 4), 16);
  const blue = parseInt(sanitized.slice(4, 6), 16);
  return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

function roundRect(ctx, x, y, width, height, radius) {
  ctx.beginPath();
  ctx.moveTo(x + radius, y);
  ctx.lineTo(x + width - radius, y);
  ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
  ctx.lineTo(x + width, y + height - radius);
  ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
  ctx.lineTo(x + radius, y + height);
  ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
  ctx.lineTo(x, y + radius);
  ctx.quadraticCurveTo(x, y, x + radius, y);
  ctx.closePath();
}

function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
  const words = String(text).split(/\s+/).filter(Boolean);
  let line = '';
  let lineCount = 0;

  for (let index = 0; index < words.length; index += 1) {
    const test = line ? `${line} ${words[index]}` : words[index];
    if (ctx.measureText(test).width > maxWidth && line) {
      ctx.fillText(line, x, y + lineCount * lineHeight);
      line = words[index];
      lineCount += 1;
      if (lineCount >= maxLines - 1) {
        break;
      }
    } else {
      line = test;
    }
  }

  if (lineCount < maxLines) {
    const lastLine = words.length > 0 && lineCount >= maxLines - 1 ? `${line.slice(0, Math.max(line.length - 3, 0))}...` : line;
    ctx.fillText(lastLine, x, y + lineCount * lineHeight);
  }
}

function getTier(key) {
  return tierMap.get(key) || tierMap.get('starter') || {
    key: 'starter',
    label: 'Starter',
    amountCents: 9900,
    accent: '#ffb347',
    summary: '',
  };
}

function currentFormTier() {
  return elements.form.querySelector('input[name="tier"]:checked')?.value || 'legend';
}

function selectedDonor() {
  return state.donors.find((donor) => donor.id === state.selectedId) || state.donors[0] || null;
}

function filteredDonors() {
  if (!state.search) {
    return state.donors;
  }

  const query = state.search.toLowerCase();
  return state.donors.filter((donor) =>
    [donor.company, donor.contact, donor.headline, donor.bio, donor.website].join(' ').toLowerCase().includes(query)
  );
}

function showToast(message) {
  elements.toast.textContent = message;
  elements.toast.classList.add('is-visible');
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => elements.toast.classList.remove('is-visible'), 2600);
}

function renderClock() {
  elements.clock.textContent = new Intl.DateTimeFormat('en-US', {
    weekday: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date()).replace(',', '').toUpperCase();
}

function renderTiers() {
  elements.tierOptions.innerHTML = (bootstrap.tiers || []).map((tier, index) => `
    <label class="tier-card" style="--tier-accent:${escapeHtml(tier.accent)}">
      <input type="radio" name="tier" value="${escapeHtml(tier.key)}" ${index === 3 ? 'checked' : ''}>
      <div class="tier-ui">
        <div class="tier-top">
          <strong>${escapeHtml(tier.label)}</strong>
          <span class="tier-price">${escapeHtml(formatMoney(tier.amountCents))}</span>
        </div>
        <p class="field-note">${escapeHtml(tier.summary)}</p>
      </div>
    </label>
  `).join('');

  elements.form.querySelectorAll('input[name="tier"]').forEach((input) => {
    input.addEventListener('change', renderPreview);
  });
}

function renderMetrics() {
  elements.metricRaised.textContent = formatMoney(state.stats.totalRaisedCents);
  elements.metricDonors.textContent = String(state.stats.donorCount);
  elements.metricAverage.textContent = formatMoney(state.stats.averageGiftCents);
  elements.metricTier.textContent = getTier(state.stats.highestTier).label.toLowerCase();
}

function renderFeed() {
  if (!state.feed.length) {
    elements.feedList.innerHTML = '<div class="empty-state">No activity yet.</div>';
    return;
  }

  elements.feedList.innerHTML = state.feed.map((item) => `
    <article class="feed-item">
      <strong>${escapeHtml(item.message)}</strong>
      <time>${escapeHtml(formatDate(item.createdAt))}</time>
    </article>
  `).join('');
}

function renderPreview() {
  const tier = getTier(currentFormTier());
  const company = elements.companyInput.value.trim() || 'Omacon sponsor';
  const headline = elements.headlineInput.value.trim() || 'Sponsorship card preview for the Omacon wall.';
  const website = elements.websiteInput.value.trim() || 'awaiting website';

  elements.previewTier.textContent = tier.label;
  elements.previewCompany.textContent = company;
  elements.previewHeadline.textContent = headline;
  elements.previewAmount.textContent = formatMoney(tier.amountCents);
  elements.previewWebsite.textContent = website;
  elements.previewAvatar.textContent = initials(company);

  if (state.uploadedLogoUrl) {
    elements.previewAvatar.style.backgroundImage = `url("${state.uploadedLogoUrl}")`;
    elements.previewAvatar.style.color = 'transparent';
  } else {
    elements.previewAvatar.style.backgroundImage = '';
    elements.previewAvatar.style.color = '#201009';
  }
}

function renderDirectory() {
  const donors = filteredDonors();

  if (!donors.length) {
    elements.donorList.innerHTML = '<div class="empty-state">No sponsors match that search.</div>';
    return;
  }

  elements.donorList.innerHTML = donors.map((donor) => {
    const active = donor.id === state.selectedId;
    const tier = getTier(donor.tier);
    const avatar = donor.image
      ? `<img class="donor-avatar" src="${escapeHtml(donor.image)}" alt="${escapeHtml(donor.company)} logo">`
      : `<div class="donor-avatar">${escapeHtml(initials(donor.company))}</div>`;

    return `
      <button type="button" class="donor-row ${active ? 'is-active' : ''}" data-id="${escapeHtml(donor.id)}">
        ${avatar}
        <div class="donor-copy">
          <strong>${escapeHtml(donor.company)}</strong>
          <span>${escapeHtml(donor.headline || donor.bio || tier.summary)}</span>
        </div>
        <span class="donor-amount">${escapeHtml(formatMoney(donor.amountCents))}</span>
      </button>
    `;
  }).join('');

  elements.donorList.querySelectorAll('.donor-row').forEach((button) => {
    button.addEventListener('click', () => {
      setSelected(button.dataset.id);
    });
  });
}

function renderDetailPanel(target, donor) {
  if (!donor) {
    target.innerHTML = '<div class="empty-state">Select a sponsor to inspect its card.</div>';
    return;
  }

  const tier = getTier(donor.tier);
  const image = donor.image
    ? `<img src="${escapeHtml(donor.image)}" alt="${escapeHtml(donor.company)} logo">`
    : escapeHtml(initials(donor.company));

  const websiteLabel = donor.website ? safeHost(donor.website) : 'not provided';
  const websiteLink = donor.website
    ? `<a href="${escapeHtml(donor.website)}" target="_blank" rel="noreferrer">${escapeHtml(websiteLabel)}</a>`
    : '<span>not provided</span>';

  target.innerHTML = `
    <div class="detail-head">
      <div>
        <p class="tile-kicker">selected sponsor</p>
        <h3>${escapeHtml(donor.company)}</h3>
      </div>
      <div class="detail-image">${image}</div>
    </div>
    <div class="detail-badge" style="background:${hexToRgba(tier.accent, 0.18)}; color:${tier.accent}; border-color:${hexToRgba(tier.accent, 0.35)}">${escapeHtml(tier.label)} // ${escapeHtml(formatMoney(donor.amountCents))}</div>
    <p>${escapeHtml(donor.headline || donor.bio || tier.summary)}</p>
    <div class="detail-grid">
      <article class="detail-card">
        <span class="detail-label">contact</span>
        <strong>${escapeHtml(donor.contact)}</strong>
      </article>
      <article class="detail-card">
        <span class="detail-label">joined</span>
        <strong>${escapeHtml(formatDate(donor.joinedAt))}</strong>
      </article>
      <article class="detail-card">
        <span class="detail-label">website</span>
        ${websiteLink}
      </article>
      <article class="detail-card">
        <span class="detail-label">payment state</span>
        <strong>${escapeHtml(donor.paymentStatus || 'seeded')}</strong>
      </article>
    </div>
    <p>${escapeHtml(donor.bio || 'No sponsor note was supplied yet.')}</p>
  `;
}

function renderSelectionCopy() {
  const donor = selectedDonor();
  if (!donor) {
    elements.overlayCompany.textContent = 'No sponsor selected';
    elements.overlayTier.textContent = 'Load a demo sponsor or finish a Stripe checkout.';
    elements.compactCompany.textContent = 'No sponsor selected';
    elements.compactTier.textContent = 'Waiting for input';
    return;
  }

  const tier = getTier(donor.tier);
  elements.overlayCompany.textContent = donor.company;
  elements.overlayTier.textContent = `${tier.label} // ${formatMoney(donor.amountCents)}`;
  elements.compactCompany.textContent = donor.company;
  elements.compactTier.textContent = `${tier.label} // joined ${formatDate(donor.joinedAt)}`;
}

function safeHost(url) {
  try {
    return new URL(url).host;
  } catch {
    return url;
  }
}

function setSelected(id) {
  if (!state.donors.some((donor) => donor.id === id)) {
    return;
  }

  state.selectedId = id;
  renderDirectory();
  renderSelectionCopy();
  renderDetailPanel(elements.detailPanel, selectedDonor());
  renderDetailPanel(elements.modalDetailPanel, selectedDonor());
  miniViewer.setSelected(id);
  fullViewer.setSelected(id);
}

async function uploadLogo(file) {
  const formData = new FormData();
  formData.append('logo', file);
  elements.logoFileName.textContent = file.name;
  elements.logoStatus.textContent = 'Uploading logo...';
  state.uploadingLogo = true;

  try {
    const response = await fetch('/api/upload-logo.php', {
      method: 'POST',
      body: formData,
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error || 'Logo upload failed.');
    }

    state.uploadedLogoUrl = payload.url || '';
    elements.logoStatus.textContent = 'Logo saved locally and ready for checkout metadata.';
    renderPreview();
  } catch (error) {
    state.uploadedLogoUrl = '';
    elements.logoFileName.textContent = 'upload failed';
    elements.logoStatus.textContent = error.message;
    showToast(error.message);
  } finally {
    state.uploadingLogo = false;
  }
}

function formPayload() {
  return {
    company: elements.companyInput.value.trim(),
    contact: elements.contactInput.value.trim(),
    email: elements.emailInput.value.trim(),
    website: elements.websiteInput.value.trim(),
    headline: elements.headlineInput.value.trim(),
    bio: elements.bioInput.value.trim(),
    tier: currentFormTier(),
    image: state.uploadedLogoUrl,
  };
}

async function refreshData(showFeedback = false) {
  if (state.refreshing) {
    return;
  }

  state.refreshing = true;
  elements.refreshBtn.disabled = true;

  try {
    const response = await fetch('/api/donors.php', { headers: { Accept: 'application/json' } });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error || 'Failed to refresh donors.');
    }

    state.donors = payload.donors || [];
    state.feed = payload.feed || [];
    state.stats = payload.stats || state.stats;

    if (!state.donors.some((donor) => donor.id === state.selectedId)) {
      state.selectedId = state.donors[0]?.id || null;
    }

    renderMetrics();
    renderFeed();
    renderDirectory();
    renderSelectionCopy();
    renderDetailPanel(elements.detailPanel, selectedDonor());
    renderDetailPanel(elements.modalDetailPanel, selectedDonor());
    miniViewer.setDonors(state.donors);
    fullViewer.setDonors(state.donors);
    miniViewer.setSelected(state.selectedId);
    fullViewer.setSelected(state.selectedId);

    if (showFeedback) {
      showToast('Data refreshed from PHP + SQLite.');
    }
  } catch (error) {
    showToast(error.message);
  } finally {
    state.refreshing = false;
    elements.refreshBtn.disabled = false;
  }
}

async function handleSubmit(event) {
  event.preventDefault();

  if (!state.stripeReady) {
    showToast('Stripe is not configured yet. Add Stripe keys in .env first.');
    return;
  }

  if (state.uploadingLogo) {
    showToast('Wait for the logo upload to finish first.');
    return;
  }

  if (!elements.form.reportValidity()) {
    return;
  }

  elements.checkoutButton.disabled = true;
  elements.checkoutButton.textContent = 'opening checkout...';

  try {
    const response = await fetch('/api/create-checkout-session.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(formPayload()),
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error || 'Failed to create the Stripe Checkout Session.');
    }

    if (!payload.url) {
      throw new Error('Stripe did not return a checkout URL.');
    }

    window.location.href = payload.url;
  } catch (error) {
    showToast(error.message);
    elements.checkoutButton.disabled = false;
    elements.checkoutButton.textContent = 'open sponsor checkout';
  }
}

async function handlePaymentReturn() {
  if (viewState.payment === 'cancelled') {
    showToast('Stripe checkout was cancelled before payment.');
    history.replaceState({}, '', window.location.pathname);
    return;
  }

  if (viewState.payment !== 'success' || !viewState.sessionId) {
    return;
  }

  try {
    const response = await fetch(`/api/session-status.php?session_id=${encodeURIComponent(viewState.sessionId)}`, {
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.error || 'Failed to confirm the Stripe session.');
    }

    if (payload.paymentStatus === 'paid') {
      showToast('Stripe payment confirmed. Syncing the Omacon wall.');
      await refreshData(false);
      if (payload.donor?.id) {
        setSelected(payload.donor.id);
      }
    } else {
      showToast('Stripe session returned, but the payment is not marked paid yet.');
    }
  } catch (error) {
    showToast(error.message);
  } finally {
    history.replaceState({}, '', window.location.pathname);
  }
}

function randomDemoFill() {
  const sample = demoPayloads[Math.floor(Math.random() * demoPayloads.length)];
  elements.companyInput.value = sample.company;
  elements.contactInput.value = sample.contact;
  elements.emailInput.value = sample.email;
  elements.websiteInput.value = sample.website;
  elements.headlineInput.value = sample.headline;
  elements.bioInput.value = sample.bio;

  const tierInput = elements.form.querySelector(`input[name="tier"][value="${sample.tier}"]`);
  if (tierInput) {
    tierInput.checked = true;
  }

  renderPreview();
}

function openViewer(event) {
  event?.preventDefault();
  elements.viewerModal.classList.add('is-open');
  elements.viewerModal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open');
  if (window.location.hash !== '#viewerModal') {
    history.replaceState({}, '', `${window.location.pathname}#viewerModal`);
  }
  fullViewer.resize();
  renderDetailPanel(elements.modalDetailPanel, selectedDonor());
}

function closeViewer(event) {
  event?.preventDefault();
  elements.viewerModal.classList.remove('is-open');
  elements.viewerModal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  if (window.location.hash === '#viewerModal') {
    history.replaceState({}, '', window.location.pathname);
  }
}

function bindEvents() {
  renderClock();
  window.setInterval(renderClock, 60_000);

  [
    elements.companyInput,
    elements.contactInput,
    elements.websiteInput,
    elements.headlineInput,
    elements.bioInput,
  ].forEach((input) => input.addEventListener('input', renderPreview));

  elements.logoFileInput.addEventListener('change', async (event) => {
    const [file] = event.target.files || [];
    if (!file) {
      state.uploadedLogoUrl = '';
      elements.logoFileName.textContent = 'no file selected';
      elements.logoStatus.textContent = 'Optional. Add a logo, avatar, or distro sigil before checkout.';
      renderPreview();
      return;
    }
    await uploadLogo(file);
  });

  elements.form.addEventListener('submit', handleSubmit);
  elements.demoFillBtn.addEventListener('click', randomDemoFill);
  elements.refreshBtn.addEventListener('click', () => refreshData(true));
  elements.searchInput.addEventListener('input', (event) => {
    state.search = event.target.value.trim();
    renderDirectory();
  });

  document.querySelectorAll('[data-action="open-viewer"]').forEach((button) => {
    button.addEventListener('click', openViewer);
  });

  elements.closeViewerBtn.addEventListener('click', closeViewer);
  elements.viewerModal.addEventListener('click', (event) => {
    if (event.target === elements.viewerModal) {
      closeViewer();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && elements.viewerModal.classList.contains('is-open')) {
      closeViewer();
    }
  });

  elements.focusSelectedBtn.addEventListener('click', () => fullViewer.focusSelected());
  elements.shuffleCameraBtn.addEventListener('click', () => fullViewer.shuffleCamera());
  elements.miniFocusBtn.addEventListener('click', () => miniViewer.focusSelected());

  window.setInterval(() => refreshData(false), 25_000);
}

const miniViewer = new DonorWallViewer(elements.miniViewer, {
  interactive: false,
  autoRotate: true,
  onSelect: (id) => setSelected(id),
});

const fullViewer = new DonorWallViewer(elements.fullViewer, {
  interactive: true,
  autoRotate: true,
  onSelect: (id) => setSelected(id),
});

function initialize() {
  renderTiers();
  renderPreview();
  renderMetrics();
  renderFeed();
  renderDirectory();
  renderSelectionCopy();
  renderDetailPanel(elements.detailPanel, selectedDonor());
  renderDetailPanel(elements.modalDetailPanel, selectedDonor());
  miniViewer.setDonors(state.donors);
  fullViewer.setDonors(state.donors);
  miniViewer.setSelected(state.selectedId);
  fullViewer.setSelected(state.selectedId);
  bindEvents();
  handlePaymentReturn();
}

initialize();

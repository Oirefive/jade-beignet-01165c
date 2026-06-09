const $ = (q, root=document) => root.querySelector(q);
const $$ = (q, root=document) => Array.from(root.querySelectorAll(q));

const state = { menu: [], category: "Все", query: "" };

const escapeHtml = (value = "") => String(value).replace(/[&<>"']/g, (m) => ({
  "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
}[m]));

function imageWithFallback(src, fallback, alt) {
  return `<img src="${escapeHtml(src || fallback)}" data-fallback="${escapeHtml(fallback)}" alt="${escapeHtml(alt)}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=this.dataset.fallback;">`;
}

async function loadJson(url, fallback) {
  try {
    const response = await fetch(url, { cache: "no-store" });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return await response.json();
  } catch (error) {
    console.warn(`Не удалось загрузить ${url}`, error);
    return fallback;
  }
}

function renderChips(menu) {
  const categories = ["Все", ...new Set(menu.map(item => item.category))];
  $(".chips").innerHTML = categories.map(cat =>
    `<button class="chip ${cat === state.category ? "is-active" : ""}" type="button" data-category="${escapeHtml(cat)}">${escapeHtml(cat)}</button>`
  ).join("");

  $$(".chip").forEach(button => {
    button.addEventListener("click", () => {
      state.category = button.dataset.category;
      renderChips(state.menu);
      renderMenu();
    });
  });
}

function renderMenu() {
  const query = state.query.trim().toLowerCase();
  const filtered = state.menu.filter(item => {
    const categoryOk = state.category === "Все" || item.category === state.category;
    const queryOk = !query || `${item.title} ${item.description} ${item.category}`.toLowerCase().includes(query);
    return categoryOk && queryOk;
  });

  $("#menu-count").textContent = `${filtered.length} позиций`;

  $("#menu-grid").innerHTML = filtered.map(item => `
    <article class="card">
      <div class="card__photo">
        ${imageWithFallback(item.image, item.fallback, item.title)}
        <span class="badge">${escapeHtml(item.badge || item.category)}</span>
      </div>
      <div class="card__body">
        <div>
          <h3>${escapeHtml(item.title)}</h3>
          <p>${escapeHtml(item.description || "")}</p>
        </div>
        <div class="card__foot">
          <span class="price">${escapeHtml(item.price)}</span>
          <span class="weight">${escapeHtml(item.weight)}</span>
        </div>
      </div>
    </article>
  `).join("") || `<p class="muted">Ничего не найдено. Попробуйте другой запрос.</p>`;
}

function renderPhotos(data) {
  const items = data.items || [];
  $("#gallery-grid").innerHTML = items.map(item => `
    <figure class="photo-card">
      ${imageWithFallback(item.src, item.fallback, item.title)}
      <span class="photo-card__text">
        <strong>${escapeHtml(item.title)}</strong>
        <span>${escapeHtml(item.caption)}</span>
      </span>
    </figure>
  `).join("");
}

function initNav() {
  const toggle = $(".menu-toggle");
  const nav = $(".nav");
  toggle?.addEventListener("click", () => {
    const open = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", String(open));
  });

  $$('a[href^="#"]').forEach(link => {
    link.addEventListener("click", event => {
      const target = $(link.getAttribute("href"));
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      nav?.classList.remove("is-open");
      toggle?.setAttribute("aria-expanded", "false");
    });
  });
}

function initCopy() {
  const copy = $("#copy-address");
  copy?.addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText("Республика Крым, Феодосия, улица Челнокова, 80В");
      const toast = $("#toast");
      toast.classList.add("show");
      setTimeout(() => toast.classList.remove("show"), 2200);
    } catch (e) {
      window.prompt("Скопируйте адрес:", "Республика Крым, Феодосия, улица Челнокова, 80В");
    }
  });
}

async function init() {
  $("#year").textContent = new Date().getFullYear();
  initNav();
  initCopy();

  const embeddedMenu = Array.isArray(window.__MENU_DATA__)
    ? window.__MENU_DATA__
    : Array.isArray(window.__MENU_DATA__?.value) ? window.__MENU_DATA__.value : null;
  state.menu = embeddedMenu || await loadJson("assets/data/menu.json", []);
  renderChips(state.menu);
  renderMenu();

  $("#search").addEventListener("input", (event) => {
    state.query = event.target.value;
    renderMenu();
  });

  const photos = window.__PHOTOS_DATA__?.items ? window.__PHOTOS_DATA__ : await loadJson("assets/data/photos.json", { items: [] });
  renderPhotos(photos);
}

init();

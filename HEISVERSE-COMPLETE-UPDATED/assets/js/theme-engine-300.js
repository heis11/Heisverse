/**
 * HEISVERSE — 300 Themes Engine & Gallery Controller
 * Manages 300 unique palettes, live previews, category filtering, search,
 * favorites, and persistent user synchronization.
 */

'use strict';

const Theme300 = {
  themes: [],
  currentThemeId: 'cine-01',
  activeCategory: 'All',
  favorites: [],
  recent: [],

  async init() {
    try {
      const res = await fetch('assets/data/themes-300.json');
      this.themes = await res.json();
    } catch (e) {
      console.warn('[HEISVERSE] Could not load themes-300.json:', e);
      return;
    }

    this.favorites = JSON.parse(localStorage.getItem('heisverse_fav_themes') || '[]');
    this.recent = JSON.parse(localStorage.getItem('heisverse_recent_themes') || '[]');

    const savedId = localStorage.getItem('heisverse_theme_id') || 'cine-01';
    this.applyTheme(savedId, false);

    this.bindGalleryEvents();
  },

  applyTheme(themeId, persist = true) {
    const theme = this.themes.find(t => t.id === themeId);
    if (!theme) return;

    this.currentThemeId = themeId;
    const root = document.documentElement;

    root.style.setProperty('--bg-primary', theme.bgPrimary);
    root.style.setProperty('--bg-secondary', theme.bgSecondary);
    root.style.setProperty('--bg-glass', theme.bgGlass);
    root.style.setProperty('--border-glass', theme.borderGlass);
    root.style.setProperty('--text-main', theme.textMain);
    root.style.setProperty('--text-muted', theme.textMuted);
    root.style.setProperty('--text-dim', theme.textDim);
    root.style.setProperty('--accent', theme.accent);
    root.style.setProperty('--accent-hover', theme.accentHover);
    root.style.setProperty('--accent-glow', theme.accentGlow);
    root.style.setProperty('--sky-gradient', theme.skyGradient);

    if (persist) {
      localStorage.setItem('heisverse_theme_id', themeId);
      this.addRecent(themeId);

      // Save to user account via backend API
      fetch('api.php?action=save_theme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: themeId })
      }).catch(() => {});

      if (typeof showToast === 'function') {
        showToast(`Theme applied: ${theme.name} (${theme.category})`);
      }
    }
  },

  applyCustomAccent(hex) {
    document.documentElement.style.setProperty('--accent', hex);
    document.documentElement.style.setProperty('--accent-glow', hex + '55');
    localStorage.setItem('heisverse_custom_accent', hex);
    fetch('api.php?action=save_accent', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accent: hex })
    }).catch(() => {});
  },

  addRecent(id) {
    this.recent = [id, ...this.recent.filter(x => x !== id)].slice(0, 10);
    localStorage.setItem('heisverse_recent_themes', JSON.stringify(this.recent));
  },

  toggleFavorite(id, e) {
    if (e) e.stopPropagation();
    if (this.favorites.includes(id)) {
      this.favorites = this.favorites.filter(x => x !== id);
    } else {
      this.favorites.push(id);
    }
    localStorage.setItem('heisverse_fav_themes', JSON.stringify(this.favorites));
    this.renderGallery();
  },

  bindGalleryEvents() {
    const searchInput = document.getElementById('themeSearchInput');
    if (searchInput) {
      searchInput.addEventListener('input', () => this.renderGallery());
    }

    const catSelect = document.getElementById('themeCategorySelect');
    if (catSelect) {
      catSelect.addEventListener('change', (e) => {
        this.activeCategory = e.target.value;
        this.renderGallery();
      });
    }

    const customColor = document.getElementById('themeCustomAccentInput');
    if (customColor) {
      customColor.addEventListener('input', (e) => this.applyCustomAccent(e.target.value));
    }
  },

  renderGallery() {
    const container = document.getElementById('themeCardsContainer');
    if (!container) return;

    const query = (document.getElementById('themeSearchInput')?.value || '').toLowerCase().trim();
    const cat = this.activeCategory;

    let filtered = this.themes.filter(t => {
      const matchCat = (cat === 'All' || t.category === cat);
      const matchQuery = !query || t.name.toLowerCase().includes(query) || t.category.toLowerCase().includes(query) || t.id.toLowerCase().includes(query);
      return matchCat && matchQuery;
    });

    container.innerHTML = filtered.map(t => {
      const isFav = this.favorites.includes(t.id);
      const isActive = (t.id === this.currentThemeId);
      return `
        <div class="theme-gallery-card ${isActive ? 'active-theme' : ''}" onclick="Theme300.applyTheme('${t.id}')">
          <div class="theme-card-preview" style="background:${t.skyGradient}; border:1px solid ${t.borderGlass}">
            <div class="theme-swatches">
              <span class="swatch" style="background:${t.accent}"></span>
              <span class="swatch" style="background:${t.bgSecondary}"></span>
              <span class="swatch" style="background:${t.textMain}"></span>
            </div>
            <button class="theme-fav-btn" onclick="Theme300.toggleFavorite('${t.id}', event)" title="Favorite">
              ${isFav ? '★' : '☆'}
            </button>
          </div>
          <div class="theme-card-body">
            <div class="theme-card-title">${t.name}</div>
            <div class="theme-card-cat">${t.category} • <small>${t.id}</small></div>
          </div>
        </div>
      `;
    }).join('') || '<div class="empty-gallery">No themes match your search query.</div>';

    const countBadge = document.getElementById('themeCountBadge');
    if (countBadge) {
      countBadge.innerText = `${filtered.length} of ${this.themes.length} Themes`;
    }
  }
};

document.addEventListener('DOMContentLoaded', () => {
  Theme300.init();
});

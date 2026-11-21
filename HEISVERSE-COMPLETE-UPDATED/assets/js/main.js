/**
 * HEISVERSE â€” Main Interactive Frontend Engine
 * Includes expandable search with live suggestions, voice dictation,
 * text-to-speech controls, multi-source player, and 300 themes gallery.
 */

'use strict';

// 1. Toast Notification Service
function showToast(message, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerText = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// 2. HEISVERSE Universal Video Player
let heisverseHlsInstance = null;
let heisverseDashInstance = null;

function destroyHeisversePlayer() {
  if (heisverseHlsInstance) {
    try { heisverseHlsInstance.destroy(); } catch (error) {}
    heisverseHlsInstance = null;
  }

  if (heisverseDashInstance) {
    try { heisverseDashInstance.reset(); } catch (error) {}
    heisverseDashInstance = null;
  }
}

function initializeHeisversePlayer() {
  const video = document.getElementById('heisverseHtml5Player');
  if (!video) return;

  const type = (video.dataset.streamType || '').toLowerCase();
  const url = video.dataset.streamUrl || '';
  const resume = Math.max(0, Number(video.dataset.resume || 0));

  let resumeApplied = false;

  function restorePosition() {
    if (resumeApplied || resume <= 0) return;
    if (!Number.isFinite(video.duration) || video.duration <= 0) return;

    try {
      video.currentTime = Math.min(resume, Math.max(0, video.duration - 1));
      resumeApplied = true;
    } catch (error) {
      console.warn('HEISVERSE could not restore playback position.');
    }
  }

  // HLS streaming (.m3u8)
  if (type === 'hls') {
    if (window.Hls && Hls.isSupported()) {
      const hls = new Hls();
      heisverseHlsInstance = hls;

      hls.on(Hls.Events.MANIFEST_PARSED, restorePosition);

      hls.on(Hls.Events.ERROR, function (event, data) {
        if (data && data.fatal) {
          showToast('This HLS stream could not be played.', 'error');
        }
      });

      hls.loadSource(url);
      hls.attachMedia(video);
      return;
    }

    // Safari and browsers with native HLS support
    if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = url;
      video.addEventListener('loadedmetadata', restorePosition, { once: true });
      video.load();
      return;
    }

    showToast('This browser does not support HLS playback.', 'error');
    return;
  }

  // MPEG-DASH streaming (.mpd)
  if (type === 'dash') {
    if (!window.dashjs || typeof dashjs.MediaPlayer !== 'function') {
      showToast('DASH player library is unavailable.', 'error');
      return;
    }

    try {
      const player = dashjs.MediaPlayer().create();
      heisverseDashInstance = player;

      if (dashjs.MediaPlayer.events &&
          dashjs.MediaPlayer.events.STREAM_INITIALIZED) {
        player.on(
          dashjs.MediaPlayer.events.STREAM_INITIALIZED,
          restorePosition
        );
      }

      player.initialize(video, url, false);
    } catch (error) {
      showToast('Could not initialize DASH playback.', 'error');
      console.error('HEISVERSE DASH initialization failed.');
    }

    return;
  }

  // Direct MP4, WebM and other supported HTML5 video sources
  video.addEventListener('loadedmetadata', restorePosition, { once: true });

  if (video.readyState >= 1) {
    restorePosition();
  }
}

function switchMovieSource(movieId, sourceId) {
  const container = document.getElementById('playerContainer');
  if (!container) return;

  destroyHeisversePlayer();

  container.innerHTML =
    '<div style="padding:3rem;text-align:center;"><p>Switching authorized stream...</p></div>';

  fetch(`api.php?action=get_player&movie_id=${encodeURIComponent(movieId)}&source_id=${encodeURIComponent(sourceId)}`)
    .then(res => res.json())
    .then(data => {
      if (data.success && data.html) {
        container.innerHTML = data.html;
        initializeHeisversePlayer();
        attachWatchTracking(movieId);
      } else {
        showToast(data.message || 'Stream source unavailable.', 'error');
      }
    })
    .catch(() => showToast('Error switching video source.', 'error'));
}

function handleQualityChange(qualityValue) {
  const video = document.getElementById('heisverseHtml5Player');

  if (heisverseHlsInstance) {
    if (String(qualityValue).toLowerCase() === 'auto') {
      heisverseHlsInstance.currentLevel = -1;
      showToast('HLS automatic quality selected.');
    } else {
      const level = Number(qualityValue);
      if (Number.isInteger(level) && level >= 0 &&
          level < heisverseHlsInstance.levels.length) {
        heisverseHlsInstance.currentLevel = level;
        showToast('HLS quality updated.');
      }
    }
    return;
  }

  const iframe = document.getElementById('heisverseIframePlayer');

  if (iframe && iframe.contentWindow) {
    iframe.contentWindow.postMessage(JSON.stringify({
      event: 'command',
      func: 'setPlaybackQuality',
      args: [qualityValue]
    }), '*');
  } else if (video) {
    showToast('Quality selection depends on the video source.');
  }
}

function setPlayerSpeed(speed) {
  const video = document.getElementById('heisverseHtml5Player');
  const rate = Number(speed);

  if (video && Number.isFinite(rate) && rate > 0) {
    video.playbackRate = rate;
    showToast(`Speed set to: ${rate}x`);
  }
}

function openPlayerModal(movieId) {
  const modal = document.getElementById('playerModal');
  const container = document.getElementById('playerContainer');
  if (!modal || !container) return;

  destroyHeisversePlayer();

  container.innerHTML =
    '<div style="padding:3rem;text-align:center;"><p>Loading authorized cinematic stream...</p></div>';

  modal.classList.add('active');

  fetch(`api.php?action=get_player&movie_id=${encodeURIComponent(movieId)}`)
    .then(res => res.json())
    .then(data => {
      if (data.success && data.html) {
        container.innerHTML = data.html;
        initializeHeisversePlayer();
        attachWatchTracking(movieId);
      } else {
        container.innerHTML =
          '<div style="padding:2.5rem;text-align:center;"><p>' +
          (data.message || 'No video source.') +
          '</p></div>';
      }
    })
    .catch(() => {
      container.innerHTML =
        '<p style="padding:2rem;">Could not load player.</p>';
    });
}

function closeModals() {
  destroyHeisversePlayer();

  document.querySelectorAll('.modal-backdrop').forEach(m =>
    m.classList.remove('active')
  );

  const playerContainer = document.getElementById('playerContainer');
  if (playerContainer) playerContainer.innerHTML = '';

  const trailerContainer = document.getElementById('trailerContainer');
  if (trailerContainer) trailerContainer.innerHTML = '';

  if (window.TextToSpeech) TextToSpeech.stop();
}

// 3. Watch Position Tracking
function attachWatchTracking(movieId) {
  const video = document.getElementById('heisverseHtml5Player');
  if (!video) return;

  let lastSaved = 0;
  let saving = false;

  async function saveProgress(force = false) {
    const position = Math.floor(video.currentTime || 0);
    const duration = Math.floor(video.duration || 0);

    if (position <= 0 || !Number.isFinite(position)) return;
    if (!force && position - lastSaved < 15) return;
    if (saving) return;

    saving = true;

    try {
      const response = await fetch('api.php?action=track_watch', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          movie_id: movieId,
          position: position,
          duration: duration
        })
      });

      const data = await response.json();

      if (response.ok && data.success) {
        lastSaved = Math.max(lastSaved, position);
      }
    } catch (error) {
      console.warn('HEISVERSE watch progress could not be saved.');
    } finally {
      saving = false;
    }
  }

  video.addEventListener('timeupdate', () => saveProgress(false));
  video.addEventListener('pause', () => saveProgress(true));
  video.addEventListener('ended', () => saveProgress(true));

  window.addEventListener('pagehide', () => {
    const position = Math.floor(video.currentTime || 0);
    const duration = Math.floor(video.duration || 0);

    if (position <= 0 || position <= lastSaved) return;

    const payload = JSON.stringify({
      movie_id: movieId,
      position: position,
      duration: duration
    });

    if (navigator.sendBeacon) {
      navigator.sendBeacon(
        'api.php?action=track_watch',
        new Blob([payload], { type: 'application/json' })
      );
    }
  }, { once: true });
}

// 4. Watchlist Toggle
function toggleWatchlist(movieId, buttonEl) {
  fetch('api.php?action=toggle_watchlist', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
    body: JSON.stringify({ movie_id: movieId })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (data.in_list) {
        buttonEl.innerHTML = 'âœ“ In Watchlist';
        buttonEl.classList.add('btn-primary');
        buttonEl.classList.remove('btn-glass');
        showToast('Added to your watchlist!');
      } else {
        buttonEl.innerHTML = 'ï¼‹ Watchlist';
        buttonEl.classList.remove('btn-primary');
        buttonEl.classList.add('btn-glass');
        showToast('Removed from your watchlist.');
      }
    } else {
      showToast(data.message || 'Please log in to manage your watchlist.', 'warning');
    }
  })
  .catch(() => showToast('Could not update watchlist.', 'error'));
}

// 5. Global search: dropdown suggestions (local library + real TMDB results)
function escapeHtml(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

function initLiveSearch() {
  const input = document.getElementById('globalSearchInput');
  const dropdown = document.getElementById('searchSuggestionsDropdown');
  if (!input || !dropdown) return;

  let debounceTimer = null;
  let requestId = 0;

  const hide = () => { dropdown.style.display = 'none'; };
  const goSearch = () => {
    const q = input.value.trim();
    if (q.length > 0) window.location.href = 'search.php?q=' + encodeURIComponent(q);
  };

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); goSearch(); }
    if (e.key === 'Escape') hide();
  });

  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const query = input.value.trim();
    if (query.length < 2) { hide(); return; }

    dropdown.innerHTML = '<div class="search-state">Searchingâ€¦</div>';
    dropdown.style.display = 'block';

    debounceTimer = setTimeout(() => {
      const myId = ++requestId;
      fetch('api.php?action=search_suggestions&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((data) => {
          if (myId !== requestId) return; // a newer search replaced this one
          const rows = [];
          (data.local || []).forEach((m) => {
            rows.push(`<div class="search-result-row" role="option" onclick="${document.getElementById('playerModal') ? 'openPlayerModal(' + Number(m.id) + ')' : "window.location.href='discover.php?src=library'"}">
              <div class="search-thumb search-thumb-empty" aria-hidden="true">ðŸŽžï¸</div>
              <div style="flex:1; min-width:0;">
                <div class="search-title">${escapeHtml(m.title)}</div>
                <div class="search-sub">HEISVERSE Library â€¢ ${escapeHtml(m.category)} â€¢ ${Number(m.released) || ''}</div>
              </div></div>`);
          });
          (data.results || []).forEach((r) => {
            const thumb = r.poster
              ? `<img class="search-thumb" src="${escapeHtml(r.poster)}" alt="" loading="lazy">`
              : '<div class="search-thumb search-thumb-empty" aria-hidden="true"></div>';
            rows.push(`<a class="search-result-row" role="option" href="${escapeHtml(r.url)}">
              ${thumb}
              <div style="flex:1; min-width:0;">
                <div class="search-title">${escapeHtml(r.title)}</div>
                <div class="search-sub">${escapeHtml(r.label)}${r.year ? ' â€¢ ' + escapeHtml(r.year) : ''}${r.rating > 0 ? ' â€¢ â˜… ' + Number(r.rating).toFixed(1) : ''}</div>
              </div></a>`);
          });

          if (rows.length === 0) {
            const msg = data.error ? escapeHtml(data.error) : 'No matching titles found.';
            dropdown.innerHTML = `<div class="search-state">${msg}</div>`;
          } else {
            rows.push(`<a class="search-result-row search-all" href="search.php?q=${encodeURIComponent(query)}">See all results for â€œ${escapeHtml(query)}â€ â†’</a>`);
            dropdown.innerHTML = rows.join('');
          }
          dropdown.style.display = 'block';
        })
        .catch(() => {
          if (myId !== requestId) return;
          dropdown.innerHTML = '<div class="search-state">Search is unavailable right now. Please try again.</div>';
        });
    }, 300);
  });

  document.addEventListener('click', (e) => {
    if (!input.contains(e.target) && !dropdown.contains(e.target)) hide();
  });
}

// 5b. Search results page (search.php): filters, debounce, Load More
function initSearchPage() {
  const grid = document.getElementById('searchResults');
  const form = document.getElementById('searchPageForm');
  if (!grid || !form || !window.HEIS_SEARCH) return;

  const input = document.getElementById('searchPageInput');
  const status = document.getElementById('searchStatus');
  const message = document.getElementById('searchMessage');
  const moreBtn = document.getElementById('searchLoadMore');
  const chips = document.querySelectorAll('#searchFilters [data-filter]');
  let wl = [];
  try { wl = JSON.parse(grid.dataset.wl || '[]'); } catch (e) { wl = []; }

  let filter = window.HEIS_SEARCH.filter || 'all';
  let page = 1, totalPages = 0, reqId = 0, timer = null, loading = false;

  const skeletons = (n) => Array.from({ length: n }, () =>
    '<div class="movie-card skeleton-card" aria-hidden="true"><div class="skeleton skeleton-poster"></div><div class="movie-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div></div></div>'
  ).join('');

  function card(r) {
    const inList = wl.indexOf(r.media_type + ':' + r.id) !== -1;
    const art = r.poster
      ? `<img class="tmdb-img" src="${escapeHtml(r.poster)}" srcset="${escapeHtml(r.poster_srcset)}" sizes="(max-width:600px) 45vw, 230px" alt="${escapeHtml(r.title)}" loading="lazy" decoding="async" width="342" height="513">`
      : `<div class="poster-fallback" aria-hidden="true"><span>${escapeHtml(r.title)}</span></div>`;
    return `<article class="movie-card tmdb-card">
      <a class="movie-art poster" href="${escapeHtml(r.url)}" aria-label="${escapeHtml(r.title)} â€” details">${art}
        <span class="movie-badge-category">${escapeHtml(r.label.toUpperCase())}</span>
        ${r.rating > 0 ? `<span class="rating-pill">â˜… ${Number(r.rating).toFixed(1)}</span>` : ''}
      </a>
      <div class="movie-body">
        <h3 class="movie-title"><a href="${escapeHtml(r.url)}">${escapeHtml(r.title)}</a></h3>
        <div class="movie-meta"><span>${r.year ? 'ðŸ“… ' + escapeHtml(r.year) : 'TBA'}</span>${r.genre ? '<span>' + escapeHtml(r.genre) + '</span>' : ''}</div>
        <div class="movie-footer">
          <a href="${escapeHtml(r.url)}" class="btn btn-primary btn-sm" style="flex:1; text-align:center;">Details</a>
          <button type="button" class="btn ${inList ? 'btn-primary' : 'btn-glass'} btn-sm tmdb-wl-btn"
            data-tmdb-id="${Number(r.id)}" data-media-type="${escapeHtml(r.media_type)}" data-title="${escapeHtml(r.title)}"
            data-poster="${escapeHtml(r.poster_path || '')}" data-year="${escapeHtml(r.year)}"
            aria-pressed="${inList}" aria-label="${inList ? 'Remove from watchlist' : 'Add to watchlist'}">${inList ? 'âœ“' : 'ï¼‹'}</button>
        </div></div></article>`;
  }

  function run(reset) {
    const q = input.value.trim();
    if (reset) { page = 1; totalPages = 0; grid.innerHTML = ''; }
    message.innerHTML = '';
    moreBtn.hidden = true;

    const url = new URL(window.location.href);
    q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
    filter !== 'all' ? url.searchParams.set('filter', filter) : url.searchParams.delete('filter');
    history.replaceState(null, '', url);

    if (q.length < 2) {
      status.textContent = '';
      grid.innerHTML = '';
      message.innerHTML = '<div class="empty-state"><p class="empty-title">Start typing to search</p><p>Try a movie, series or anime title â€” partial names work too.</p></div>';
      return;
    }

    const myId = ++reqId;
    loading = true;
    if (reset) grid.innerHTML = skeletons(8);
    status.textContent = 'Searchingâ€¦';

    fetch('api.php?action=tmdb_search&q=' + encodeURIComponent(q) + '&filter=' + encodeURIComponent(filter) + '&page=' + page, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => {
        if (myId !== reqId) return;
        loading = false;
        if (reset) grid.innerHTML = '';
        if (data.error && !(data.results || []).length) {
          status.textContent = '';
          message.innerHTML = `<div class="empty-state error" role="alert"><p class="empty-title">Search failed</p><p>${escapeHtml(data.error)}</p></div>`;
          return;
        }
        grid.insertAdjacentHTML('beforeend', (data.results || []).map(card).join(''));
        totalPages = data.total_pages || 0;
        if (!grid.children.length) {
          status.textContent = '';
          message.innerHTML = `<div class="empty-state"><p class="empty-title">No results for â€œ${escapeHtml(q)}â€</p><p>Check the spelling or try a different filter.</p></div>`;
          return;
        }
        status.textContent = (data.total_results || grid.children.length).toLocaleString() + ' results';
        moreBtn.hidden = !(page < totalPages);
      })
      .catch(() => {
        if (myId !== reqId) return;
        loading = false;
        if (reset) grid.innerHTML = '';
        status.textContent = '';
        message.innerHTML = '<div class="empty-state error" role="alert"><p class="empty-title">Search is unavailable</p><p>Please check your connection and try again.</p></div>';
      });
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); run(true); });
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => run(true), 350); });
  chips.forEach((chip) => chip.addEventListener('click', () => {
    filter = chip.dataset.filter;
    chips.forEach((c) => { const on = c === chip; c.classList.toggle('active', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    run(true);
  }));
  moreBtn.addEventListener('click', () => { if (!loading && page < totalPages) { page += 1; run(false); } });

  run(true);
}

// 5c. TMDB watchlist (movies / TV from TMDB), trailers, share, mobile menu, image fallbacks
function toggleTmdbWatchlist(btn) {
  const full = btn.classList.contains('tmdb-wl-full');
  btn.disabled = true;
  fetch('api.php?action=toggle_tmdb_watchlist', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
    body: JSON.stringify({
      tmdb_id: Number(btn.dataset.tmdbId),
      media_type: btn.dataset.mediaType,
      title: btn.dataset.title || '',
      poster_path: btn.dataset.poster || '',
      year: btn.dataset.year || ''
    })
  })
  .then((r) => r.json())
  .then((data) => {
    if (!data.success) { showToast(data.message || 'Could not update watchlist.', 'warning'); return; }
    if (!data.in_list && btn.dataset.removeCard) {
      const card = btn.closest('.movie-card');
      if (card) card.remove();
      showToast('Removed from your watchlist.');
      return;
    }
    btn.classList.toggle('btn-primary', data.in_list);
    btn.classList.toggle('btn-glass', !data.in_list);
    btn.setAttribute('aria-pressed', data.in_list ? 'true' : 'false');
    btn.textContent = full ? (data.in_list ? 'âœ“ In Watchlist' : 'ï¼‹ Add to Watchlist') : (data.in_list ? 'âœ“' : 'ï¼‹');
    showToast(data.in_list ? 'Added to your watchlist!' : 'Removed from your watchlist.');
  })
  .catch(() => showToast('Could not update watchlist.', 'error'))
  .finally(() => { btn.disabled = false; });
}

function openTrailer(youtubeKey, title) {
  const modal = document.getElementById('trailerModal');
  const box = document.getElementById('trailerContainer');
  if (!modal || !box || !/^[A-Za-z0-9_-]{6,20}$/.test(youtubeKey)) return;
  box.innerHTML = '<div class="video-container responsive-embed"><iframe src="https://www.youtube-nocookie.com/embed/'
    + encodeURIComponent(youtubeKey) + '?autoplay=1&rel=0" title="' + escapeHtml(title || 'Trailer')
    + '" frameborder="0" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>';
  modal.classList.add('active');
}

function initGlobalDelegates() {
  document.addEventListener('click', (e) => {
    const wl = e.target.closest('.tmdb-wl-btn');
    if (wl) { e.preventDefault(); toggleTmdbWatchlist(wl); return; }

    const tr = e.target.closest('[data-trailer]');
    if (tr) { e.preventDefault(); openTrailer(tr.dataset.trailer, tr.dataset.trailerTitle); return; }

    const sh = e.target.closest('[data-share]');
    if (sh) {
      e.preventDefault();
      const url = sh.dataset.shareUrl || window.location.href;
      const title = sh.dataset.shareTitle || document.title;
      if (navigator.share) {
        navigator.share({ title, url }).catch(() => {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => showToast('Link copied to clipboard!'), () => showToast('Could not copy the link.', 'error'));
      } else {
        window.prompt('Copy this link:', url);
      }
    }
  });

  // If a TMDB image fails to load, swap it for a clean text fallback (no broken-image icons)
  document.addEventListener('error', (e) => {
    const img = e.target;
    if (!(img instanceof HTMLImageElement) || !img.classList.contains('tmdb-img')) return;
    if (img.classList.contains('hero-bg')) { img.remove(); return; }
    const fb = document.createElement('div');
    fb.className = 'poster-fallback';
    fb.setAttribute('aria-hidden', 'true');
    fb.innerHTML = '<span>' + escapeHtml(img.alt || '') + '</span>';
    img.replaceWith(fb);
  }, true);

  // Show the anime hint on Discover and lock the filters that anime overrides
  const typeSel = document.getElementById('f-type');
  const note = document.getElementById('animeNote');
  if (typeSel && note) {
    const sync = () => {
      const anime = typeSel.value === 'anime' || typeSel.value === 'anime_movie';
      note.hidden = !anime;
      const lang = document.getElementById('f-lang');
      if (lang) { lang.disabled = anime; if (anime) lang.value = ''; }
      const country = document.getElementById('f-country');
      if (country) { country.disabled = typeSel.value === 'anime'; if (typeSel.value === 'anime') country.value = ''; }
    };
    typeSel.addEventListener('change', sync);
    sync();
  }
}

// Collapsible HEISVERSE sidebar navigation
function initMobileMenu() {
  const bar = document.querySelector('.navbar');
  const sidebar = document.getElementById('siteSidebar');
  const btn = bar ? bar.querySelector('.sidebar-toggle') : null;

  if (!bar || !sidebar || !btn) return;

  document.body.classList.add('has-sidebar');

  const isMobile = () => window.matchMedia('(max-width: 960px)').matches;

  btn.setAttribute('aria-expanded', isMobile() ? 'false' : 'true');

  btn.addEventListener('click', () => {
    if (isMobile()) {
      const open = document.body.classList.toggle('sidebar-mobile-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    } else {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
  });

  sidebar.addEventListener('click', (event) => {
    if (isMobile() && event.target.closest('a')) {
      document.body.classList.remove('sidebar-mobile-open');
      btn.setAttribute('aria-expanded', 'false');
    }
  });

  window.addEventListener('resize', () => {
    if (isMobile()) {
      document.body.classList.remove('sidebar-collapsed');
      btn.setAttribute('aria-expanded', document.body.classList.contains('sidebar-mobile-open') ? 'true' : 'false');
    } else {
      document.body.classList.remove('sidebar-mobile-open');
      btn.setAttribute('aria-expanded', document.body.classList.contains('sidebar-collapsed') ? 'false' : 'true');
    }
  });
}
// 6. Theme Gallery Modal Opener
function openThemeGallery() {
  const modal = document.getElementById('themeGalleryModal');
  if (modal) {
    modal.classList.add('active');
    if (window.Theme300) {
      Theme300.renderGallery();
    }
  }
}

// 7. PWA Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js')
      .then(() => {})
      .catch(() => {});
  });
}

// Global DOM Ready
document.addEventListener('DOMContentLoaded', () => {
  initLiveSearch();
  initSearchPage();
  initGlobalDelegates();
  initMobileMenu();

  // Close modals on clicking backdrop
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) closeModals();
    });
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModals();
  });
});


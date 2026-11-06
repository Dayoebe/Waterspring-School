const CACHE_PREFIX = 'watersprings-pwa-';
const CACHE_VERSION = `${CACHE_PREFIX}v4`;
const CORE_CACHE = `${CACHE_VERSION}-core`;
const PAGE_CACHE = `${CACHE_VERSION}-public-pages`;
const ASSET_CACHE = `${CACHE_VERSION}-assets`;
const OFFLINE_URL = './offline.html';
const MANIFEST_URL = './build/manifest.json';
const CORE_ASSETS = [
  OFFLINE_URL,
  './',
  './images/watersprings/app-icon.svg',
  './images/watersprings/logo.png',
];

// Keep this list aligned with the public page routes in routes/web.php.
// Authentication and portal URLs are intentionally absent.
const PUBLIC_DOCUMENT_PATHS = new Set([
  '/',
  '/about',
  '/academics',
  '/why-watersprings',
  '/contact',
  '/admission',
  '/prospectus',
  '/gallery',
  '/team',
]);

const STATIC_ASSET_PATH_PREFIXES = [
  '/build/',
  '/images/',
  '/img/',
  '/icons/',
];
const STATIC_ASSET_PATHS = new Set([
  '/favicon.ico',
]);

function isSameOrigin(url) {
  return url.origin === self.location.origin;
}

function scopeRelativePath(url) {
  const scopePath = new URL(self.registration.scope).pathname;

  if (!url.pathname.startsWith(scopePath)) {
    return null;
  }

  const relativePath = `/${url.pathname.slice(scopePath.length).replace(/^\/+/, '')}`;
  const normalizedPath = relativePath.replace(/\/+$/, '');

  return normalizedPath || '/';
}

function isDocumentRequest(request) {
  const accept = request.headers.get('accept') || '';

  return request.mode === 'navigate' || accept.includes('text/html');
}

function isPublicDocument(url) {
  const relativePath = scopeRelativePath(url);

  return relativePath !== null && (
    PUBLIC_DOCUMENT_PATHS.has(relativePath) ||
    /^\/team\/\d+$/.test(relativePath)
  );
}

function shouldCacheStaticAsset(url) {
  const relativePath = scopeRelativePath(url);

  return (
    isSameOrigin(url) &&
    relativePath !== null &&
    (
      STATIC_ASSET_PATHS.has(relativePath) ||
      STATIC_ASSET_PATH_PREFIXES.some((prefix) => relativePath.startsWith(prefix))
    )
  );
}

function publicDocumentCacheKey(url) {
  const cacheUrl = new URL(url.toString());
  cacheUrl.search = '';
  cacheUrl.hash = '';

  return new Request(cacheUrl.toString(), {
    credentials: 'omit',
    headers: { Accept: 'text/html' },
  });
}

async function loadManifestAssets() {
  try {
    const response = await fetch(MANIFEST_URL, {
      cache: 'no-store',
      credentials: 'omit',
    });

    if (!response.ok) {
      return [];
    }

    const manifest = await response.json();

    return Object.values(manifest)
      .map((entry) => entry && entry.file ? `./build/${entry.file}` : null)
      .filter(Boolean);
  } catch (error) {
    return [];
  }
}

async function installCoreAssets() {
  const manifestAssets = await loadManifestAssets();
  const assetsToCache = [...new Set([...CORE_ASSETS, ...manifestAssets])];
  const cache = await caches.open(CORE_CACHE);

  await Promise.allSettled(
    assetsToCache.map(async (asset) => {
      try {
        // Precache without session cookies so the home page can never contain
        // an authenticated user's navigation or account details.
        const request = new Request(asset, {
          cache: 'reload',
          credentials: 'omit',
        });
        const response = await fetch(request);

        if (!response || !response.ok || response.type !== 'basic') {
          throw new Error(`Unexpected response: ${response ? response.status : 'no response'}`);
        }

        await cache.put(request, response.clone());
      } catch (error) {
        console.warn('[service-worker] Failed to precache asset:', asset, error);
      }
    })
  );
}

async function cleanupOldCaches() {
  const expectedCaches = [CORE_CACHE, PAGE_CACHE, ASSET_CACHE];
  const cacheKeys = await caches.keys();

  await Promise.all(
    cacheKeys
      .filter((key) => key.startsWith(CACHE_PREFIX) && !expectedCaches.includes(key))
      .map((key) => caches.delete(key))
  );
}

async function refreshPublicDocumentCache(cacheKey) {
  const pageCache = await caches.open(PAGE_CACHE);
  const anonymousRequest = new Request(cacheKey, {
    cache: 'reload',
    credentials: 'omit',
  });
  const response = await fetch(anonymousRequest);

  if (
    response &&
    response.ok &&
    response.type === 'basic' &&
    !response.redirected &&
    isPublicDocument(new URL(response.url))
  ) {
    await pageCache.put(cacheKey, response.clone());
  }
}

async function handlePublicDocumentRequest(request, cacheKey) {
  try {
    // Return the normal session-aware response while online. A separate
    // credential-free request is the only response allowed into PAGE_CACHE.
    return await fetch(request);
  } catch (error) {
    const pageCache = await caches.open(PAGE_CACHE);
    const cachedResponse = await pageCache.match(cacheKey);

    if (cachedResponse) {
      return cachedResponse;
    }

    return caches.match(OFFLINE_URL, { ignoreSearch: true });
  }
}

async function handleStaticAssetRequest(request) {
  const assetCache = await caches.open(ASSET_CACHE);
  const publicAssetRequest = new Request(request, { credentials: 'omit' });
  const cachedResponse = await assetCache.match(publicAssetRequest);

  const networkRequest = fetch(publicAssetRequest)
    .then((response) => {
      if (
        response &&
        response.ok &&
        response.type === 'basic' &&
        !response.redirected &&
        shouldCacheStaticAsset(new URL(response.url))
      ) {
        assetCache.put(publicAssetRequest, response.clone()).catch(() => undefined);
      }

      return response;
    })
    .catch(() => cachedResponse);

  return cachedResponse || networkRequest;
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    installCoreAssets().then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    cleanupOldCaches().then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(request.url);

  if (!isSameOrigin(requestUrl)) {
    return;
  }

  if (isDocumentRequest(request)) {
    // Let the browser fetch every auth and portal document directly. No cache
    // is opened and no offline fallback is served for those requests.
    if (!isPublicDocument(requestUrl)) {
      return;
    }

    const cacheKey = publicDocumentCacheKey(requestUrl);
    event.respondWith(handlePublicDocumentRequest(request, cacheKey));
    event.waitUntil(refreshPublicDocumentCache(cacheKey).catch(() => undefined));
    return;
  }

  if (shouldCacheStaticAsset(requestUrl)) {
    event.respondWith(handleStaticAssetRequest(request));
  }
});

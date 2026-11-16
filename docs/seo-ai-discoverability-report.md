# Watersprings discovery implementation

The public discovery catalogue currently contains ten route-level pages: `/`, `/about`, `/team`, `/academics`, `/open-learning`, `/why-watersprings`, `/admission`, `/prospectus`, `/gallery` and `/contact`. Individual public staff profiles under `/team/{staffProfile}` are rendered from visible staff records but are not separate entries in the static page catalogue.

`App\Support\PublicSeo` supplies page titles, descriptions, canonical URLs, social metadata and structured data. `SeoController` serves the sitemap index, page and image sitemaps, crawler rules and AI-readable summaries. Public gallery images in the image sitemap come from `config/public-school.php`.

Private dashboard, authentication, payment callback/webhook, academic-record, Livewire, Sanctum and internal API routes are excluded from public discovery. Public metadata describes Watersprings International School Akure and uses its configured logo and contact information. Site settings and public content remain separate sources as documented in the README.

See [Watersprings public-site notes](watersprings-public-site.md) for verified sources and historical validation. These endpoints support discovery and citation; they do not guarantee indexing, search rankings, or AI inclusion. Recheck rendered canonical URLs after every `APP_URL` change.

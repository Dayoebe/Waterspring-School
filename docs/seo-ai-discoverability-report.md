# Watersprings discovery implementation

The public pages are `/`, `/about`, `/academics`, `/why-watersprings`, `/admission`, `/gallery` and `/contact`.

`App\Support\PublicSeo` supplies page titles, descriptions, canonical URLs, social metadata and structured data. `SeoController` serves the sitemap index, page and image sitemaps, crawler rules and AI-readable summaries. Public gallery images in the image sitemap come from `config/public-school.php`.

Private dashboard, authentication and academic-record routes are excluded from public discovery. Public metadata describes Watersprings International School Akure and uses its official logo and contact information. Site settings and public content remain separate sources as documented in the README.

See [Watersprings public-site notes](watersprings-public-site.md) for verified sources and validation history. These files support discovery and citation; they do not guarantee search rankings or AI inclusion.

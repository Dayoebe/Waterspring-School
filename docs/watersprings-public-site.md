# Watersprings public website migration

Public content was checked against the school's existing website on 7 September 2026.

The seven public pages are Home, About, Academics, Why Watersprings, Admission, Gallery and Contact. `/why-elites` permanently redirects to `/why-watersprings`. Public authentication pages inherit the new identity. Dashboard records, permissions and school settings were not migrated.

## Content sources

Base website: http://www.waterspringsschool.com.ng/

- `/about-us/about-watersprings-international-school-akure.html`: school introduction and Head of School Adedamola Ogidan.
- `/about-us/mission-and-vision.html`: mission, vision, motto and four core values.
- `/about-us/history.html`: His Mercy Crèche and Playgroup (2002–2013), Mrs. Olajumoke Babatunde and the school's origins.
- `/about-us/ceo-s-message.html`: CEO Dr. Olukayode Abimbola Babatunde, curriculum and facilities.
- `/about-us/school-ethos.html`: Christian ethos, practical learning and service.
- `/schools/early-years-and-foundation-stage/infant-school/creche.html`, `/schools/early-years-and-foundation-stage/infant-school/pre-school-1.html`, `/schools/key-stage-1.html`, `/schools/key-stage-2.html`: learning stages.
- `/admissions-fees/how-to-apply.html`, `/admissions-fees/arrange-a-visit.html`, `/admissions-fees/fees.html`, `/admissions-fees/prospectus.html`: application guidance, visits, fee categories and prospectus link.
- `/contact-us.html`: address, two telephone numbers, three email addresses and social profiles.
- `/news/item/125-watersprings-international-college.html`: 27 July 2025 college announcement. The 2025/2026 flyer is explicitly archived; its entrance dates are not presented as current.

No unsupported enrollment statistics, examination pass rates, boarding claims, sample testimonials or invented events were carried over.

## Public content and media

`config/public-school.php` contains the public identity, contacts, content, programmes, FAQs and photo list. `SiteSettings::forPublicSite()` reads it independently of the dashboard's database settings. This is deliberate while the dashboard migration is deferred; dashboard site-settings edits do not control this public content yet.

Images are stored locally in `public/images/watersprings/`:

| Local file | Original source path |
| --- | --- |
| logo.png | /templates/clxsite/images/object1421062495.png |
| learn.jpg | /images/banners/new%202.jpg |
| play.jpg | /images/banners/new.jpg |
| grow.jpg | /images/banners/scroll-banner-3.jpg |
| together.jpg | /images/banners/6.jpg |
| head-of-school.jpg | /images/design/Photos%20of%20Staff/Mrs%20Ogidan%201.jpg |
| key-stage-2.jpg | /images/design/ks2.jpg |
| college.jpg | /images/news/COLLEGE.jpg |

`app-icon.svg` embeds the original logo on a white canvas. The manifest, service-worker cache version and offline page use Watersprings branding. The image sitemap includes the same photos shown in the public gallery, rather than unrelated database gallery items.

## Forms and next stage

The local `schools` table had no records during validation. Admission therefore links to Watersprings' existing online application page; Contact provides phone/email links and visit guidance. Existing Livewire forms are retained and render when school records are available. No test enquiries or applications were sent externally.

Before enabling the local forms in the dashboard stage, configure Watersprings school records, entry classes and sections, and verify recipient routing and end-to-end submissions. Review current fees and admission dates with the school; no fee amounts were invented. Set the production application URL during deployment so canonical and sitemap URLs use the deployment domain.

## Validation

- PHP 8.4.25 is available at `/usr/bin/php`. The XAMPP PHP 8.2 runtime cannot satisfy this checkout's installed Composer dependencies (PHP >= 8.4.1).
- `npm run build` succeeded.
- `php artisan view:cache` succeeded.
- PHP syntax checks passed for changed PHP files; `node --check public/service-worker.js` passed.
- All seven public pages, login/register/password recovery, sitemap endpoints, AI-readable endpoints, manifest and offline page returned HTTP 200.
- `/why-elites` returned HTTP 301 to `/why-watersprings`.
- Rendered responses were checked for old school names, Awka/Anambra, stock-photo URLs and unsupported sample claims.
- Chromium checks passed on all seven public pages at 390px and 1440px: no horizontal overflow, no broken images, exactly one H1 per page, and no JavaScript exceptions. Mobile navigation and FAQ disclosure controls worked.
- Structured data parsed on all seven pages, and internal fragment links resolved.
- Browser installation of the PWA and live form submission were not tested.

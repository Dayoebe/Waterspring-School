# Watersprings public website implementation history

Public content was checked against the school's existing website on 7 September 2026. The source and validation sections below preserve the evidence from that migration; the current route catalogue is summarized separately so historical statements are not mistaken for current release proof.

The initial seven public pages were Home, About, Academics, Why Watersprings, Admission, Gallery and Contact. The current repository also includes Team, Open Learning, and Prospectus, for ten route-level pages in the sitemap catalogue. Public staff detail pages are generated for visible staff records. Public authentication pages inherit the school identity.

The later management platform adds real academic, finance, admissions, family-service, and AI workflows; the original branding cleanup itself did not create or change operational school records.

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

## Forms and configuration state

The local `schools` table had no records during the original September validation. At that time Admission linked to Watersprings' existing online application page and Contact provided phone/email links. The current code renders its Livewire admission/contact forms when qualifying school records exist and now includes the staff admissions pipeline documented in [school workflows](school-workflows.md). No statement here proves that a particular deployment has completed that configuration.

Before enabling local forms in any deployment, configure Watersprings school records, entry classes and sections, and verify recipient routing and end-to-end submissions. Review current fees and admission dates with the school; no fee amounts were invented. Set the production application URL during deployment so canonical and sitemap URLs use the deployment domain.

## Historical validation (7 September 2026)

- At the time of the public-site migration, the installed dependency set required a different CLI PHP. That constraint is no longer current: `composer.json` now supports PHP `^8.2`, pins dependency resolution to PHP 8.2.12, and the current XAMPP runtime satisfies the lockfile. Always run `composer check-platform-reqs` on the deployment being released.
- `npm run build` succeeded.
- `php artisan view:cache` succeeded.
- PHP syntax checks passed for changed PHP files; `node --check public/service-worker.js` passed.
- All seven pages that existed in that migration, login/register/password recovery, sitemap endpoints, AI-readable endpoints, manifest and offline page returned HTTP 200.
- Rendered responses were checked for unrelated school branding and stock-photo URLs and unsupported sample claims.
- Chromium checks passed on those seven pages at 390px and 1440px: no horizontal overflow, no broken images, exactly one H1 per page, and no JavaScript exceptions. Mobile navigation and FAQ disclosure controls worked.
- Structured data parsed on those seven pages, and internal fragment links resolved.
- Browser installation of the PWA and live form submission were not tested.

## Application branding cleanup

Application defaults now use the verified public-school configuration. Dashboard and report-card branding, logo references and command names use Watersprings. Obsolete logos and application icons were removed, and the service-worker cache version was advanced. School records and the existing uploaded Watersprings logo were preserved. The former school-comparison route was removed.

Historical cleanup validation: build, Blade compilation, PHP syntax, Composer metadata and source-branding checks passed. HTTP checks passed for the seven pages then present, login, dashboard, school management, manifest and AI summary, including local image URLs. The missing local storage symlink was restored so uploaded school logos rendered. Printed report templates were compiled, but no student reports were generated during that cleanup.

## Public prospectus page

`/prospectus` presents the supplied Watersprings Prospectus PDF as a public HTML page, using the existing website layout. Navigation, the footer and Admission link to it. It includes the welcome and CEO messages, facilities, mission and vision, ethos, curriculum and class ages, daily timetable, uniform quantities, attendance policy, bus service, clubs and contact details. The original Google Drive PDF remains linked for its illustrated layout.

Source: https://drive.google.com/file/d/1zcJks1g7HmKV2X8OcNnLNosQG0oM2bUB/view (20-page prospectus, Drive modified date September 2024). Text was retrieved through Google Drive; uniform quantities were verified visually on PDF pages 15–16. Text is lightly edited for web readability. The prospectus’s attendance policy and drop-off timetable give different arrival wording; both are retained, with a prompt to confirm class arrangements with the school.

The page is registered in public metadata, structured data, sitemap and AI-readable page discovery. The service-worker cache version was advanced. No login is required and dashboard navigation is unaffected.

Validation: Blade compilation, scoped PHP formatting, production build and service-worker syntax checks passed. Guest browser checks passed at 390px, 1024px and 1440px: one H1, no dashboard shell, no horizontal overflow, working contents links, loaded images and valid structured data. Home, Admission, the page sitemap and `llms.txt` expose the new page. No forms were submitted.

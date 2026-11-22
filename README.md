# Evolve Hire — WebspaceKit (cPanel) deploy guide

PHP 8.1+ aur MySQL. Node/Redis ki zaroorat nahi. WordPress `public_html` mein jaisa hai waisa rahega — ye app apne subdomain pe chalti hai.

## Pehli baar (15–20 min)

1. cPanel → **MySQL Databases**: DB + user banao (jaise `cpaneluser_evolve`), All Privileges do.
2. `evolvehire-cpanel-v1.5.0.zip` ke andar ki `webspacekit` folder ko File Manager se `/home/USERNAME/evolvehire/` mein rakho (`private/` aur `public/` side-by-side).
3. `private/config.sample.php` ko copy karke `private/config.php` banao. DB details daalo aur `setup_token` mein 32 random hex chars (`openssl rand -hex 16`).
4. **Domains → Subdomain** banao (jaise `hire.yourdomain.com`), Document Root = `/home/USERNAME/evolvehire/public`.
5. **MultiPHP Manager** → PHP 8.1+. **SSL/TLS Status** → Run AutoSSL.
6. Browser mein kholo: `https://SUBDOMAIN/install.php?token=SETUP_TOKEN` → owner email + apna strong password (12+ chars) set karo.
7. File Manager se `public/install.php` **delete** kar do. Login: `/gate`.
8. Health check: `https://SUBDOMAIN/healthz` → `{"ok":true,"version":"1.5.0"}`.

### Addon domain layout (jab domain seedha cPanel account ke andar hai)

Jab `evolvehire.in` jaise addon domain ka webroot `public_html/evolvehire.in` ho, tab do theek options:

- **A (recommended — sabse clean):** cPanel → **Domains → evolvehire.in → Manage** (ya "Setup/Web Application") → Document Root badlo: `/home/hiringhu/evolvehire/public` (ya koi bhi non-`public_html` path jahan files rahengi). Phir `public/` ki files us `public` folder mein aur `private/` uske saath rakho. Webroot ke bahar `private/` hone se CVs/config web se kabhi reachable nahi hote.
- **B (agar docroot nahi badal sakte):** `private/` ko `public_html/evolvehire.in/` ke **andar** rakhna padega — tab `private/.htaccess` (zip mein included) Ise web se block karta rahega (`Require all denied`), aur `public/` ki files webroot mein rakho. Chalta hai, par A zyada safe hai kyunki Apache config girne par private expose ho sakta hai.

Note: `private/` folder `PRIVATE_ROOT` use karta hai jo `__DIR__`-based hai — path koi bhi ho, app khud ko dhund leti hai.

## v1.5.0 — kya badla (v1.4.0 ke upar)

- UI brand mark pe wapas: ink `#0B1C33`, ivory `#F4EFE4`, gold `#C6A15A`. Instrument Serif + Inter, dono self-hosted (CSP-safe).
- Home: editorial hero, sector marquee, credentials alag strip (GST hero ke beech mein nahi), job cards scan hone layak, section headers.
- Bug fix: application status ke steps homepage ke 3-column layout se collide kar rahe the. Ab `.track` alag hai.
- Nav: current page, “Submit resume” primary, mobile menu pehle jaisa `site.js` se (inline onclick nahi).
- Jobs filters 12-column grid. Reduced-motion pe animation band.
- Backend / routes / DB same. Update: zip extract, phir `/migrate.php?token=...` ek baar. Is release mein naya SQL nahi hai — migrate phir bhi safe hai.

## v1.4.0 — kya badla (v1.3.0 ke upar)

- Home: stat band (counters scroll pe chalte hain), "How applying works" steps, service cards pe icons, "Two ways in" CTA band. Inner pages ko soft banner + animated underline mila. Config/data files zip me nahi hain.

## v1.3.0 — kya badla

- **Bug fix:** mobile menu (hamburger) CSP ki wajah se kaam nahi karta tha (inline `onclick` block) — ab `site.js` se chalta hai.
- **Bug fix:** Google Fonts CSP se block the, isliye Sora/Inter load hi nahi hote the — ab fonts `public/assets/fonts/` se self-hosted hain (fast + CSP-safe).
- **Naya:** hero mein animated talent-network (cursor ke saath react karta hai), growth-arrow graphic jo khud draw hota hai, headline light-sweep, live dot, scroll progress bar, cards pe cursor glow, button shine, footer glow. Reduced-motion users ke liye sab band.
- Update: zip upload+extract karo, phir `/migrate.php?token=...` ek baar kholo. `config.php`, `installed.lock`, `storage/`, `logs/` zip mein hain hi nahi, to overwrite nahi honge.

## Site pe kya kya hai

- Public: home, jobs board (search + filters + pagination), job detail + apply form, submit CV, hire-talent brief, track status, job alerts, contact, about, work, services, industries, functions, insights, case studies, companies, careers, credentials, sitemap.xml, robots.txt.
- Desk (staff): applications pipeline (status + notes), CV bank (search), hiring briefs + messages, jobs list, password resets.
- Owner admin (`/admin`): overview counts, jobs CRUD (featured/urgent/internal/expiry), applications, candidates, leads, alerts + digest, catalogue editor (industries/functions/services/insights/case studies/companies), staff management, settings, audit log.

## Update (3 tareeke — koi bhi ek)

- **A. Zip (sabse easy):** naya zip File Manager mein `evolvehire/` pe upload+extract → `https://SUBDOMAIN/migrate.php?token=SETUP_TOKEN` ek baar kholo.
- **B. cPanel Git (auto):** cPanel → Git™ Version Control → repo clone → "Deploy HEAD Commit". `.cpanel.yml` files sync karta hai aur migrations chalata hai.
- **C. GitHub Actions:** repo Secrets: `FTP_HOST FTP_USER FTP_PASS FTP_DIR SITE_HOST SETUP_TOKEN`. `main` pe push = lint + upload + migrate + health check.

`config.php`, `installed.lock`, `storage/` (resumes), `logs/` kabhi overwrite nahi hote.

## Schema badalna

`private/migrations/006_xxx.sql` banao (idempotent: `IF NOT EXISTS`). Deploy pe `/migrate.php?token=...` se automatically chalta hai.

## Mail (optional)

Cron: `*/5 * * * * /usr/local/bin/php /home/USERNAME/evolvehire/private/cron.php`
Ye outbox drain karta hai + job alerts digest chalata hai. Cron na ho to site phir bhi theek chalti hai — desk pe sab dikhta hai.

## Security (already in)

Owner password khud set karta hai (no defaults), setup token, CSRF har POST pe, rate-limit (login/forms/track), session regenerate, CSP/HSTS/nosniff headers, resumes webroot ke bahar + magic-byte check, honeypot fields, audit log, dotfile/block. XSS escaping tested. Duplicate application pe wahi reference code wapas milta hai.

## Backup

cPanel → Backup se DB + `private/storage/` roz. Rollback: purana zip wapas upload + DB backup restore.

## Local dev (optional)

`config.php` mein `'db_driver' => 'sqlite'` + `db_sqlite_path` set karo — MySQL ke bina bhi poori app chal ti hai (migrations + seeds automatically SQLite dialect mein translate hote hain).

# LBU Enrollment System: "Option C · Campus Photo" redesign

Implementation brief. Apply the approved **Option C (Campus Photo)** design to every page of the Long Brown University enrollment system: login, student pages and admin pages.

Reference mockups are in the "LBU Student Home — Design Options" slide deck, slides 4 and 8–14.

---

## 0. Ground rules (read first)

1. **Do not add new files.** Edit only existing files. Put shared CSS into the stylesheets that already exist:
   - `style.css` (root): login page only
   - `student/style.css`: all student pages
   - `Admin/style.css`: all admin pages, including `Admin/includes/report.php`, which loads `../style.css`
2. **Do not change PHP logic, SQL or form field names.** The redesign is markup and CSS only. Keep every `name=""`, `action=""`, `method=""`, session check, prepared statement and escape helper exactly as it is.
3. **Keep all text content and links working.** Only the wrapper markup and classes change.
4. Remove each page's inline `<style>` block once its rules have moved into the shared stylesheet. Page-only rules that are truly unique may stay inline, but keep them short.
5. Old template rules in the stylesheets (`.shell`, `.box`, `.header`, `h2` with `images/h2.gif`, `.university-banner`, the old `.container`/`.nav-link` pills, and so on) can be deleted once no page uses them. Search the project before deleting.
6. Do **not** touch the leftover nurse-system files (`Admin/addnewsched.php`, `addnewsection.php`, `addnewsubj.php`, `addnewuser.php`, `adminedit.php`, `Admin/includes/menu.php`, both `menu2.php`) unless asked.
7. Bump the cache-buster in every `<link href="style.css?v=…">` to `?v=20261120`.

---

## 1. Design tokens

| Token | Value | Use |
|---|---|---|
| `--lbu-maroon` | `#542b29` | Primary buttons, headings, table emphasis |
| `--lbu-maroon-dk` | `#3e2120` | Text on gold buttons |
| `--lbu-cream` | `#fff0c7` | Text on photo/maroon |
| `--lbu-cream-2` | `#ead8bb` | Secondary text on photo |
| `--lbu-gold` | `#e9c77e` | Eyebrows, active nav underline, gold buttons |
| `--lbu-gold-dk` | `#a07a3c` | Small category labels on white |
| `--lbu-page` | `#faf6f0` | Page background |
| `--lbu-line` | `#ece2d6` | Borders |
| `--lbu-ink` | `#33231f` | Body text |
| `--lbu-muted` | `#8a7a70` | Muted text, table header labels |

**Fonts** (Google Fonts, linked in each page `<head>`; no font files):

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
```

- Headings and the school name: **Lora 600**
- Everything else: **Poppins**

**Hero photo:** for now, use the existing `news3.jpg`: `student/news3.jpg` for student pages, `Admin/news3.jpg` for admin pages, and `student/news3.jpg` from the login page. It is set in one CSS variable, `--lbu-hero-img`, so it can be swapped for a real LBU campus photo later by changing one line per stylesheet.

---

## 2. Shared layout (student + admin)

Every logged-in page uses this structure, replacing the old `<header class="university-banner">` + `<nav class="container">`:

```php
<body class="lbu">
  <header class="lbu-hero">
    <div class="lbu-bar">
      <a class="lbu-logo" href="studentindex.php" aria-label="Long Brown University home">
        <img src="../logo.png" alt="Long Brown University crest">
        <span>Long Brown University</span>
        <!-- admin pages only: --> <span class="lbu-tag">Admin</span>
      </a>
      <nav class="lbu-nav" aria-label="Student navigation"><?php include('includes/menu.php'); ?></nav>
    </div>
    <div class="lbu-pagehead">
      <p class="lbu-eyebrow">Student portal</p>
      <h1>Student profile</h1>
      <p>Review the details on your account.</p>
    </div>
  </header>

  <main class="lbu-panel">
    … page content …
  </main>

  <footer class="lbu-footer"><p>Long Brown University · Student Enrollment</p></footer>
</body>
```

- Admin pages link the logo to `adminindex.php`, include `includes/menu1.php`, add the `lbu-tag`, and use "Enrollment Administration" in the footer.
- `Admin/includes/report.php` sits one folder deeper, so it uses `../../logo.png` and `../adminindex.php`. **Check that the menu links work from that folder.** `menu1.php` uses relative links like `userreg.php`, which break from `includes/`. Fix this inside `report.php`, for example with a `$base = '../';` prefix passed into the menu, or by writing the nav links inline in `report.php`. Do not create a new file.

### Active nav item

Edit the existing `student/includes/menu.php` and `Admin/includes/menu1.php` so the current page's link gets `class="nav-link is-active" aria-current="page"`:

```php
<?php $lbuCurrent = basename($_SERVER['PHP_SELF']); ?>
<li class="nav-item"><a class="nav-link<?php echo $lbuCurrent === 'enroll.php' ? ' is-active" aria-current="page' : ''; ?>" href="enroll.php">Enroll Now</a></li>
```

On the enrollment preview (`showsubs.php` after a POST from `enroll.php`), highlight **Enroll Now**. On the plain curriculum view, highlight **Curriculum**.

### Shared CSS

Append this block to **both** `student/style.css` and `Admin/style.css`, with identical content in each. Change only the `--lbu-hero-img` path if needed (both files sit next to their own `news3.jpg`, so `url(news3.jpg)` works for both).

```css
/* ===== LBU · Option C (Campus Photo) ===== */
:root{
  --lbu-maroon:#542b29;--lbu-maroon-dk:#3e2120;--lbu-cream:#fff0c7;--lbu-cream-2:#ead8bb;
  --lbu-gold:#e9c77e;--lbu-gold-dk:#a07a3c;--lbu-page:#faf6f0;--lbu-line:#ece2d6;
  --lbu-ink:#33231f;--lbu-muted:#8a7a70;--lbu-hero-img:url(news3.jpg);
}
body.lbu{margin:0;background:var(--lbu-page);color:var(--lbu-ink);font:14px/1.55 Poppins,Arial,sans-serif;}
.lbu h1,.lbu h2,.lbu h3{font-family:Lora,Georgia,serif;font-weight:600;}

/* Hero */
.lbu-hero{position:relative;isolation:isolate;background:var(--lbu-hero-img) center 40%/cover no-repeat;color:var(--lbu-cream);}
.lbu-hero::before{content:"";position:absolute;inset:0;z-index:-1;
  background:linear-gradient(90deg,rgba(52,24,22,.95) 0%,rgba(62,30,27,.82) 50%,rgba(62,30,27,.45) 100%);}
.lbu-bar{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:20px 60px;}
.lbu-logo{display:flex;align-items:center;gap:14px;color:var(--lbu-cream);text-decoration:none;font-family:Lora,serif;font-size:20px;font-weight:600;}
.lbu-logo img{width:52px;height:52px;border-radius:50%;object-fit:cover;}
.lbu-tag{font-family:Poppins,sans-serif;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  background:var(--lbu-gold);color:var(--lbu-maroon-dk);border-radius:20px;padding:3px 10px;}
.lbu-nav ul{display:flex;flex-wrap:wrap;gap:6px 28px;margin:0;padding:0;list-style:none;}
.lbu-nav .nav-link{display:inline-block;padding:4px 0;color:#f2dfba;font-size:14px;text-decoration:none;border-bottom:2px solid transparent;background:none;}
.lbu-nav .nav-link:hover,.lbu-nav .nav-link:focus-visible{color:#fff;border-bottom-color:rgba(233,199,126,.5);}
.lbu-nav .nav-link.is-active{color:#fff;font-weight:600;border-bottom-color:var(--lbu-gold);}
.lbu-pagehead{padding:26px 60px 74px;max-width:900px;}
.lbu-eyebrow{margin:0;color:var(--lbu-gold);font-size:12px;font-weight:600;letter-spacing:.24em;text-transform:uppercase;}
.lbu-pagehead h1{margin:8px 0 6px;font-size:44px;line-height:1.1;color:var(--lbu-cream);}
.lbu-pagehead p:not(.lbu-eyebrow){margin:0;color:var(--lbu-cream-2);font-size:15px;}

/* Panel that overlaps the hero */
.lbu-panel{position:relative;box-sizing:border-box;margin:-46px 60px 0;padding:26px 30px;
  background:#fff;border-radius:14px;box-shadow:0 14px 34px rgba(48,25,22,.12);}
.lbu-panel h2{margin:0 0 14px;font-size:22px;color:#4a2624;}
.lbu-footer{margin:28px 60px 32px;color:var(--lbu-muted);font-size:13px;}

/* Buttons */
.lbu-btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 22px;border:1px solid var(--lbu-maroon);
  border-radius:40px;background:var(--lbu-maroon);color:var(--lbu-cream)!important;font:600 14px Poppins,sans-serif;text-decoration:none!important;cursor:pointer;}
.lbu-btn--gold{background:var(--lbu-gold);border-color:var(--lbu-gold);color:var(--lbu-maroon-dk)!important;}
.lbu-btn--ghost{background:#fff;border-color:#d9c6a8;color:var(--lbu-maroon)!important;}
.lbu-btn:hover{filter:brightness(1.06);}
.lbu-btn:focus-visible,.lbu-nav .nav-link:focus-visible,.lbu-logo:focus-visible{outline:2px solid var(--lbu-gold);outline-offset:3px;}

/* Info strip (4 facts in a row) */
.lbu-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));border:1px solid var(--lbu-line);border-radius:12px;margin-bottom:20px;}
.lbu-strip > div{padding:14px 20px;border-left:1px solid var(--lbu-line);}
.lbu-strip > div:first-child{border-left:0;}
.lbu-strip small,.lbu-label{display:block;font-size:11px;color:var(--lbu-muted);text-transform:uppercase;letter-spacing:.08em;}
.lbu-strip b{display:block;margin-top:2px;font-size:17px;color:var(--lbu-maroon);}

/* Tables */
.lbu-table-wrap{width:100%;overflow-x:auto;}
.lbu-table{width:100%;min-width:640px;border-collapse:collapse;font-size:13px;}
.lbu-table th{padding:10px 12px;border-bottom:2px solid var(--lbu-line);text-align:left;font-size:11px;font-weight:600;
  letter-spacing:.1em;text-transform:uppercase;color:var(--lbu-muted);background:none;}
.lbu-table td{padding:12px;border-bottom:1px solid #f1e9df;vertical-align:top;}
.lbu-table td strong,.lbu-table td b{color:var(--lbu-maroon);}
.lbu-table .num{text-align:center;white-space:nowrap;}

/* Badges */
.lbu-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.04em;background:#f3e9dc;color:var(--lbu-maroon);}
.lbu-badge--dark{background:var(--lbu-maroon);color:var(--lbu-cream);}
.lbu-badge--ok{background:#e6efe1;color:#3d5f2c;}
.lbu-badge--wait{background:#fbefd9;color:#8a5a12;}

/* Detail boxes (profile) */
.lbu-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
.lbu-field{padding:14px 18px;border:1px solid var(--lbu-line);border-radius:12px;background:#fcfaf7;overflow-wrap:anywhere;}
.lbu-field .lbu-label{color:var(--lbu-gold-dk);font-weight:700;letter-spacing:.1em;}
.lbu-field div{margin-top:4px;font-size:16px;}
.lbu-field--wide{grid-column:1/-1;}

/* Action row under tables/forms */
.lbu-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:18px;}
.lbu-actions .lbu-total{margin-left:auto;color:var(--lbu-muted);font-size:15px;}
.lbu-actions .lbu-total b{font-family:Lora,serif;font-size:22px;color:var(--lbu-maroon);}
.lbu-select{min-height:44px;padding:0 18px;border:1px solid #d9c6a8;border-radius:40px;background:#fff;font:14px Poppins,sans-serif;color:var(--lbu-ink);}
.lbu-empty{padding:16px;border:1px dashed #cdbbaa;border-radius:10px;background:#fcfaf7;}
.lbu-alert{padding:12px 16px;border-radius:10px;background:#fbefd9;color:#8a5a12;margin:0 0 16px;}

/* Stat cards (admin home) */
.lbu-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:22px;}
.lbu-stat{padding:18px 22px;border:1px solid var(--lbu-line);border-radius:12px;}
.lbu-stat strong{display:block;font-family:Lora,serif;font-size:38px;line-height:1.1;color:var(--lbu-maroon);}
.lbu-stat--hl{background:var(--lbu-maroon);border-color:var(--lbu-maroon);}
.lbu-stat--hl .lbu-label{color:var(--lbu-gold);} .lbu-stat--hl strong{color:var(--lbu-cream);}

/* Article cards (student home) */
.lbu-cards{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:20px;}
.lbu-card{overflow:hidden;border:1px solid var(--lbu-line);border-radius:12px;background:#fff;text-decoration:none;color:inherit;}
.lbu-card img,.lbu-card .lbu-card-art{display:block;width:100%;height:120px;object-fit:cover;background:linear-gradient(135deg,#7a4636,#c49a4f);}
.lbu-card > div{padding:14px 18px;}
.lbu-card .lbu-label{color:var(--lbu-gold-dk);font-weight:700;letter-spacing:.12em;}
.lbu-card h3{margin:4px 0 0;font-size:19px;color:var(--lbu-maroon-dk);}

/* Responsive */
@media (max-width:900px){
  .lbu-bar{flex-direction:column;align-items:flex-start;padding:16px 20px;}
  .lbu-pagehead{padding:16px 20px 64px;}
  .lbu-pagehead h1{font-size:34px;}
  .lbu-panel{margin:-44px 12px 0;padding:20px 16px;}
  .lbu-footer{margin:20px 16px 24px;}
  .lbu-stats,.lbu-cards{grid-template-columns:1fr;}
}
@media (max-width:620px){
  .lbu-nav ul{gap:4px 18px;}
  .lbu-fields{grid-template-columns:1fr;}
  .lbu-strip > div{border-left:0;border-top:1px solid var(--lbu-line);}
  .lbu-strip > div:first-child{border-top:0;}
  .lbu-actions{flex-direction:column;align-items:stretch;}
  .lbu-actions .lbu-total{margin-left:0;}
}
```

---

## 3. Page-by-page spec

### 3.1 Login: `index.php` + root `style.css`

The layout is a full-screen photo, with the crest, school name and motto on the left and a white sign-in card on the right (slide 9).

- Replace the body markup. Keep the PHP block at the top and the form fields `username`, `password` and `submit` unchanged.
- Remove the inline `style="float:left…"` divs and the `</br>` tags.
- Show `$loginError` inside the card, above the fields, using `.lbu-alert` with `role="alert"`.

```php
<body class="lbu lbu-login">
  <div class="lbu-login-copy">
    <img src="logo.png" alt="Long Brown University crest">
    <p class="lbu-eyebrow">Maysan · Valenzuela City</p>
    <h1>Long Brown<br>University</h1>
    <p>Learn with purpose. Lead with heart.</p>
  </div>
  <form class="lbu-login-card" method="POST">
    <h2>Sign in</h2>
    <p class="lbu-login-sub">Students and staff use the same login.</p>
    <?php if ($loginError !== '') { ?><p class="lbu-alert" role="alert"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
    <label for="username">Username</label>
    <input id="username" type="text" name="username" required autocomplete="username">
    <label for="password">Password</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
    <button type="submit" name="submit" value="Login" class="lbu-btn lbu-btn--gold">Log in</button>
    <p class="lbu-login-help">Trouble signing in? Contact the registrar’s office.</p>
  </form>
</body>
```

> Keep `name="submit"` on the button: the PHP checks `isset($_POST['submit'])`.

Replace the contents of root `style.css` with the needed `:root` tokens, `body.lbu`, `.lbu-eyebrow`, `.lbu-btn`, `.lbu-btn--gold` and `.lbu-alert` (copied from section 2), plus:

```css
.lbu-login{min-height:100vh;margin:0;display:flex;align-items:center;justify-content:space-between;gap:48px;padding:40px 110px;box-sizing:border-box;
  background:linear-gradient(90deg,rgba(52,24,22,.95),rgba(62,30,27,.82) 50%,rgba(62,30,27,.45)),url(student/news3.jpg) center/cover no-repeat;}
.lbu-login-copy{max-width:620px;color:#fff4d5;}
.lbu-login-copy img{width:120px;height:120px;border-radius:50%;box-shadow:0 0 0 6px rgba(233,199,126,.25);}
.lbu-login-copy .lbu-eyebrow{margin-top:26px;}
.lbu-login-copy h1{margin:10px 0 12px;font-size:62px;line-height:1.05;color:#fff4d5;}
.lbu-login-copy p:last-child{margin:0;font-size:18px;color:#ead8bb;}
.lbu-login-card{width:440px;max-width:100%;box-sizing:border-box;padding:38px 40px;border-radius:18px;background:#fff;box-shadow:0 24px 60px rgba(20,8,6,.4);}
.lbu-login-card h2{margin:0 0 4px;font-size:28px;color:#4a2624;}
.lbu-login-sub{margin:0 0 22px;color:#8a7a70;}
.lbu-login-card label{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#4a2624;}
.lbu-login-card input{display:block;width:100%;box-sizing:border-box;height:48px;margin-bottom:18px;padding:0 14px;border:1px solid #d9c6a8;border-radius:10px;background:#fcfaf7;font:14px Poppins,sans-serif;}
.lbu-login-card input:focus{outline:2px solid #e9c77e;outline-offset:1px;border-color:#c49a4f;}
.lbu-login-card .lbu-btn{width:100%;margin-top:6px;}
.lbu-login-help{margin:18px 0 0;text-align:center;font-size:12px;color:#8a7a70;}
@media (max-width:900px){
  .lbu-login{flex-direction:column;justify-content:center;padding:32px 20px;text-align:center;}
  .lbu-login-copy img{width:90px;height:90px;}
  .lbu-login-copy h1{font-size:40px;}
  .lbu-login-card{text-align:left;}
}
```

Delete the old `.loginForm` rules (there are two copies) and the leftover template CSS in root `style.css`.

### 3.2 Student home: `student/studentindex.php`

Slide 4. This page has a **taller hero** than the inner pages.

- Hero: add the modifier class `lbu-hero--home` and set `.lbu-hero--home .lbu-pagehead{padding:28px 60px 110px}` and `.lbu-hero--home h1{font-size:58px}`.
  - Eyebrow: `Student portal`
  - h1: `Welcome back,<br><?php echo first name ?>.` (if no profile, just "Welcome back.")
  - Motto line: "Learn with purpose. Lead with heart."
  - Two buttons inside the hero: `Enroll now` (`lbu-btn--gold`, links to `enroll.php`) and `View curriculum` (a transparent button with a cream 1px border, links to `showsubs.php`).
- Directly under the hero, instead of `.lbu-panel`, put an overlapping **info strip** card (`main.lbu-panel` containing `.lbu-strip`) with Program, Year level and Student no. Add `studnum` to the existing SELECT. Show a fourth cell for Enrollment only if the data already exists; otherwise show three cells.
- Below it, a section titled **Campus updates** (h2, Lora) using `.lbu-cards`: the three existing articles, each as an `<a class="lbu-card">` with category label, title, and the same link targets as today. Use the gradient block `.lbu-card-art` in place of a photo, varying the gradient per card, until real LBU photos exist. Do not use `news1.jpg` or `news4.jpg`: they show another school's branding.

### 3.3 Profile: `student/studentprofile.php`

Slide 10.
- Hero: eyebrow `Student portal`, h1 `Student profile`, subtitle `Review the details on your account.`
- Panel:
  - Header row: a round 70px maroon avatar with initials (Lora, cream), the full name in Lora 24px, then `Student no. · COURSE · year` in muted text, and a right-aligned `Continue to enrollment →` (`lbu-btn--gold`).
  - Then `.lbu-fields`: Course, Year level, Email, Contact, and Address (`lbu-field--wide`).
- Keep the "profile not found" message, shown in `.lbu-empty`.

### 3.4 Enroll Now: `student/enroll.php`

Slide 11.
- Hero: eyebrow `Step 2 of 4 · Choose subjects`, h1 `Enroll in subjects`, subtitle `Select the subjects for your enrollment request.`
- Panel: the existing form inside `.lbu-table-wrap > table.lbu-table`, with the checkbox as the **first** column, then Code, Subject, Description and Units (`.num`).
- Checkboxes: style them with `accent-color:#542b29; width:20px; height:20px;`.
- `.lbu-actions` row: the semester `<select class="lbu-select">` (keep `name="sem"`), the submit button `Review selected subjects →` (`lbu-btn`), and a right-aligned `.lbu-total` reading "N selected · **X units**".
  - The counter is **optional** (see section 4). Without it, omit the `.lbu-total`.

### 3.5 Curriculum + Enrollment preview: `student/showsubs.php`

Slide 12. This file renders two states, so keep its branching logic.
- **Preview state (after POST):**
  - Hero: eyebrow `Step 3 of 4 · Review fees`, h1 `Enrollment preview`.
  - Panel: `.lbu-strip` with Student, Course, Year level and Semester. Then `table.lbu-table` with Subject (code bold + title), Schedule, Units and Est. tuition.
  - `.lbu-actions`: `← Back to subjects` (`lbu-btn--ghost`), `Submit enrollment request` (`lbu-btn--gold`), and `.lbu-total` "Estimated total **₱…**".
  - Keep the existing confirm() and the hidden fields.
- **Curriculum state:**
  - Hero: eyebrow `Student portal`, h1 `<COURSE> curriculum`.
  - Panel: `.lbu-strip` with Student, Year level and Subjects listed, then the subject table and a `Go to enrollment` button.
- Keep `.screen-only` / print behaviour if it exists.

### 3.6 Admin home: `Admin/adminindex.php`

Slide 13.
- Hero: add the `lbu-tag` "Admin" next to the logo. Eyebrow `Enrollment administration`, h1 `Welcome, administrator`, subtitle `Review accounts, keep subjects current, and follow enrollment requests.`
- Panel:
  - `.lbu-stats`: Accounts, Subjects, and Requests awaiting review. The last one uses `lbu-stat--hl`.
  - Then a two-column row (`grid-template-columns:1.5fr 1fr; gap:20px`, stacking at 900px):
    - Left: **Latest requests** table (optional, see section 4). If it isn't built, show the existing "Articles & campus updates" text here instead.
    - Right: **Quick actions**, three full-width stacked buttons: `Manage accounts` (`lbu-btn`), then `Manage subjects` and `Enrollment report` (`lbu-btn--ghost`).

### 3.7 Admin lists: `Admin/userreg.php`, `Admin/subjectreg.php`, `Admin/includes/report.php`

Slide 14. All three share one look.
- Hero with the admin tag. Eyebrow `Enrollment administration`. h1 is `Accounts`, `Subject catalog` or `Enrollment report`, with the existing intro sentence as the subtitle.
- Panel: `table.lbu-table` inside `.lbu-table-wrap`, keeping the same columns as today.
  - userreg: show Role as `.lbu-badge` (Student) or `.lbu-badge--dark` (Admin).
  - report: show the approved flag as `.lbu-badge--ok` "Approved" or `.lbu-badge--wait` "Pending".
- The search bar and `+ Add account` button in the mockup are **optional** (section 4).

---

## 4. Optional features shown in the mockups (ask before building)

These go beyond restyling. **Do not build them unless the user confirms.** Lay out the page so they can be added later.

1. **Live units counter** on `enroll.php`: a small inline `<script>` that counts checked boxes and adds up units, using a `data-units` attribute on each checkbox.
2. **Latest requests table** on `adminindex.php`: the last 5 rows of `student_subject` joined to `studenttbl`, using the same query style as `report.php`, with Pending/Approved badges.
3. **Search box** on `userreg.php`: client-side filtering of table rows.
4. **"+ Add account" button**: there is currently no working add-user page (the `addnew*.php` files are nurse-system leftovers). Hide the button until a real page exists.

---

## 5. Checklist before finishing

- [ ] No new files were created. `git status` shows only modified files.
- [ ] Login works for both a student and an admin account, and the error message shows in the card.
- [ ] Every nav link works from every page, including `Admin/includes/report.php`.
- [ ] The active nav item is highlighted on each page.
- [ ] Enrollment flow works end to end: enroll → preview → submit.
- [ ] Pages look right at 1440px, 900px, 620px and 375px wide, with no horizontal scroll except inside table wrappers.
- [ ] Text contrast: cream on the photo overlay, and maroon on white or cream.
- [ ] Old unused CSS is removed from the three stylesheets, and each page's inline `<style>` is gone or minimal.
- [ ] The cache-buster is updated to `?v=20261120`.

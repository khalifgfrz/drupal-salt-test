# Salt Drupal Test

Implementation of the Salt Drupal technical test: News & Event content
types, an editable homepage banner, custom blocks for Event/News on
the homepage (no Views/Block UI), and a detail page with a related
content sidebar.

## 1. Requirements

- **PHP 8.3+** (Drupal 11.4.x needs this — PHP 8.1/8.2 will fail
  during `composer create-project` with a parse error on
  `drupal/core-recipe-unpack`)
- PHP extensions enabled: `gd`, `mbstring`, `curl`, `openssl`, `zip`,
  `pdo_mysql` (or `pdo_sqlite`)
- Composer 2.x
- MySQL/MariaDB (via XAMPP) **or** SQLite for quick local testing
- Git (also used by `drush.bat` on Windows — make sure Git for
  Windows / Git Bash is installed)

> If your XAMPP still ships PHP 8.2, replace the `xampp/php` folder
> with a PHP 8.3+ build from https://windows.php.net/download/ (pick
> **Thread Safe** if you'll also use it with Apache), or install PHP
> 8.3+ separately and put it first in your system `PATH`.

## 2. Install Drupal via Composer

```bash
composer create-project drupal/recommended-project drupal-salt-test
cd drupal-salt-test
composer require drush/drush
```

## 3. Add the custom module

Copy the `web/modules/custom/salt_custom` folder from this package
into `web/modules/custom/` in your project.

## 4. Install the site

**Using MySQL/MariaDB (XAMPP), recommended:**

```bash
vendor/bin/drush site:install standard \
  --db-url=mysql://root:@localhost:3306/YOUR_DATABASE_NAME \
  --account-name=admin --account-pass=admin -y
```

(create an empty database first via DBeaver/phpMyAdmin before this)

**Or using SQLite (no DB setup at all):**

```bash
vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.ht.sqlite \
  --account-name=admin --account-pass=admin -y
```

## 5. Run the local dev server

**IMPORTANT:** the `php -S` built-in server MUST be run **from inside
the `web` folder**, using Drupal's built-in router — otherwise CSS/JS
won't load at all:

```bash
cd web
php -S localhost:8888 .ht.router.php
```

> If an uploaded image whose filename contains a space returns 404,
> edit `web/.ht.router.php` and change the line
> `if (file_exists(__DIR__ . $url['path']))` to
> `if (file_exists(__DIR__ . rawurldecode($url['path'])))`. This is a
> quirk specific to PHP's built-in server and won't appear on
> Apache/production.

## 6. Enable the custom module

```bash
vendor/bin/drush en salt_custom -y
```

This automatically creates (see `salt_custom.install`):

- The **Category** vocabulary
- The **News** content type (News Description, Publish Date, Image,
  Category) and **Event** content type (Event Description, Event Date
  [date range], Event Image, Category) — fields exactly matching the
  spec
- The **Banner** block type (Title, Subtitle, Banner Image, **Search
  Box**) — editable by the editor with no code changes via
  **Content > Blocks**. Search Box is a **Long Text (Full HTML)**
  field — the editor writes/pastes their own search box markup
  (input + button) there, so the layout is fully configurable without
  touching code (still non-functional, per the spec: "only layout,
  don't work on search functionality").

## 7. Add taxonomy terms

```
Structure > Taxonomy > Category > Add term
```

Create a few categories, e.g. "Team Event", "Health".

## 8. Tidy up Manage Display for News & Event

```
Structure > Content types > News > Manage display
Structure > Content types > Event > Manage display
```

Arrange the field order to match the wireframe (Image → Date →
Description → Category). Optional: uncheck **"Display author and
date information"** on the content type's Edit page if you don't want
the "By admin, ..." line to show (it's not in the PDF wireframe).

## 9. Set up the Banner

1. **Structure > Block types > Banner > Manage display** — set the
   Label of every field (Title, Subtitle, Banner Image, Search
   Box) to **Hidden** (otherwise the raw field label text gets
   printed).
2. **Content > Blocks > Add content block**, choose type **Banner**,
   fill in Title/Subtitle/Banner Image, and **Search Box** — this
   field starts EMPTY (the module ships no default markup), so type
   or paste your own search box HTML here, e.g.:
   ```html
   <form class="salt-banner-form" action="/search/node" method="get">
     <div class="salt-search-input-box">
       <span>🔍</span>
       <input
         type="text"
         name="keys"
         placeholder="example: salt"
         autocomplete="off"
       />
     </div>
     <div>
       <button class="salt-search-btn" type="submit">search</button>
     </div>
   </form>
   ```
   Set the text format to **Full HTML** so the `<input>`/`<button>`
   tags render as actual form elements instead of being stripped or
   printed as plain text. Then Save.

## 10. Add content

`Content > Add content` — create a few News and Event items with a
Category, so the "ongoing event priority" and "related content" logic
both have something to work with.

## 11. Configure Block Layout (Structure > Block layout)

All blocks are placed in the **Content** region, in this order (drag
using the ⇅ icon) with this visibility:

| Order | Block                            | Pages visibility      | Mode                          |
| ----- | -------------------------------- | --------------------- | ----------------------------- |
| 1     | **Banner**                       | `<front>` + `/node/*` | Show for the listed pages     |
| 2     | **Page title**                   | _(empty)_             | _(default, all pages)_        |
| 3     | **Salt - Homepage Event & News** | `<front>`             | Show for the listed pages     |
| 4     | **Main page content**            | `<front>`             | **Hide** for the listed pages |

## 12. Export config & database

```bash
vendor/bin/drush config:export -y
vendor/bin/drush sql:dump --result-file=db-backup.sql
```

## Module structure

```
web/modules/custom/salt_custom/
├── salt_custom.info.yml
├── salt_custom.install                 # content types, fields, banner block type
├── salt_custom.module                  # hook_theme, hook_preprocess_node/block, card-builder helper
├── salt_custom.libraries.yml
├── css/salt-custom.css
├── src/Plugin/Block/
│   ├── HomepageEventNewsBlock.php      # Event + News side by side, 1 block (in use)
│   ├── EventListBlock.php              # separate version (optional/legacy)
│   ├── NewsListBlock.php               # separate version (optional/legacy)
│   └── RelatedContentBlock.php         # optional; related content is already baked into node templates
└── templates/
    ├── salt-homepage-event-news-block.html.twig
    ├── block-content--banner.html.twig
    ├── node--event--full.html.twig     # 2-column layout + related content
    ├── node--news--full.html.twig
    └── (salt-event-list-block / salt-news-list-block / salt-related-content-block: legacy)
```

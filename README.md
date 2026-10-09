# Finance

A personal finance dashboard. PHP 8.4, hand-rolled MVC, MySQL, **zero external
dependencies**.

**[Try the live demo](https://williamtouboul.alwaysdata.net/demo)** — no sign-up,
no credentials. A throwaway account is created on the spot, filled with three
months of sample data, and deleted 24 hours later. Everything is editable.

## What it does

Track income and expenses by hand, one entry at a time. No bank API, by design:
the point of the tool is to reconcile accounts deliberately, not to watch a feed
scroll past.

- **Entries** with a label, a signed amount, a date and free-form tags
- **Tags** you create as you go, each with its own colour, doubling as spending
  categories
- **Time navigation** by day, month or year, with every figure scoped to the
  period on screen
- **Spending breakdown** as a pie chart, server-rendered SVG
- **Balance curve** over the period, so an approaching overdraft is visible at a
  glance
- **Recurring entries** — rent, salary, subscriptions — that queue up for review
  rather than posting themselves
- **Monthly budgets** per category, with a progress bar that turns amber then red
- **Search and filters** on label, direction, tags and amount range
- **CSV export** of whatever is currently on screen, filters included

## Notable design decisions

The parts of this codebase worth a second look:

**Money is never a float.** Amounts live as signed integers of cents, in memory
and in the database. `0.1 + 0.2 !== 0.3` in binary, and a rounding error on
someone's accounts is not a cosmetic bug.

**Date ranges, never `YEAR()` or `MONTH()`.** Wrapping an indexed column in a
function stops MySQL from using the index and forces a full table scan. Every
period filter uses inclusive bounds against `(user_id, occurred_on, id)`.

**One primary tag carries the amount.** An entry can wear as many tags as you
like, but exactly one owns its value in the breakdown. Without that rule, €80
tagged both *leisure* and *sport* would count twice and the pie chart would
total more than you actually spent. The slices now add up to the period's real
spending, to the cent.

**Charts are computed server-side and emitted as SVG.** No charting library, so
no dependency, no JavaScript requirement, and they stay crisp when printed. The
geometry is separated from the rendering so it can be tested without diffing SVG
strings — including the degenerate case of a single 100% slice, where an arc's
start and end points coincide and SVG draws nothing at all.

**Recurring entries never post themselves.** A due occurrence joins a review
queue; you confirm it, adjusting the amount if the bill varies. What marks an
occurrence as already handled is the entry it produced, not a "last generated"
counter that would drift out of sync the first time a row was deleted by hand.
That also makes a double submission harmless and lets you backfill history by
dating a recurrence in the past.

**No duplicate code path for the demo.** The demo runs the exact same
application; isolation rests on the same `user_id` boundary that separates any
two users, which the test suite exercises directly. A parallel in-session
implementation would have meant maintaining a second copy of ~1,100 lines of
data access — two implementations that drift apart, and a demo that eventually
stops showing real behaviour.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring` and `openssl`
- MySQL 5.7+ or MariaDB 10.4+

No third-party libraries, so no `composer install`. Autoloading is a hand-written
PSR-4 autoloader in `src/Core/Autoloader.php`, comments included.

## Local setup

```bash
cp config/config.example.php config/config.php   # then fill in your credentials
php bin/migrate.php                              # creates the database if absent
php bin/create-user.php                          # the only way to create an account
php -S localhost:8000 -t public
```

On Windows, if PHP is not on your `PATH`, PowerShell needs the call operator in
front of a quoted path, otherwise it treats the line as a plain string:

```powershell
& "C:\path\to\php.exe" bin/migrate.php
```

## Scripts

| Script                   | Purpose                                                  |
|--------------------------|----------------------------------------------------------|
| `bin/migrate.php`        | Applies pending SQL migrations                           |
| `bin/create-user.php`    | Creates an account — there is no sign-up page            |
| `bin/set-password.php`   | Sets a new password on an existing account               |
| `bin/test.php`           | Runs the test suites (none of them touch the database)   |
| `bin/check-prod.php`     | Audits an install before exposing it to the internet     |

## Tests

```bash
php bin/test.php
```

```
Budget                  17 assertions   ok
PieChart + LineChart    34 assertions   ok
Money                   22 assertions   ok
Period                  30 assertions   ok
Schedule                23 assertions   ok

[ok] 126 assertions, no failures.
```

A small harness in `tests/TestCase.php` rather than PHPUnit, consistent with
the zero-dependency rule. The suites cover pure computation — amounts, dates,
recurrence schedules, chart geometry, budget thresholds — which is exactly where
a silent error would go unnoticed. The runner returns a meaningful exit code, so
it drops straight into CI.

## Project layout

```
public/      the only directory served by the web server
src/Core/    autoloading, routing, database access, session, CSRF, hashing
src/         controllers, models, repositories, services
views/       plain PHP templates
config/      configuration (config.php is git-ignored) and the route table
database/    SQL migrations
bin/         command-line scripts
tests/       test suites, run by bin/test.php
var/         logs and generated files
```

## Conventions

- Amounts are **integer cents**, signed. Never a float.
- Negative is an expense, positive is income.
- `occurred_on` is when the transaction happened, distinct from `created_at`.
- An entry may carry many tags but exactly one **primary tag**, which owns its
  amount in the category breakdown.
- Every value printed in a template goes through `View::e()`.
- Every POST form carries a CSRF token via `Csrf::field()`.

## Demo mode

`/demo`, or the button under the sign-in form, creates an anonymous account
seeded with three months of entries, tags, recurrences and budgets, then deletes
it after 24 hours. Visitors get the whole application, with no restrictions.

Opening a demo is a POST rather than a link, inconvenient as that is for sharing:
creating an account is a write, and a GET would be triggered by browser
prefetching, link previews in messaging apps and crawlers. The shared URL is
therefore a landing page with a real form on it.

Expired accounts are purged opportunistically on each new demo, rather than by a
scheduled task that not every host offers. Their data goes with them through the
existing `ON DELETE CASCADE` constraints.

## Security

The application holds personal financial data. What is in place:

| Concern                     | Implementation                                                   |
|-----------------------------|------------------------------------------------------------------|
| Password storage            | Argon2id where available, bcrypt cost 12 otherwise, auto-rehash   |
| SQL injection               | Prepared statements throughout, PDO emulation disabled            |
| XSS                         | Output escaping via `View::e()`, plus a Content-Security-Policy   |
| CSRF                        | Per-session token and a `SameSite=Strict` cookie                  |
| Session fixation            | Session ID regenerated on login                                   |
| Session cookie theft        | `HttpOnly`, `Secure` over HTTPS, client fingerprint               |
| Brute force                 | 15-minute lockout past 10 failures per IP or 20 per account       |
| Account enumeration         | One generic message, plus a constant-time dummy verification      |
| Cross-account access        | Every query filters on `user_id`; a foreign row returns 404       |
| Source code exposure        | Only `public/` is served, configuration lives outside the docroot |
| Leaks through stack traces  | Function arguments stripped (`zend.exception_ignore_args`)        |
| Input encoding              | UTF-8 normalisation as the request is read                        |

A plaintext password exists only for as long as it takes to verify it. It is
never logged, echoed back into a form, placed in a URL, stored in the session or
accepted as a command-line argument.

The least obvious one is the stack traces. PHP attaches each call's arguments to
exception traces by default, so a database outage during sign-in used to write
`Auth->attempt('you@example.com', 'YourPasswo...')` straight into the log file —
and onto the screen in development mode. `config/bootstrap.php` disables that for
every entry point.

Hashing is not pinned to one algorithm. Argon2id is preferable, but shared hosts
do not always provide it; the code picks the best available at runtime and
rehashes on the next successful login if the environment changes. That decision
paid off on the first deployment.

## Deployment

Runs on any shared host with PHP 8.2+ and MySQL or MariaDB. These steps are
written for alwaysdata, whose free tier is more than enough: 1 GB of disk, an
`.alwaysdata.net` subdomain and automatic Let's Encrypt certificates.

**1. Site** — in *Web > Sites*, add a PHP site and set the document root to
`/www/finance/public`. **Not** `/www/finance`. This is the single most important
setting here: pointing at the project root would make `config/config.php` and its
credentials downloadable by anyone.

**2. Database** — create a database and a **dedicated user** with a long
password. Not the admin account: an application flaw should not reach your other
databases. Note the host, which is not `127.0.0.1`.

**3. Files**

```bash
ssh youraccount@ssh-youraccount.alwaysdata.net
cd www && git clone https://github.com/WilliamTouboul/finance.git && cd finance
```

**4. Configure** — copy `config/config.example.php` to `config/config.php`, set
`env` to `'prod'`, `session.secure` to `true`, and the database credentials from
step 2.

**5. Install**

```bash
php bin/migrate.php
php bin/create-user.php
```

**6. Verify**

```bash
php bin/check-prod.php
```

This audits configuration, environment, database and file exposure, and refuses
to pass while anything blocking remains. Do not open access until it is green.

### Subsequent updates

```bash
cd ~/www/finance && git pull && php bin/migrate.php && php bin/check-prod.php
```

`config/config.php` is git-ignored, so `git pull` never overwrites it.

### Backups

The free tier keeps three days of history, which is short for data entered by
hand over years. The CSV export on the Entries page is there for that: run it
now and then and keep a copy.

## Status

All five planned stages are complete and deployed.

- [x] Foundations — database, MVC skeleton, routing
- [x] Authentication
- [x] Tags and entries, day / month / year navigation
- [x] Category pie chart, search and filters, CSV export
- [x] Recurring entries, balance curve, per-category budgets

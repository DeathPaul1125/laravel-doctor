# Laravel Doctor

> Inspired by [react-doctor](https://github.com/millionco/react-doctor). Catches bad Laravel before it ships.

Static analyzer that scans a Laravel project and reports issues across four dimensions, producing a **health score 0–100** plus a list of actionable findings.

- **75+** → `great`
- **50–74** → `needs-work`
- **<50** → `critical`

## Install

```bash
composer require --dev deathpaul1125/laravel-doctor
```

## Usage

### Artisan (auto-registered)

```bash
php artisan doctor
php artisan doctor --json
php artisan doctor --category=security
php artisan doctor --min-score=70       # CI: fail when score < 70
php artisan doctor --baseline           # only count new issues
php artisan doctor --update-baseline    # snapshot current state
```

### Standalone CLI

```bash
vendor/bin/laravel-doctor .
vendor/bin/laravel-doctor . --json > doctor-report.json
vendor/bin/laravel-doctor . --category=performance
vendor/bin/laravel-doctor . --html=doctor-report.html   # interactive HTML
```

### Web dashboard (auto-mounted in non-production)

When the package is installed, the ServiceProvider mounts two routes — but only when `APP_ENV` is `local`, `development`, `testing` or `staging`:

- `GET /doctor` → interactive HTML dashboard
- `GET /doctor/json` → raw JSON

Just open `http://your-app.test/doctor` while developing.

## Built-in checks (20)

### Security
| ID                                | Severity        | Detects                                                   |
| --------------------------------- | --------------- | --------------------------------------------------------- |
| `security/mass-assignment`        | HIGH            | Models without `$fillable`/`$guarded`                     |
| `security/raw-query-injection`    | CRITICAL        | `whereRaw`, `DB::raw`, etc. with variable interpolation   |
| `security/blade-unescaped`        | HIGH            | `{!! $var !!}` in Blade (XSS risk)                        |
| `security/app-config`             | CRITICAL/LOW    | `APP_DEBUG=true` in prod, empty `APP_KEY`, missing example|
| `security/env-outside-config`     | MEDIUM          | `env()` called outside `config/`                          |
| `security/hardcoded-secrets`      | CRITICAL        | AWS/Google/Slack/GitHub keys, JWTs, PEM blocks, password literals |
| `security/missing-csrf`           | HIGH            | `<form method="POST">` without `@csrf`                    |
| `security/request-all-to-fill`    | HIGH            | `Model::create($request->all())` and friends              |
| `security/disabled-tls-verify`    | HIGH            | `'verify' => false`, `withoutVerifying()`, `CURLOPT_SSL_VERIFYPEER => false` |

### Performance
| ID                                | Severity | Detects                                                  |
| --------------------------------- | -------- | -------------------------------------------------------- |
| `performance/possible-n-plus-one` | MEDIUM   | Relation access inside loops without eager-loading       |
| `performance/load-all-rows`       | MEDIUM   | `Model::all()` in controllers/jobs/commands              |
| `performance/missing-fk-index`    | MEDIUM   | Migration columns ending in `_id` without `->index()`/FK |

### Architecture
| ID                                       | Severity | Detects                                  |
| ---------------------------------------- | -------- | ---------------------------------------- |
| `architecture/fat-controller`            | MEDIUM   | Controllers >250 LOC or methods >60 LOC  |
| `architecture/validation-in-controller`  | LOW      | Inline `$request->validate()`            |
| `architecture/unnamed-route`             | LOW      | `Route::get(...)` without `->name(...)`  |
| `architecture/closure-route`             | LOW      | Closure routes (block `route:cache`)     |
| `architecture/missing-migration-down`    | LOW      | Migration files with empty/no `down()`   |

### Quality
| ID                              | Severity | Detects                                      |
| ------------------------------- | -------- | -------------------------------------------- |
| `quality/unused-import`         | LOW      | `use` statements that aren't referenced      |
| `quality/duplicate-migration`   | MEDIUM   | Same table created in >1 migration           |
| `quality/debug-statements`      | MEDIUM   | `dd`, `dump`, `var_dump`, `print_r`, `ray`   |

## Configuration

Drop a `.laravel-doctor.json` at the project root:

```json
{
  "disable": ["architecture/unnamed-route"],
  "ignore": [
    "app/Http/Controllers/Legacy/**",
    { "path": "app/Models/User.php", "check": "security/mass-assignment" }
  ],
  "thresholds": { "fatController": { "fileLoc": 300, "methodLoc": 80 } }
}
```

- `disable` — list of check IDs to skip entirely.
- `ignore` — paths/globs to skip, or `{path, check, line}` objects for finer control. Supports `**` for any depth and `*` for a single segment.

## Baseline workflow

For legacy projects with thousands of pre-existing issues, snapshot once and only fail on **new** problems:

```bash
# Capture current state as accepted
php artisan doctor --update-baseline
git add .laravel-doctor.baseline.json
git commit -m "chore: laravel-doctor baseline"

# Future runs hide known findings and score only new ones
php artisan doctor --baseline --min-score=80
```

## Sample output

```
  Laravel Doctor — /var/www/my-app

  SECURITY (3)
  ────────────────────────────────────────────────────────────
  CRITICAL  security/app-config
    APP_DEBUG=true while APP_ENV=production — stack traces will leak secrets.
    → .env
    💡 Set APP_DEBUG=false in production environments.

  HIGH      security/mass-assignment
    Model is missing $fillable or $guarded declaration.
    → app/Models/Post.php:1
    💡 Declare protected $fillable = [...] or protected $guarded = [].

  ════════════════════════════════════════════════════════════
  Health score: 62/100 (NEEDS WORK)
  security: 3 · performance: 2 · architecture: 1
```

## JSON output (CI)

```json
{
  "project": "/var/www/my-app",
  "score": 62,
  "grade": "needs-work",
  "totals": {
    "findings": 6,
    "byCategory": { "security": 3, "performance": 2, "architecture": 1, "quality": 0 },
    "baselineKnown": 0
  },
  "findings": [ { "check": "security/app-config", "severity": "critical", ... } ]
}
```

## GitHub Actions

A workflow is provided at [`.github/workflows/laravel-doctor.yml`](.github/workflows/laravel-doctor.yml):

- Installs PHP 8.2 and Composer
- Runs `laravel-doctor . --json --baseline --min-score=80`
- Uploads the JSON report as an artifact
- Posts a top-25 finding comment on PRs
- Fails the build when score drops below 80

Copy it into any Laravel repo to get the same flow.

## Adding custom checks

```php
use LaravelDoctor\{Check, CheckContext, Finding, Category, Severity};

final class MyCheck implements Check {
    public function id(): string { return 'custom/my-rule'; }
    public function category(): string { return Category::QUALITY; }
    public function description(): string { return 'Whatever'; }
    public function run(CheckContext $context): array {
        // return Finding[]
    }
}

$doctor = new \LaravelDoctor\Doctor([new MyCheck(), ...\LaravelDoctor\Doctor::defaultChecks()]);
$result = $doctor->diagnose(new \LaravelDoctor\CheckContext(getcwd()));
```

## Exit codes

- `0` — score ≥ `--min-score`
- `1` — score below threshold, or fatal error

## License

MIT

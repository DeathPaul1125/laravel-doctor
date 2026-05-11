# Laravel Doctor

> Inspirado en [react-doctor](https://github.com/millionco/react-doctor). Detecta código Laravel problemático antes de que llegue a producción.

Analizador estático que escanea un proyecto Laravel y reporta problemas en cuatro dimensiones, produciendo una **puntuación de salud de 0–100** y una lista de hallazgos accionables.

- **75+** → `great` (excelente)
- **50–74** → `needs-work` (necesita trabajo)
- **<50** → `critical` (crítico)

## Instalación

```bash
composer require --dev deathpaul1125/laravel-doctor
```

## Uso

### Artisan (registrado automáticamente)

```bash
php artisan doctor
php artisan doctor --json
php artisan doctor --category=security
php artisan doctor --min-score=70       # CI: falla si la puntuación es menor a 70
php artisan doctor --baseline           # solo cuenta los problemas nuevos
php artisan doctor --update-baseline    # guarda el estado actual como referencia
php artisan doctor --html=public/doctor.html   # genera reporte HTML interactivo
```

### CLI standalone

```bash
vendor/bin/laravel-doctor .
vendor/bin/laravel-doctor . --json > doctor-report.json
vendor/bin/laravel-doctor . --category=performance
vendor/bin/laravel-doctor . --html=doctor-report.html   # HTML interactivo
```

### Dashboard web (montado automáticamente fuera de producción)

Al instalar el paquete, el ServiceProvider monta dos rutas — pero **solo** cuando `APP_ENV` es `local`, `development`, `testing` o `staging`:

- `GET /doctor` → dashboard HTML interactivo
- `GET /doctor/json` → JSON crudo

Solo abre `http://tu-app.test/doctor` mientras desarrollas.

## Checks incluidos (20)

### Seguridad
| ID                                | Severidad     | Detecta                                                              |
| --------------------------------- | ------------- | -------------------------------------------------------------------- |
| `security/mass-assignment`        | HIGH          | Modelos sin `$fillable`/`$guarded`                                   |
| `security/raw-query-injection`    | CRITICAL      | `whereRaw`, `DB::raw`, etc. con interpolación de variables           |
| `security/blade-unescaped`        | HIGH          | `{!! $var !!}` en Blade (riesgo de XSS)                              |
| `security/app-config`             | CRITICAL/LOW  | `APP_DEBUG=true` en producción, `APP_KEY` vacío, `.env.example` ausente |
| `security/env-outside-config`     | MEDIUM        | `env()` llamado fuera de `config/`                                   |
| `security/hardcoded-secrets`      | CRITICAL      | Claves de AWS/Google/Slack/GitHub, JWTs, bloques PEM, passwords literales |
| `security/missing-csrf`           | HIGH          | `<form method="POST">` sin `@csrf`                                   |
| `security/request-all-to-fill`    | HIGH          | `Model::create($request->all())` y similares                         |
| `security/disabled-tls-verify`    | HIGH          | `'verify' => false`, `withoutVerifying()`, `CURLOPT_SSL_VERIFYPEER => false` |

### Rendimiento
| ID                                | Severidad | Detecta                                                  |
| --------------------------------- | --------- | -------------------------------------------------------- |
| `performance/possible-n-plus-one` | MEDIUM    | Acceso a relaciones dentro de loops sin eager-loading    |
| `performance/load-all-rows`       | MEDIUM    | `Model::all()` en controllers/jobs/commands              |
| `performance/missing-fk-index`    | MEDIUM    | Columnas de migración que terminan en `_id` sin `->index()`/FK |

### Arquitectura
| ID                                       | Severidad | Detecta                                          |
| ---------------------------------------- | --------- | ------------------------------------------------ |
| `architecture/fat-controller`            | MEDIUM    | Controllers con >250 líneas o métodos con >60    |
| `architecture/validation-in-controller`  | LOW       | `$request->validate()` inline en el controller   |
| `architecture/unnamed-route`             | LOW       | `Route::get(...)` sin `->name(...)`              |
| `architecture/closure-route`             | LOW       | Rutas con closure (bloquean `route:cache`)       |
| `architecture/missing-migration-down`    | LOW       | Archivos de migración con `down()` vacío o ausente |

### Calidad
| ID                              | Severidad | Detecta                                          |
| ------------------------------- | --------- | ------------------------------------------------ |
| `quality/unused-import`         | LOW       | `use` statements no referenciados                |
| `quality/duplicate-migration`   | MEDIUM    | Misma tabla creada en más de una migración       |
| `quality/debug-statements`      | MEDIUM    | `dd`, `dump`, `var_dump`, `print_r`, `ray`       |

## Configuración

Coloca un archivo `.laravel-doctor.json` en la raíz del proyecto:

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

- `disable` — lista de IDs de checks a omitir completamente.
- `ignore` — rutas/globs a ignorar, o objetos `{path, check, line}` para control más fino. Soporta `**` para profundidad arbitraria y `*` para un solo segmento.

## Flujo con baseline

Para proyectos legacy con miles de problemas preexistentes, toma una foto del estado actual y haz que solo fallen los problemas **nuevos**:

```bash
# Captura el estado actual como aceptado
php artisan doctor --update-baseline
git add .laravel-doctor.baseline.json
git commit -m "chore: baseline de laravel-doctor"

# Las siguientes corridas ocultan los hallazgos conocidos y solo puntúan los nuevos
php artisan doctor --baseline --min-score=80
```

## Salida de ejemplo

```
  Laravel Doctor — /var/www/mi-app

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

## Salida JSON (para CI)

```json
{
  "project": "/var/www/mi-app",
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

El workflow viene incluido en [`.github/workflows/laravel-doctor.yml`](.github/workflows/laravel-doctor.yml):

- Instala PHP 8.2 y Composer
- Ejecuta `laravel-doctor . --json --baseline --min-score=80`
- Sube el reporte JSON como artefacto
- Publica un comentario con los 25 hallazgos principales en los PRs
- Falla el build si la puntuación cae por debajo de 80

Cópialo a cualquier repo Laravel para tener el mismo flujo.

## Agregar checks personalizados

```php
use LaravelDoctor\{Check, CheckContext, Finding, Category, Severity};

final class MiCheck implements Check {
    public function id(): string { return 'custom/mi-regla'; }
    public function category(): string { return Category::QUALITY; }
    public function description(): string { return 'Lo que sea'; }
    public function run(CheckContext $context): array {
        // retorna Finding[]
    }
}

$doctor = new \LaravelDoctor\Doctor([new MiCheck(), ...\LaravelDoctor\Doctor::defaultChecks()]);
$result = $doctor->diagnose(new \LaravelDoctor\CheckContext(getcwd()));
```

## Códigos de salida

- `0` — puntuación ≥ `--min-score`
- `1` — puntuación por debajo del umbral, o error fatal

## Licencia

MIT

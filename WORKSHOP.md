# Workshop: Symfony AI Agent auf einer Sulu-Seite

Notizen für den Vortragenden. Die Teilnehmer tippen nicht, du codest live und zeigst vor. Jeder Branch ist der fertige Stand seines Schritts und dein Sicherheitsnetz, wenn das Live-Coding hängt.

## Ablauf (60 Minuten, Zeiten geschätzt, noch nicht gestoppt)

| Zeit | Block | Inhalt |
|---|---|---|
| 0 bis 8 | Folien Teil 1 | Titel, Agent, Prompt und Tools, Tool-Schleife, vom Modell zum Agent |
| 8 bis 28 | Live-Coding mit Symfony AI | 02 Wetter-Agent, 03 Produkt-Tools, 04 Chat zeigen |
| 28 bis 33 | Folien Teil 2 | Was ein Agent in Produktion braucht, die Sulu AI Agents API |
| 33 bis 48 | Live-Coding mit sulu.ai | 05 Umstellung, 06 Rückfrage (optional) |
| 48 bis 60 | Fragen | |

Wird die Zeit knapp: 06 weglassen, 04 nur zeigen.

## Branches

Jeder baut auf dem vorigen auf. Zeigen mit `git diff 01-start 02-weather-console`, `git diff 02-weather-console 03-product-tools` und so weiter.

| Branch | Inhalt | Du tippst |
|---|---|---|
| `01-start` | Sulu, Product Bundle, 15 Pflanzen | nichts |
| `02-weather-console` | Wetter-Agent in der Console | `ai.yaml`, `GetWeather`, `AgentCommand` |
| `03-product-tools` | Agent bekommt die Produkt-Tools des Bundles | vier Zeilen in `ai.yaml`, Prompt |
| `04-ui` | Chat-Endpoint und Widget auf der Startseite | nichts, erklären |
| `05-sulu-ai` | Umstellung auf sulu.ai | `ai.yaml`, `sulu_ai_platform.yaml` |
| `06-ask-user` | Agent fragt zurück (optional) | Controller und Formular |

- **02**: `config/packages/ai.yaml` (Plattform, Agent, Prompt), `src/Ai/GetWeather.php` (`#[AsTool]`, `__invoke`), `src/Command/AgentCommand.php` (Schleife mit wachsender `MessageBag`).
- **03**: vier `sulu_product.ai_*` Services unter `tools:` in `ai.yaml`, Prompt in `config/ai/plant_finder.md`. Kein eigener Code.
- **05**: `ai.yaml` (`platform` und `model`), neue Datei `config/packages/sulu_ai_platform.yaml`. Die Controller-Datei bleibt gleich.
- **06**: Controller (`server_tools`, `AgentInputRequiredException`) und Formular im Widget.

## Vorbereitung

- `.env.local`: `OPENAI_API_KEY` (Branch 2 bis 4) und `SULU_AI_PLATFORM_API_KEY` (Branch 5 und 6). Die Datei ist nicht im Git.
- `docker compose up -d database` (Port 3317), dann nach jedem Branch-Wechsel `composer install` und `bin/workshop-reset`.
- Server: `symfony server:start -d --no-tls --port=8123`. Admin: `http://127.0.0.1:8123/admin`, Login `admin` / `admin`.
- Folien: `talk.html` auf `main`, im Browser öffnen. Die Markdown-Quelle liegt im Repo `sulu-slides-template`, Branch `sulu-touch-2026-workshop`.
- Stand wechseln: im Checkout `<pfad-zu-main>/bin/stand 03` (oder `05 smoke`). Das Skript liegt auf `main`, weil die Stand-Branches es nicht tragen. Es wechselt den Branch, macht `composer install` und `bin/workshop-reset` und startet den Server.
- Smoke-Test: `bin/smoke` (aktueller Branch) oder `bin/smoke-all` (alle sechs). Ohne Key prüft er nur die Verkabelung.

## Wichtig

- Branch 02 bis 04 laufen mit dem veröffentlichten Sulu AI Platform Bundle und eigener Infrastruktur. Das kann jeder nachbauen.
- Branch 05 und 06 nutzen die Agents API der Plattform. Sie ist noch nicht veröffentlicht und kommt im Q4 2026. Die Branches pinnen dafür Entwicklungsstände der Bundles (`composer.json`). Das gilt nur für dein Setup. Die Folien sagen "Coming in Q4 2026".
- `symfony/ai-*` steht auf `^0.14`. `composer.lock` ist im Git.
- Der Fork des Product Bundles ist eine Einzelstelle.
- Die Bundle-Tools filtern nach den Schlüsseln der Optionen (`shade`, `full-sun`, `yes`, `easy`). Der Agent holt sie sich mit `sulu_product_get_attributes`.
- Beide Plattformen liefern keine Token-Streams. Der Chat wartet auf die ganze Antwort und zeigt danach die Tool-Schritte.
- Nach einem Branch-Wechsel Cache in beiden Kontexten leeren. `bin/workshop-reset` macht das.

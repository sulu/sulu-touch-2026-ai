# Workshop: Symfony AI Agent auf einer Sulu-Seite

Sechs Branches. Jeder baut auf dem vorigen auf und enthält den fertigen Stand seines Schritts.

| Branch | Inhalt | Du tippst |
|---|---|---|
| `01-start` | Sulu, Product Bundle, 15 Pflanzen, Folien in `talk/` | nichts |
| `02-weather-console` | Wetter-Agent in der Console | `ai.yaml`, `GetWeather`, `AgentCommand` |
| `03-product-tools` | Agent bekommt die Produkt-Tools des Bundles | vier Zeilen in `ai.yaml`, Prompt |
| `04-ui` | Chat-Endpoint und Widget auf der Startseite | nichts, erklären |
| `05-sulu-ai` | Umstellung auf sulu.ai | `ai.yaml`, `sulu_ai_platform.yaml` |
| `06-ask-user` | Agent fragt zurück (optional) | Controller und Formular |

## Vorbereitung

- `.env.local`: `OPENAI_API_KEY` (Branch 2 bis 4) und `SULU_AI_PLATFORM_API_KEY` (Branch 5 und 6). Die Datei ist nicht im Git.
- `docker compose up -d database` (Port 3317), dann nach jedem Branch-Wechsel `composer install` und `bin/workshop-reset`.
- Server: `symfony server:start -d --no-tls --port=8123`. Admin: `http://127.0.0.1:8123/admin`, Login `admin` / `admin`.
- Folien: `cd talk && npm install && npm run dev`, oder `npm run build` für eine HTML-Datei ohne Netz.
- Smoke-Test: `bin/smoke` (aktueller Branch) oder `bin/smoke-all` (alle sechs). Ohne Key prüft er nur die Verkabelung.

## Zum Tippen (Diff zum vorigen Branch)

Zeigen mit `git diff 01-start 02-weather-console`, `git diff 02-weather-console 03-product-tools` und so weiter.

- **02**: `config/packages/ai.yaml` (Plattform, Agent, Prompt), `src/Ai/GetWeather.php` (`#[AsTool]`, `__invoke`), `src/Command/AgentCommand.php` (Schleife mit wachsender `MessageBag`).
- **03**: vier `sulu_product.ai_*` Services unter `tools:` in `ai.yaml`, Prompt in `config/ai/plant_finder.md`. Kein eigener Code.
- **05**: `ai.yaml` (`platform` und `model`), neue Datei `config/packages/sulu_ai_platform.yaml`. Die Controller-Datei bleibt gleich.
- **06**: Controller (`server_tools`, `AgentInputRequiredException`) und Formular im Widget.

## Wichtig

- `symfony/ai-*` steht auf `^0.14`. Branch 5 braucht die Bundle-Branches mit 0.14 (MR !71 und !123), siehe `composer.json`.
- Der Fork des Product Bundles ist eine Einzelstelle. `composer.lock` ist im Git.
- Die Bundle-Tools filtern nach den Schlüsseln der Optionen (`shade`, `full-sun`, `yes`, `easy`). Der Agent holt sie sich mit `sulu_product_get_attributes`.
- Beide Plattformen liefern keine Token-Streams. Der Chat wartet auf die ganze Antwort und zeigt danach die Tool-Schritte.
- Nach einem Branch-Wechsel Cache in beiden Kontexten leeren. `bin/workshop-reset` macht das.

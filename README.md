# A Symfony AI agent on a Sulu page

Workshop at Sulu:Touch 2026. We build an agent with Symfony AI, give it the product tools of a Sulu bundle and put a chat on a Sulu page. In the last steps the agent moves to the sulu.ai platform with a config change. These steps use the Sulu AI agents API, which is not released yet and comes in Q4 2026.

The code lives in branches. Each branch builds on the previous one and holds the finished state of its step.

| Branch | What you get |
|---|---|
| [`01-start`](../../tree/01-start) | Sulu skeleton, product bundle, 15 houseplants |
| [`02-weather-console`](../../tree/02-weather-console) | A weather agent in the console, on OpenAI |
| [`03-product-tools`](../../tree/03-product-tools) | The same agent gets the product tools of the bundle |
| [`04-ui`](../../tree/04-ui) | A chat endpoint and a chat widget on the start page |
| [`05-sulu-ai`](../../tree/05-sulu-ai) | The same app on the sulu.ai platform |
| [`06-ask-user`](../../tree/06-ask-user) | The agent asks the visitor back |

`git diff 02-weather-console 03-product-tools` shows what a step changes.

## Requirements

- PHP 8.2 or newer, Composer, Docker
- Node.js for the slides
- `OPENAI_API_KEY` for branches 02 to 04
- For branches 05 and 06: a `SULU_AI_PLATFORM_API_KEY`, the base URI of the platform and access to the private Sulu Composer repository. They pin development versions of the bundles until the agents API is released.

## Start

```bash
git checkout 01-start
composer install
printf 'APP_ENV=dev\nOPENAI_API_KEY=...\n' > .env.local   # add the other keys here too
bin/workshop-reset
symfony server:start -d --no-tls --port=8123
```

- Site: http://127.0.0.1:8123
- Admin: http://127.0.0.1:8123/admin, login `admin` / `admin`
- The database runs in Docker on port 3317.

After every branch change run `composer install` and `bin/workshop-reset`. `bin/stand 03` on `main` does both: call it by path from inside a checkout of a stand branch. The script rebuilds the database, the 15 plants and the search index.

## Slides

`talk.html` on `main` is the finished deck: one file, no network needed for the images. Open it in a browser. The Markdown source is on the branch `sulu-touch-2026-ai-workshop` of the `sulu-slides-template` repository.

## Check a branch

`bin/smoke` runs the checks for the current branch. `bin/smoke-all` runs every branch. Without a key it only checks the wiring, with a key the agent answers for real.

## Notes for the speaker

See [WORKSHOP.md](WORKSHOP.md).

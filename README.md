# Trailhead

Trailhead is a hosted web application for managing and automating BSA Scouting advancement entry into Scoutbook Plus (advancements.scouting.org).

## Overview

Trailhead acts as a front-end intake and session management layer. Leaders enter scout names and achievements, review them in a queue, then hand off to an AI-assisted automation flow that logs into Scoutbook Plus and enters the advancements.

## Stack

- **Backend:** PHP 8.x
- **Database:** MySQL (Cloudways hosted)
- **Frontend:** Vanilla HTML/CSS/JS (no framework)
- **Hosting:** Cloudways

## Project Structure

```
Trailhead/
├── public/          # Web root — entry point & static assets
├── src/             # PHP application logic (routes, controllers, models)
├── db/              # SQL schema and migrations
├── docs/            # Specs, architecture notes, changelogs
└── README.md
```

## Version

Currently in **v1 development**. See `docs/trailhead-v1-spec.md` for full specification.

# Trailhead v1 — Product Specification

## Purpose

Trailhead is a hosted web application for BSA troop leaders to manage and automate the entry of Scout advancements into Scoutbook Plus (advancements.scouting.org). It acts as a front-end intake and review layer, with an AI-assisted automation handoff to perform the actual data entry.

---

## Goals

- Allow leaders to enter scout names and achievements in a structured, reviewable format
- Queue advancements for review before submission
- Hand off a clean, validated batch to an AI automation agent ("Computer") that logs into Scoutbook Plus and enters each item
- Log results of each run and flag items that were already approved or encountered errors
- Avoid duplicate entries by checking current approval state before acting

---

## Users

- **Troop Leader** — primary user; enters advancements, reviews queue, triggers runs
- **AI Agent (Computer)** — receives a structured prompt/session and performs Scoutbook Plus entry

---

## Core Workflow

1. Leader logs into Trailhead
2. Leader creates a **Session** (named batch, e.g. "April 2026 Court of Honor")
3. Leader adds **Scouts** and their **Advancements** to the session
4. Leader reviews the **Queue** — confirms all items look correct
5. Leader clicks **Send to Computer** — generates a structured prompt
6. Computer receives the prompt, logs into Scoutbook Plus, and enters each advancement
7. Computer reports back: **Entered**, **Already Approved**, or **Error** per item
8. Trailhead logs the result in **Run History**

---

## Key Rules

- Before entering any advancement, Computer must verify it is not already **Approved** in Scoutbook Plus
- If already approved: report `Result: Already approved` — do not re-enter
- If entry succeeds: report `Result: Entered`
- If error occurs: report `Result: Error — [reason]`
- All results are stored in `run_history` and surfaced in the Trailhead UI

---

## Pages / Views

| Route | Description |
|---|---|
| `/` | Dashboard — active sessions, recent runs |
| `/login` | Leader login |
| `/sessions` | Session list + create new |
| `/sessions/{id}` | Session detail — advancements list, queue review |
| `/queue` | Global queue view across all sessions |
| `/run` | Trigger a run — generates Computer prompt |
| `/run/{id}` | Run history detail — per-item results |

---

## Database Tables

| Table | Purpose |
|---|---|
| `users` | Troop leaders |
| `scouts` | Scout records |
| `sessions` | Advancement batches |
| `advancements` | Line items per session |
| `run_history` | Log of automation runs |

See `db/schema.sql` for full DDL.

---

## Tech Stack

- **PHP 8.x** — backend routing and logic
- **MySQL** — persistence (Cloudways hosted)
- **Vanilla JS / HTML / CSS** — no framework dependency
- **Cloudways** — hosting platform

---

## v1 Scope (In)

- User authentication (login/logout)
- Scout CRUD
- Session CRUD
- Advancement entry per session
- Queue review UI
- Prompt generation for Computer handoff
- Run history logging
- Already-approved detection logic

## v1 Scope (Out / Future)

- Voice dictation input
- Multi-troop support
- Email notifications
- Direct API integration with Scoutbook Plus
- Role-based access (multiple leader accounts per troop)

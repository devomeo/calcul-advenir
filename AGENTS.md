Project: Advenir Eligibility Wizard (WordPress Plugin)

You are an autonomous software agent working on a brand-new WordPress plugin that guides users through a short, progressive questionnaire to determine eligibility for the French Advenir EV-charging subsidy and to estimate the aid amount (per charging point and total).

Important: This repository is empty. You must create the entire plugin from scratch, following the instructions and acceptance criteria below. Deliver your work as a single Pull Request with clean, atomic commits and a detailed PR description.

1) Goals

Build a production-ready plugin named “Advenir Eligibility Wizard” that provides:

A shortcode: [advenir_wizard] to render a progressive Q&A wizard.

A REST API namespace aew/v1 with endpoints:

GET /aew/v1/config → returns the current questions + result texts.

POST /aew/v1/evaluate → evaluates answers against scenarios and returns eligibility + computed amounts.

An Admin page to store a JSON ruleset (questions, scenarios, texts). Admin must validate basic JSON; if invalid, show actionable errors and do not overwrite existing valid config.

A lightweight, accessible front-end wizard (plain JS) that walks the user through questions, summaries answers, calls REST, and shows “eligible/not eligible” + amounts.

No PII storage: do not store user answers server-side; all evaluation is stateless per request.

Configurable barèmes: JSON in admin lets site owners maintain Advenir rates/caps/conditions without code edits.

2) Non-Goals

No collection or storage of personal data.

No complex build system (no React build required).

No third-party dependencies unless strictly necessary.

3) Technical Constraints & Standards

WordPress: 6.4+ compatible, PHP 8.0+.

Security: Use nonces for REST POST; sanitize and escape inputs/outputs; cap checks for admin pages.

Internationalization: Text domain advenir-eligibility-wizard; all user-facing strings translatable.

Coding Standards: WordPress Coding Standards (PHPCS) for PHP; sensible lint for JS/CSS.

Accessibility: Basic a11y (labels linked to inputs, keyboard navigation, aria-live for result area, focus management).

Performance: Only enqueue assets on pages that render the shortcode (when feasible).

4) Deliverables (Files & Structure)

Create the following structure:

advenir-eligibility-wizard.php
includes/
  class-aew-admin.php
  class-aew-frontend.php
  class-aew-rest.php
assets/
  js/wizard.js
  css/wizard.css
languages/
  (placeholder, e.g., .pot later)
readme.txt
.gitignore


.gitignore: include node_modules/, vendor/, .DS_Store, *.zip, *.log.

Main file header must include: name, description, version, author, license, text domain, domain path.

5) Functional Specification
5.1 Admin Rules JSON

Admin menu: “Advenir Wizard” (capability: manage_options).

A single textarea stores/edits JSON in WP option key: aew_rules_json.

Validation:

On save: json_decode; if invalid, show an error (Settings API notice) and keep previous value.

If empty, prefill with a default example JSON (below).

Help text under the textarea: remind site admins to verify official Advenir barèmes regularly.

Default example JSON (values are illustrative, not official—admin must update):

{
  "_meta": {
    "last_updated": "2025-09-26T00:00:00Z",
    "currency": "EUR",
    "notes": "Exemples. Mettez à jour selon les barèmes Advenir en vigueur."
  },
  "questions": [
    {
      "id": "beneficiary",
      "label": "Qui êtes-vous ?",
      "type": "single",
      "required": true,
      "options": [
        {"value": "particulier", "label": "Particulier (maison individuelle)"},
        {"value": "copro", "label": "Copropriété (parking collectif)"},
        {"value": "entreprise_prive", "label": "Entreprise — Parking privé réservé aux salariés"},
        {"value": "entreprise_public", "label": "Entreprise — Parking ouvert au public"},
        {"value": "collectivite", "label": "Collectivité / acteur public"}
      ]
    },
    {
      "id": "site",
      "label": "Où se situe l’installation ?",
      "type": "single",
      "required": true,
      "options": [
        {"value": "residence_principale", "label": "Résidence principale"},
        {"value": "residence_secondaire", "label": "Résidence secondaire"},
        {"value": "site_entreprise", "label": "Site d’entreprise / local professionnel"}
      ]
    },
    {
      "id": "usage",
      "label": "Usage de la borne",
      "type": "single",
      "required": true,
      "options": [
        {"value": "usage_prive", "label": "Recharge privative"},
        {"value": "usage_partage", "label": "Recharge partagée (plusieurs utilisateurs)"},
        {"value": "usage_public", "label": "Recharge ouverte au public"}
      ]
    },
    {
      "id": "smart",
      "label": "Pilotage intelligent (smart charging)",
      "type": "single",
      "required": true,
      "options": [
        {"value": "oui", "label": "Oui"},
        {"value": "non", "label": "Non / je ne sais pas"}
      ]
    },
    {
      "id": "power",
      "label": "Puissance par point de charge",
      "type": "single",
      "required": true,
      "options": [
        {"value": "<=7kW", "label": "≤ 7,4 kW"},
        {"value": "7_22kW", "label": "> 7,4 kW à 22 kW"},
        {"value": ">22kW", "label": "> 22 kW (AC/DC)"}
      ]
    },
    {
      "id": "nb_points",
      "label": "Nombre de points de charge installés",
      "type": "number",
      "required": true,
      "min": 1,
      "max": 200
    },
    {
      "id": "est_cost",
      "label": "Coût estimé éligible par point (optionnel)",
      "type": "currency",
      "required": false,
      "min": 0
    }
  ],
  "scenarios": [
    {
      "id": "particulier_dom_7kW",
      "label": "Particulier — Maison — ≤7,4 kW",
      "conditions": {
        "beneficiary": ["particulier"],
        "site": ["residence_principale","residence_secondaire"],
        "usage": ["usage_prive"],
        "power": ["<=7kW"]
      },
      "calc": {"type": "rate_cap", "rate": 0.5, "cap": 500}
    },
    {
      "id": "copro_collectif_22kW",
      "label": "Copropriété — Infrastructure collective ≤22 kW",
      "conditions": {
        "beneficiary": ["copro"],
        "usage": ["usage_partage"],
        "power": ["<=7kW","7_22kW"]
      },
      "calc": {"type": "rate_cap", "rate": 0.5, "cap": 960}
    },
    {
      "id": "entreprise_parking_public_dc",
      "label": "Entreprise — Parking ouvert au public — >22 kW",
      "conditions": {
        "beneficiary": ["entreprise_public"],
        "usage": ["usage_public"],
        "power": [">22kW"]
      },
      "calc": {"type": "rate_cap", "rate": 0.4, "cap": 2000}
    },
    {
      "id": "entreprise_parking_prive_ac",
      "label": "Entreprise — Parking privé réservé — ≤22 kW",
      "conditions": {
        "beneficiary": ["entreprise_prive"],
        "usage": ["usage_partage","usage_prive"],
        "power": ["<=7kW","7_22kW"]
      },
      "calc": {"type": "rate_cap", "rate": 0.3, "cap": 960}
    },
    {
      "id": "collectivite_public_ac",
      "label": "Collectivité — Ouvert au public — ≤22 kW",
      "conditions": {
        "beneficiary": ["collectivite"],
        "usage": ["usage_public"],
        "power": ["<=7kW","7_22kW"]
      },
      "calc": {"type": "rate_cap", "rate": 0.5, "cap": 1500}
    }
  ],
  "result_texts": {
    "eligible": "🎉 Vous êtes potentiellement éligible à la prime Advenir pour le scénario « {{scenario_label}} ».",
    "not_eligible": "❌ Selon vos réponses, la configuration ne correspond pas aux critères habituels de la prime Advenir.",
    "footnote": "ℹ️ Estimation indicative à confirmer selon le dossier et les justificatifs. Les montants et plafonds peuvent évoluer."
  }
}


Behavior for rate_cap: if est_cost is provided and >0, use min(est_cost * rate, cap) for amount per point; else show cap as “jusqu’à … par point”.

5.2 Front-End Wizard (Shortcode)

Shortcode: [advenir_wizard].

Renders a card layout:

Header: title + hint “Aucune donnée enregistrée”.

Body: step area (one question per step), Previous/Next controls, progress state.

Footer: small disclaimer (“Montants indicatifs…”).

Logic:

Fetch questions via GET /aew/v1/config.

Step by step answers; last step shows a Summary (answers recap) then calls POST /aew/v1/evaluate.

Display:

If not eligible: text from result_texts.not_eligible.

If eligible: text from result_texts.eligible with {{scenario_label}}, plus:

Montant par point (formatted currency),

Nb de points,

Total estimé,

Scénario label,

Footnote text.

Accessibility:

<label for> and id for radio/inputs, keyboard navigation, aria-live="polite" on result area, focus transfer between steps.

5.3 REST API

Namespace: aew/v1.

GET /config: returns:

{ "questions": [...], "result_texts": {...} }


POST /evaluate:

Input:

{ "answers": { "<question_id>": <value>, ... } }


Output (eligible):

{
  "eligible": true,
  "scenario": {"id":"...", "label":"...", "calc": {...}},
  "per_point": 480.00,
  "nb_points": 3,
  "total": 1440.00,
  "message": "🎉 ...",
  "footnote": "ℹ️ ...",
  "used_cost": 1200.00
}


Output (not eligible):

{ "eligible": false, "message": "..." }

6) Security Requirements

Admin page: require manage_options. Escape outputs, sanitize on save. Use Settings API notices for validation errors.

REST:

GET /config: public read OK.

POST /evaluate: require WP REST nonce (header X-WP-Nonce); reject if missing/invalid.

Validate shape of answers (object), coerce numeric fields (e.g., nb_points, est_cost) to numeric types with bounds.

Escape/sanitize all outputs sent to the DOM.

No user PII is persisted.

7) UX & Styling

CSS: Clean, minimal, class-based (e.g., .aew-container, .aew-card, .aew-option, .aew-btn…), easy to override.

JS: Vanilla ES modules not required; a single IIFE is fine.

Progress state: show current step vs total (e.g., “Étape 3/7”).

Errors: prefer inline messages near fields, not alert().

8) Performance

Enqueue JS/CSS only when shortcode is present (or at least only on the page render that includes the shortcode).

Version assets with plugin version constant.

9) Internationalization

Load text domain advenir-eligibility-wizard and wrap all user-facing strings in __(), _e(), esc_html__(), etc.

Include Domain Path: /languages.

10) Developer Experience (Optional but Preferred)

Add PHPCS config (phpcs.xml) for WordPress standards.

Add a minimal readme.txt:

What it does

Installation

Shortcode usage

Admin JSON rules maintenance

Security/data note

Add .pot generation instructions (optional).

11) Acceptance Criteria

A PR will be considered complete when:

Plugin activates without errors on WordPress 6.4+ / PHP 8.0+.

Shortcode [advenir_wizard] renders the wizard and loads assets.

Wizard fetches questions via GET /aew/v1/config, navigates steps, shows a summary, then calls POST /aew/v1/evaluate.

Evaluation returns:

Eligible path: shows per-point amount (computed), nb points, total, scenario label, footnote.

Not eligible path: shows proper message.

Admin page:

Shows textarea with JSON.

Valid JSON saves and is used; invalid JSON shows a clear error and is not saved.

Security:

Admin capabilities enforced.

Nonce required for POST /evaluate.

Sanitization/escaping in place.

A11y: labels associated, keyboard nav works, aria-live on results.

i18n: strings wrapped with the correct text domain.

Docs: readme.txt includes setup instructions and JSON maintenance note.

Clean, atomic commits and a PR description that:

Explains architecture and decisions,

Lists endpoints, shortcode,

Shows sample payloads/responses,

Mentions security and i18n.

12) Work Plan for the Agent

Scaffold folders/files and plugin header; add .gitignore.

Implement Admin class (option, validation, settings page).

Implement REST class (routes, config/evaluate, nonce check, evaluation logic).

Implement Frontend class (shortcode, asset enqueue, localization/nonce).

Write wizard.js (steps UI, fetch config, collect answers, evaluate, render result) and wizard.css.

Provide default JSON in admin with illustrative scenarios.

Produce readme.txt.

Test locally (lint PHP, basic runtime checks).

Open one PR with diffs, screenshots (if possible), and a clear description referencing the Acceptance Criteria.

13) Commands (if you need them)

PHPCS (optional): if you add Composer + WPCS, run composer cs or phpcs.

No build required for JS/CSS.

14) License

Use GPL-2.0+ for the plugin. Include license header in the main file.

15) Notes for Maintainers

Barèmes Advenir évoluent : site admins must update JSON rules via the admin page.

This plugin does not constitute official advice; always verify eligibility with official documentation.

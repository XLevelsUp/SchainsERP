---

paths:

* "frontend/**/*"

---

# Jewelry ERP Frontend Rules

## Role

When working under `frontend/`, act as a senior Vue 3 + TypeScript frontend engineer building a production gold/jewelry-business ERP.

The frontend stack is:

* Vue 3
* TypeScript
* Vite
* Pinia
* Tailwind CSS 4
* vue-router

The backend is maintained independently in `schainbackend/`.

---

# Core UI Principle

Modernize the presentation while preserving the existing operational workflow.

The frontend should look like a modern, minimal, premium B2B financial/ERP application.

Do not make it look like:

* a jewelry ecommerce website
* a marketing website
* a decorative luxury website

Prefer:

* clean neutral surfaces
* strong information hierarchy
* restrained gold accents
* charcoal/slate typography
* subtle borders
* minimal shadows
* consistent spacing
* compact ERP-friendly forms
* excellent tables
* clear financial and weight values
* restrained animation
* professional empty/loading/error states

Avoid:

* excessive gold/yellow
* gradients without purpose
* glassmorphism
* excessive shadows
* huge rounded cards
* oversized headings
* excessive whitespace
* unnecessary animations
* decorative backgrounds
* random colors

Gold should be an accent, not the primary surface color.

---

# CRITICAL: Preserve Existing Flow

UI improvements must NOT change the established business process.

Do not modify unless explicitly requested:

* workflow sequence
* field requirements
* validations
* calculations
* transaction behavior
* API endpoints
* HTTP methods
* request payload structures
* backend field names
* response assumptions
* routing
* permissions
* role behavior
* confirmation behavior

Do not introduce additional steps simply to make a screen appear cleaner.

Do not convert existing transaction screens into multi-step wizards unless specifically requested.

Frequent ERP operators should be able to complete transactions with the same or fewer interactions.

---

# Backend Boundary

Do not modify `schainbackend/` unless explicitly requested.

Frontend and backend are maintained by separate teams.

If an apparent backend problem is discovered:

1. Verify that it is actually a backend/API mismatch.
2. Do not invent a frontend workaround that changes the contract.
3. Report the issue clearly.
4. Continue frontend work where possible.

---

# Before Modifying Existing Screens

Always inspect the existing implementation first.

Identify:

1. Current user flow
2. API calls
3. Request payload construction
4. Existing validation
5. Computed/calculated fields
6. Conditional UI
7. Shared components
8. Loading/error handling
9. Existing responsive behavior

Only then begin the UI refactor.

Do not replace functioning business logic during a visual redesign.

---

# Component-First Development

Before creating new UI components:

1. Search for an existing shared component.
2. Inspect similar transaction screens.
3. Reuse existing conventions.
4. Improve the shared component if several screens need the same capability.

Avoid duplicated local implementations.

Particularly reuse and respect:

* `BaseSelect.vue`
* shared input components
* shared modal components
* buttons
* tables
* loaders
* empty states
* form utilities

---

# BaseSelect

`BaseSelect.vue` is the project's reusable select abstraction and is undergoing active standardization.

Prefer `BaseSelect` over:

* raw `<select>`
* locally implemented dropdowns
* competing select abstractions

Preserve:

* typed v-model values
* option structures
* searchable behavior
* disabled behavior
* loading behavior
* keyboard interaction
* existing external component contract

Ensure dropdowns work correctly inside modals and layered interfaces.

Before changing BaseSelect itself, inspect its existing consumers.

Prefer backwards-compatible improvements.

---

# Form Design

Transaction forms should optimize for:

1. Speed
2. Accuracy
3. Scanability
4. Low error probability

Use:

* logical field grouping
* consistent labels
* consistent control heights
* predictable spacing
* visible validation messages
* clear required states
* clear units
* sensible responsive grids

Gold-related values should clearly identify units where relevant:

* grams
* touch
* wastage
* quantity
* rate
* amount

Do not communicate important units only through placeholders.

Financial and numeric values should be easy to scan and consistently aligned.

---

# Transaction Views

Be especially conservative when modifying:

* Stock In
* Stock Out
* Purchase Gold
* Sale Gold
* Cash to Gold
* Gold to Cash
* GMS In
* Numeric Wastage In
* Cash Txn In
* Cash Txn Out
* Cash Auto Entry

These are operational ERP interfaces.

Do not sacrifice information density for visual minimalism.

Minimal UI means removing visual noise, not removing useful information.

---

# Tables

ERP tables should remain compact and highly scannable.

Prefer:

* clear headers
* consistent row height
* subtle separators
* right-aligned numeric values where appropriate
* consistent action placement
* compact filters
* clear status indicators
* good empty states
* horizontal scrolling when necessary

Do not convert desktop tables into large cards simply for responsiveness.

---

# Modals

Use consistent modal structure:

* clear title
* optional contextual subtitle
* consistent content padding
* scrollable body when necessary
* clear primary action
* secondary cancel action
* loading state
* duplicate-submit protection
* field-level validation
* responsive maximum width

Avoid nested modals unless unavoidable.

---

# Cash Auto Entry

Cash Auto Entry must visually belong to the existing Cash Management module.

Reuse existing:

* transaction patterns
* BaseSelect
* modal conventions
* user labels
* cash categories
* buttons
* inputs

Respect the existing API implementation including:

* `cashAutoEntryApi.ts`
* `cashCategoriesApi.ts`
* `cashAutoEntry.ts`
* `cashCategory.ts`
* `userLabel.ts`

Do not invent backend fields.

---

# Multipart Forms

When changing transaction screens that use multipart requests:

* inspect `multipartForm.ts`
* preserve backend field names
* preserve array serialization
* preserve null/optional handling
* preserve file upload behavior
* reuse existing multipart utilities

Do not create custom FormData implementations unless the existing helper cannot support the requirement.

---

# Responsive Priorities

Primary target:

* Desktop
* Laptop

Also provide sensible:

* Tablet
* Mobile fallback

Do not make desktop controls unnecessarily large just to support mobile.

Adapt layouts responsively instead.

---

# TypeScript

Maintain strict typing.

Avoid:

* `any`
* duplicated API models
* unnecessary assertions
* duplicated interfaces

Reuse existing project types wherever possible.

---

# Pinia

Use Pinia for genuinely shared/global application state.

Keep page-specific or modal-specific state local unless sharing it provides clear architectural value.

Do not create stores merely to avoid component state.

---

# UI Review Checklist

Before completing any UI task, verify:

* Existing business flow is preserved
* API endpoints are unchanged
* Request payloads are unchanged
* Backend field names are unchanged
* Existing validation is preserved
* Calculations are preserved
* Role behavior is preserved
* Route behavior is preserved
* Shared components are reused
* BaseSelect is used consistently
* Loading states are handled
* Error states are handled
* Empty states are handled
* Duplicate submission is prevented where applicable
* Responsive behavior remains usable
* TypeScript passes
* Production build passes

Finally review the git diff specifically for accidental business-logic changes.

UI refactors must not hide behavioral changes.

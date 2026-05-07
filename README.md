# Miraj Hotel Suite

Multi-tenant SaaS Hotel Management Platform — modular ERP for hotels and resorts.

> Inspired by the IDS Next FortuneNext / FX product family. This codebase is a Phase 1–2 foundation, **not a finished product.** See "Honest scope" below.

---

## Architecture at a glance

- **Stack:** Laravel 11 + PHP 8.3 + PostgreSQL 15 + Redis 7 + Inertia.js + React 18
- **Multi-tenancy:** single DB, fail-closed global scopes, subdomain or header-based tenant resolution
- **Module system:** every feature is a module that can be turned on/off per tenant or per property; module gates enforced at the route + controller layer
- **Channel manager:** driver-pattern abstraction (AxisRooms today, STAAH/SiteMinder/RateGain pluggable)
- **Integrations:** abstracted gateways for payments, door locks, ID scanners, with vendor-specific adapters

---

## Module catalog

Every module ships with the codebase but is independently toggleable. Each tenant's plan determines defaults; tenants can buy add-ons or super-admins can override per property.

### Core (always on)

| Module | Code | What it does |
|---|---|---|
| Property Management | `pms` | Reservations, check-in/out, folios, room inventory, rate plans, multi-property |
| User & Role Management | `users` | 14 roles per IDS-Next playbook, granular permissions |
| Standard Reports | `reports` | Occupancy, ARR, RevPAR, daily flash, audit trail |

### Operations

| Module | Code | What it does |
|---|---|---|
| Point of Sale (POS) | `pos` | Restaurant/bar/room-service order management with split-by-station printing |
| Kitchen Display System | `kds` | Cook-side tickets with priority + age-based color escalation, recall, prep timing analytics |
| Banquet & Sales/Catering | `banquet` | Hall booking, package quoting, F&B requirements, BEO printing |
| Housekeeping | `housekeeping` | Room status board, task assignment, lost & found, mobile RA app |
| Store / Materials | `store` | Inventory, vendors, purchase orders, GRN, stock movements |
| Maintenance Engineering | `maintenance` | Tickets, preventive schedules, asset register |
| Spa & Wellness | `spa` | Therapist roster, treatment menu, appointment scheduling |

### Distribution

| Module | Code | What it does |
|---|---|---|
| Channel Manager | `channel_manager` | OTA inventory + rate sync via AxisRooms, STAAH, SiteMinder |
| Direct Booking Engine | `booking_engine` | On-property booking widget |
| Hotel Website (CMS) | `cms` | Hosted multi-page hotel website with theme system + page builder |
| Central Reservation System | `crs` | Multi-property unified inventory for chains |

### Finance

| Module | Code | What it does |
|---|---|---|
| Financial Management | `accounts` | Chart of accounts, vouchers, GST returns (GSTR-1, 3B), bank reconciliation |
| Payroll & HR | `payroll` | Staff records, attendance, payroll, statutory compliance |
| F&B Cost Control | `fb_costing` | Recipe costing, plate cost analysis, variance reports |

### Marketing / CRM

| Module | Code | What it does |
|---|---|---|
| Review Management | `reviews` | Aggregated Google + TripAdvisor + Booking + MMT reviews; one inbox to respond |
| Revenue Management | `revenue` | Rate shopper, dynamic pricing rules, occupancy + ARR forecast |
| Guest CRM & Loyalty | `crm` | Profiles, segmentation, campaigns, loyalty tiers |
| Amenities / Add-ons | `amenities` | Sell extras: airport pickup, breakfast, spa packages, tours |
| WhatsApp Engagement | `whatsapp` | AiSensy / Gallabox integration for booking confirmations + service requests |
| Membership / Club | `membership` | Member cards, prepaid wallets, member rates |

### Integrations

| Module | Code | What it does |
|---|---|---|
| Door Locks | `door_locks` | Onity / Saflok / dormakaba / Salto / VingCard adapters |
| ID Scanner / OCR | `id_scanner` | Aadhaar, passport, voter ID extraction (Hyperverge / Jumio / Onfido) |
| Payment Gateway | `payment_gateway` | Razorpay (default), Stripe, PayU, CCAvenue |

---

## Honest scope of this codebase

### Production-ready in this delivery

- ✅ Multi-tenant foundation with fail-closed scopes
- ✅ Module registry with dependency resolution + per-tenant + per-property toggles
- ✅ PMS: reservation create/cancel/no-show with row-level inventory locking, check-in/out, folios with Indian GST CGST/SGST/IGST split, payments
- ✅ POS: outlet, menu, table, order with KOT split-by-station, void, discount, bill, room-charge to PMS folio, settle
- ✅ KDS: station routes, ticket lifecycle (queued → started → ready → served), recall flow, priority, urgency tier color escalation
- ✅ Channel Manager: driver interface, AxisRooms driver structure with HTTP client + auth + retry + logging, BookingImporter (OTA → reservation), InventorySyncService (push), scheduled commands
- ✅ Reviews: aggregation framework with **real TripAdvisor Content API integration**, Google + Booking.com source stubs, response tracking, summary stats
- ✅ Revenue: dynamic pricing engine with 5 rule types (occupancy, days-to-arrival, day-of-week, season, event), rate shopper schema, forecast model
- ✅ Amenities: catalog + ordering with multiple pricing types (per stay / per night / per person)
- ✅ CMS: site + page + media model with public renderer that resolves host → site, page builder block model, dynamic blocks (room_grid, amenity_list, review_widget)
- ✅ **Banquet**: enquiry → tentative → confirmed → completed lifecycle with hall availability checking, time-window conflict detection, package-based food costing, GST split (18% on services, 5% on F&B)
- ✅ **Housekeeping**: auto-task generation from arrivals/departures, VIP pre-arrival inspection, stayover scheduling, least-loaded auto-assignment, room status auto-sync on verify
- ✅ **Store**: full purchase order → GRN → stock movement chain with weighted-average cost recomputation, low-stock alerts, issue/adjustment/wastage tracking
- ✅ **Accounts**: double-entry voucher posting with balance validation, immutable reversals, trial balance computation, GSTR-1/3B return computation from posted vouchers + ITC from GRN
- ✅ **Night Audit**: full daily-close service — converts no-shows, posts room revenue to in-house folios, snapshots occupancy/ARR/RevPAR, advances business date, fail-safe rollback
- ✅ Integration adapters: Razorpay (working REST), Onity door lock (local encoder API), Hyperverge ID scanner (Aadhaar + passport OCR endpoints)

### Stubbed — needs wire-up before production

- 🟡 AxisRooms exact XML/JSON wire format — structure is in place, exact field names need verification against AxisRooms partner kit (requires partner agreement)
- 🟡 TripAdvisor / Google Business / Booking.com review APIs — endpoint shapes documented in code; needs partner credentials
- 🟡 Hyperverge / Onity API call payloads — vendor-specific contract details; signed agreements required
- 🟡 Real Aadhaar UIDAI verification — requires AUA license

### Not in this delivery (Phase 3+)

- ❌ React/Inertia frontend pages for each module (only TapeChart shipped)
- ❌ Full implementations of: Banquet (`banquet`), Housekeeping detail (`housekeeping`), Store (`store`), Maintenance (`maintenance`), Spa (`spa`), Payroll (`payroll`), F&B Costing (`fb_costing`), CRS (`crs`)
- ❌ Real ML model for revenue forecasting (current implementation: simple historical averages)
- ❌ Booking engine frontend
- ❌ Offline-capable client (RxDB / IndexedDB sync)
- ❌ Comprehensive test suite (only ReservationServiceTest sample)

### Realistic roadmap to "ship as SaaS"

| Phase | Duration | Headcount | Deliverable |
|---|---|---|---|
| Phase 1 — PMS foundation | 3 months | 4 devs | What's in this repo for `pms` module |
| Phase 2 — POS + KDS + Channel | 4 months | 4 devs | What's in this repo for `pos`, `kds`, `channel_manager` |
| Phase 3 — Frontend at parity | 6 months | 2 frontend devs | Production React UI for all modules |
| Phase 4 — Banquet + Housekeeping + Store | 4 months | 3 devs | Phase 3 feature modules from spec |
| Phase 5 — Accounts + GST returns | 4 months | 2 devs + 1 CA consultant | Production-grade Indian accounting |
| Phase 6 — Booking Engine + CMS | 3 months | 2 devs + 1 designer | Public-facing direct booking |
| Phase 7 — Revenue + Reviews | 3 months | 2 devs + 1 ML | Production rate shopper + dynamic pricing |
| Phase 8 — Channel certifications | parallel, 6–12 months | 1 integrations dev | AxisRooms + each OTA |
| Phase 9 — Production hardening | 2 months | full team | Pen-test, scale test, DR runbook |

**Total: 24-30 months with a 6-8 person team to reach IDS Next-equivalent breadth.** First sellable slice (PMS + POS + Channel) realistically 9-12 months in.

---

## Quick start

```bash
# Prerequisites: PHP 8.3+, Composer 2.6+, PostgreSQL 15+, Redis 7+, Node 20+

composer install
npm install
cp .env.example .env
php artisan key:generate

# Configure DB in .env (PostgreSQL recommended)
php artisan migrate --seed

# Seeded:
#   - 24 modules in the catalog (ModuleSeeder)
#   - 1 demo tenant: "Miraj Hotels Demo" (slug: miraj-demo)
#   - 1 property: Miraj Lake Palace, Udaipur (30 rooms, 4 room types)
#   - 14 RBAC roles + 84 permissions
#   - 3 users: admin / fom / cashier (password: 'password')
#   - POS: 2 outlets, 12 tables, 14 menu items, 4 KDS stations
#   - Modules enabled by default: pos, kds, channel_manager, reviews, revenue, amenities

npm run build
php artisan serve
php artisan queue:work    # in second terminal
```

Login at `http://localhost:8000/` with `admin@miraj-demo.test / password`.

---

## Module system mechanics

```php
// Anywhere in code:
if (app(ModuleService::class)->enabled('pos')) {
    // POS UI / API allowed
}

// Route-level:
Route::middleware('module:kds')->group(...);

// Toggle from admin UI or code:
app(ModuleService::class)->toggleForTenant($tenant, 'reviews', true);
app(ModuleService::class)->toggleForProperty($property, 'pos', false); // off at this property
```

Resolution order: property override > tenant override > plan default > module's `is_active`.

Cache: 5-minute TTL per (tenant, property, module). Auto-busts on toggle.

Dependencies: enabling `kds` checks that `pos` is enabled first.

---

## API surface (top-level)

```
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout

# PMS (always available)
GET    /api/properties, /api/properties/{id}/availability
GET    /api/reservations, POST /api/reservations
POST   /api/reservations/{id}/{cancel|no-show|check-in|check-out}
POST   /api/folios/{id}/{charges|payments|settle}

# POS (gated by module:pos)
GET    /api/pos/outlets, POST /api/pos/outlets/{outlet}/orders
POST   /api/pos/orders/{order}/items
POST   /api/pos/orders/{order}/send-to-kitchen
POST   /api/pos/orders/{order}/charge-to-folio
POST   /api/pos/orders/{order}/settle

# KDS (gated by module:kds)
GET    /api/kds/stations/{station}/queue
POST   /api/kds/tickets/{ticket}/{start|ready|served|recall}

# Reviews (gated by module:reviews)
GET    /api/reviews, /api/reviews/summary
POST   /api/reviews/{review}/respond

# Revenue (gated by module:revenue)
GET    /api/revenue/rate-recommendation
GET    /api/revenue/rate-shop
GET/POST /api/revenue/rules, /api/revenue/competitors

# Amenities (gated by module:amenities)
GET    /api/amenities, POST /api/amenities/{amenity}/order

# CMS admin (gated by module:cms)
GET    /api/cms/sites, POST /api/cms/sites/{site}/publish
POST   /api/cms/sites/{site}/pages, PATCH /api/cms/pages/{page}

# CMS public (no auth)
GET    /api/public/cms/site?host=
GET    /api/public/cms/page?host=&slug=

# Module administration
GET    /api/admin/modules
POST   /api/admin/modules/{moduleCode}/{enable|disable}
```

---

## Strategic note for Piyush

This codebase is a credible **PMS + POS + KDS + Channel Manager foundation**. Earlier guidance still stands: build for one paying client first (₹40-80L project), validate workflows with real ops, *then* productize. The same code becomes your SaaS v1 and you de-risk ~₹2-3 Cr of burn.

To clarify scale: IDS Next has 35+ years tenure, ~150 engineers, 6,000+ hotel customers, 350+ tech partner integrations. Reaching their breadth takes a 6-8 person team 24-30 months. Reaching their depth in any one module takes 6-12 months per module. Plan accordingly.

---

## License

Proprietary © Miraj Hospitality / WebSenor.

# CRM service API for CompanyOS

Read-only endpoints that let the CompanyOS hub read CRM data and pick up new events.
Nothing here can create, change or delete CRM data.

## Setup

1. Generate a key and put it in `.env` (never commit it):

   ```
   php -r "echo bin2hex(random_bytes(32));"
   COMPANYOS_SERVICE_KEY=<the key>
   ```

   Use the same key in CompanyOS. If the server uses `php artisan config:cache`, run it again after changing `.env`.

2. Run the migration (creates `outbox_events`):

   ```
   php artisan migrate --path=database/migrations/2026_11_27_100000_create_outbox_events_table.php
   ```

   Until the table exists, events are not recorded (the error is logged); sales, leads and payments still save normally.

## Authentication

Send the key on every request:

```
X-Service-Key: <key>
```

`Authorization: Bearer <key>` also works. Missing or wrong key → `401`. Key not set on the server → `503`.
Rate limit: 120 requests per minute.

## Conventions

- Base URL: `{CRM_URL}/api/v1/service`
- Success: `{ "data": ..., "meta": {...} }`
- Error: `{ "error": { "code": "VALIDATION_FAILED", "message": "...", "details": {...} } }`
- Dates: `YYYY-MM-DD`. `from`/`to` default to the current calendar month.
- Lists: `page` (default 1), `per_page` (default 25, max 100).
- Currencies: sales, invoices and PPC in **USD**; office expenses in **PKR**.

## Endpoints

| Method | Path | Query | Returns |
|---|---|---|---|
| GET | `/summary` | `from`, `to` (max 366 days) | Company figures for the range (below) |
| GET | `/sales` | `from`, `to`, `approval_status`, `status`, `team_id`, paging | Submitted sales (drafts excluded) by `sale_date` |
| GET | `/leads` | `status`, `source`, `from`/`to` (created), `follow_up_due=1`, paging | Leads |
| GET | `/invoices` | `status=unpaid` (default) \| `overdue` \| `all`, paging | Invoices with `amount_paid`, `amount_due` |
| GET | `/expenses` | `from`, `to`, `category`, paging | Office expenses (PKR) |
| GET | `/users` | paging | CRM users: name, email, role, team, account status |
| GET | `/events` | `after` (last id seen, default 0), `limit` (default 100, max 500) | New events in id order |

### `/summary` definitions

These match the CRM finance dashboard, so both show the same numbers.

| Field | Meaning |
|---|---|
| `sales.booked`, `sales.count` | Approved, not refunded sales with `sale_date` in range |
| `sales.completed_revenue` | Approved sales with status `completed` in range (CRM overview "revenue") |
| `sales.received` | Sale-linked receipts by `issued_at` + invoice payments by `paid_at`, void invoices excluded |
| `sales.refunds_total`, `refunds_count` | Sales refunded (`refunded_at`) in range |
| `sales.awaiting_approval_count` | Submitted sales still pending approval (any date) |
| `ppc.spend` | Team PPC spend for every month the range touches |
| `ppc.net_cash` | `received − ppc.spend` (PKR expenses not included) |
| `receivables.*` | Money still owed right now: open sale balances, open standalone invoices, overdue invoices |
| `leads.new_*` | Leads created in range, by status and source |
| `leads.follow_ups_due_count` | Open leads whose follow-up date is today or past |
| `expenses.total` | Office expenses in range (PKR) |
| `by_team` | Booked and received per team |

## Events

Polling pattern: call `/events?after=0`, process `data`, store `meta.next_after`, and call again with that value.
When `meta.has_more` is `true`, call again straight away. Event `id`s are unique and never reused, so the
consumer can skip any id it has already handled.

```json
{
  "id": 42,
  "source": "crm",
  "type": "sale.approved",
  "aggregate_type": "sale",
  "aggregate_id": 1187,
  "context": "live",
  "payload": { "sale_id": 1187, "amount": 1500, "currency": "USD", "...": "..." },
  "occurred_at": "2026-11-27T10:15:00+00:00"
}
```

| Type | When |
|---|---|
| `sale.submitted` | A sale is created (not as draft), or a draft is submitted |
| `sale.approved` / `sale.rejected` | Approval status changes |
| `sale.refunded` | A sale is marked refunded |
| `lead.created` | A lead is created |
| `lead.status_changed` | A lead's status changes (`previous_status` in payload) |
| `invoice.payment_received` | A payment is recorded on an invoice |

`context` is `import` for rows created by the CSV sale/lead import, so automations can skip notifications for
historical data. Otherwise it is `live`.

**Limitation:** bulk updates written as `Model::query()->update([...])` don't fire model events and so record no
event. All current create, approve and refund paths save models one by one.

## Code map

| File | Purpose |
|---|---|
| `app/Http/Middleware/VerifyServiceKey.php` | Key check (`service.key` alias) |
| `app/Http/Controllers/Api/Service/ServiceDataController.php` | Read endpoints |
| `app/Http/Controllers/Api/Service/ServiceEventController.php` | Events endpoint |
| `app/Services/CompanyOs/ServiceSummary.php` | Summary figures |
| `app/Support/Outbox.php` | Records events; never breaks the calling request |
| `app/Observers/OutboxObserver.php` | Turns Sale / Lead / InvoicePayment changes into events |
| `database/migrations/2026_11_27_100000_create_outbox_events_table.php` | Events table |
| `tests/Feature/CompanyOsServiceApiTest.php` | Tests (`php artisan test --filter=CompanyOsServiceApiTest`) |
| `docs/companyos-service-api.postman_collection.json` | Postman collection |

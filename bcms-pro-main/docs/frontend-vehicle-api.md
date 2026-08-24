# Frontend brief: vehicle listing, detail view, and update

This document describes the BCMS Pro **HTTP JSON APIs** for vehicle **list**, **view**, and **update**. Paths are relative to the API prefix (e.g. `https://<host>/api`).

## Back office — step 1: list vehicles only

Use **`GET /vehicles/all`** for a paginated grid. The backend runs one **count** query and one **paginated select** (fixed column list, two `LEFT JOIN`s, no N+1). Request a **reasonable `per_page`** (default `15`; the API caps **`per_page` at 100** so a client cannot accidentally pull thousands of rows in one request). Use `search` and `sort_*` so users filter instead of loading huge pages.

## Authentication

All endpoints below use **`auth:sanctum`**. Send the session cookie or `Authorization: Bearer <token>` consistently with other authenticated calls. Use `Accept: application/json`.

---

## Listing: choose the right context

### A) Admin / management — paginated vehicle list

**Endpoint:** `GET /vehicles/all`

**Query parameters (optional):**

| Parameter     | Description |
|---------------|-------------|
| `search`      | Filters plate, account holder names, `account_no`, body type name |
| `sort_by`     | `plate_no` \| `body` \| `created_at` (falls back to `vehicle.created_at`) |
| `sort_order`  | `asc` \| `desc` (default `desc`) |
| `per_page`    | Default `15`; clamped **1–100** |
| `page`        | Default `1`; values less than `1` are treated as `1` |

**Success response** (pagination at root; not the same shape as `sendResponse`):

```json
{
  "success": true,
  "message": "Vehicles retrieved successfully",
  "data": [
    {
      "exempted": 0,
      "account_no": "...",
      "id": 1,
      "status": 1,
      "card_number": "...",
      "first_name": "...",
      "middle_name": "...",
      "surname": "...",
      "body_type_id": 1,
      "rfid_tag_no": "...",
      "plate_no": "...",
      "body": "...",
      "created_at": "..."
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0,
    "from": 0,
    "to": 0
  }
}
```

**Errors:** `success: false` with `message`.

---

### B) Portal — vehicles for the logged-in customer

**Endpoint:** `GET /portal/list-vehicles`

No query parameters; account is inferred from the authenticated user.

**Success response** (standard `sendResponse` envelope):

```json
{
  "success": true,
  "status_code": 1,
  "data": [
    {
      "id": 1,
      "plate_no": "...",
      "created_at": "...",
      "body_type": "...",
      "exempted": "YES"
    }
  ],
  "message": "Vehicles fetched successfully"
}
```

`exempted` is the string `"YES"` or `"NO"`.

---

### C) Optional — vehicles for a specific account

**Endpoint:** `GET /vehicles/account/{accountId}`

`accountId` is the account row primary key.

**Query parameters (optional):**

| Parameter   | Default | Description        |
|-------------|---------|--------------------|
| `per_page`  | `10`    | Page size          |
| `page`      | `1`     | Page number        |
| `search`    | —       | Plate substring    |

**Success response** (`sendResponse`; `data` shape):

```json
{
  "vehicles": [
    {
      "id": 1,
      "plate_no": "...",
      "body_type": {
        "id": 1,
        "name": "...",
        "description": "..."
      },
      "status": 1,
      "exempted": 0,
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 0,
    "from": null,
    "to": null
  }
}
```

---

## View single vehicle (detail)

**Endpoint:** `GET /vehicles/{id}`

`id` is the vehicle primary key.

**Success response** (`sendResponse`): `data` includes fields such as:

- `vehicleID`, `plate_no`, `card_number`, `rfid_tag_no`, `body_type_id`, `account_no`
- `exempted`, `exempt_reason`, `status`, `created_at`, `updated_at`
- Body type: `name`, `body` (id)
- Account: flattened fields (`first_name`, `middle_name`, `surname`, `phone`, `email`, `nida`, `account_balance`, …) and nested `account`
- `price` (amount for body type, if configured)
- `image` — base64 string when available

**Errors:** e.g. vehicle not found → `sendError`.

### Note for edit forms

`PUT /vehicles/update` requires **`account_vehicle_id`**. Confirm whether your detail payload or list row includes that id; if not, coordinate with backend or load it from another endpoint before submitting updates.
`PUT /vehicles/update` requires **`account_vehicle_id` only when you send `account_no`** (to move the vehicle to another account). For plate/body/RFID-only edits, omit both.

---

## Update vehicle

**Endpoint:** `PUT /vehicles/update` (same rules for `PUT /api/collection-management/vehicles/{id}`; there `vehicle_id` is the path `{id}`.)

**Body (JSON):** Send `Content-Type: application/json` (required for PUT if the client does not set it automatically — otherwise Laravel may not read `plate_no` / `body_type_id` from the body). Alternatively use `POST /api/vehicles/update` with the same JSON body.

| Field                | Required | Validation / notes                                      |
|----------------------|----------|---------------------------------------------------------|
| `vehicle_id`         | yes      | Integer; must exist in `vehicle.id`                     |
| `plate_no`           | yes      | Unique in `vehicle` except current `vehicle_id`         |
| `account_no`         | no       | If set, must exist in `account.account_no`; then `account_vehicle_id` is required |
| `body_type_id`       | yes      | Must exist in `body_type.id`                            |
| `account_vehicle_id` | with `account_no` | Required only when `account_no` is present; must exist in `account_vehicle.id` |
| `card_number`        | no       | If set, backend updates card / RFID-related flags       |
| `rfid_tag_no`        | no       | Optional string                                         |

**Success** (`sendResponse`):

- `data.vehicle` — updated vehicle model
- `data.account_vehicle` — updated association model when `account_no` was sent; otherwise `null`
- `message`: `"Vehicle updated successfully"`

**Validation errors:** `sendError` with `data.errors` (Laravel validator structure).

---

## Standard JSON envelopes

Used by `sendResponse` / `sendError` (e.g. portal list, detail, update).

**Success:**

```json
{
  "success": true,
  "status_code": 1,
  "data": {},
  "message": "..."
}
```

**Error:**

```json
{
  "success": false,
  "status_code": 0,
  "message": "...",
  "data": {}
}
```

Some errors may still return HTTP 200; rely on `success` and `message`.

---

## UI checklist

1. **List:** Admin table/cards from `GET /vehicles/all` with search, sort, pagination; portal from `GET /portal/list-vehicles`.
2. **View:** Navigate with vehicle `id` → `GET /vehicles/{id}`; display `image` when present.
3. **Edit:** Submit `PUT /vehicles/update` (or collection-management PUT). Include `account_no` + `account_vehicle_id` only when changing the account link.

---

## Route source (reference)

- `routes/api/vehicle.php` — `vehicles/all`, `vehicles/{id}`, `vehicles/update`, `vehicles/account/{accountId}`
- `routes/api/portal.php` — `portal/list-vehicles`
- API prefix: `api` (see `App\Providers\RouteServiceProvider`)

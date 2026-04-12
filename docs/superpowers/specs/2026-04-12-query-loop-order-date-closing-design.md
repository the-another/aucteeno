# Query Loop Order: Date Closing Redesign

## Summary

Rename the current `ending_soon` sort to `status_ending_soon` and introduce a new `ending_soon` that combines running + upcoming auctions/items by `bidding_ends_at` without grouping by bidding status.

## Current State

The query loop block (`blocks/query-loop`) supports three sort options:

- `ending_soon` (default) — groups by bidding status: running (10) first by `bidding_ends_at ASC`, then upcoming (20) by `bidding_starts_at ASC`, then expired (30) by `bidding_ends_at DESC`
- `newest` — `post_date DESC`
- `lot_number` — items only, by `lot_sort_key ASC`

## Changes

### 1. Rename existing `ending_soon` to `status_ending_soon`

The current sort is a composite of bidding state + date ending. Rename it to reflect that. All existing SQL logic stays identical — only the key name changes.

**Label in editor:** "By Status & Ending"

### 2. New `ending_soon` (takes over the name, remains default)

Combines running (10) and upcoming (20) into a single group ordered by `bidding_ends_at ASC`. Expired (30) follows, ordered by `bidding_ends_at DESC`.

**Label in editor:** "Ending Soon"

### Files to Change

#### `blocks/query-loop/block.json`

Add `status_ending_soon` to the `orderBy` enum. Keep `ending_soon` as default.

```json
"enum": ["ending_soon", "status_ending_soon", "newest", "lot_number"]
```

#### `blocks/query-loop/src/editor.js`

Update SelectControl options:

- "Ending Soon" → `ending_soon`
- "By Status & Ending" → `status_ending_soon`
- "Latest Added" → `newest`
- "Lot Number" → `lot_number` (items only, unchanged)

#### `includes/database/class-database-auctions.php`

**`ending_soon`** (new): Single CASE expression, 2 groups:

```sql
CASE WHEN a.bidding_status IN (10, 20) THEN 1 ELSE 2 END ASC,
CASE WHEN a.bidding_status IN (10, 20) THEN a.bidding_ends_at ELSE -a.bidding_ends_at END ASC,
a.auction_id ASC
```

**`status_ending_soon`**: Exact current `ending_soon` SQL, renamed.

#### `includes/database/class-database-items.php`

**`ending_soon`** (new): 2-part UNION ALL:

- Group 1: status IN (10, 20), `ORDER BY i.bidding_ends_at ASC, i.lot_sort_key ASC, i.item_id ASC`
- Group 2: status = 30, `ORDER BY i.bidding_ends_at DESC, i.item_id DESC`
- Same offset/pagination calculation pattern as current 3-part approach, reduced to 2 groups.

**`status_ending_soon`**: Exact current `ending_soon` UNION ALL (3-part), renamed.

#### `includes/database/class-query-orderer.php`

Same rename + new sort logic mirroring the database classes.

#### `includes/rest-api/class-rest-controller.php`

Accept `status_ending_soon` as a valid `sort` parameter value.

### No Changes Required

- **`blocks/query-loop/render.php`** — passes through attribute value, no sort-specific logic
- **Database schema / indexes** — existing indexes on `(bidding_status, bidding_ends_at, ...)` cover the new query pattern

## Bidding Status Reference

| Code | Slug | Meaning |
|------|------|---------|
| 10 | `running` | Actively bidding |
| 20 | `upcoming` | Not yet started |
| 30 | `expired` | Ended |

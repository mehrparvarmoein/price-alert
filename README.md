# Price Alert System

A price alert service built with Laravel 13, PHP 8.4, PostgreSQL 17, Redis and Laravel Horizon.

The system allows users to create price alerts for a gold price. When the price **crosses** the configured target, the alert is processed and the user receives a notification.

## Features

* Above and below price alerts
* Redis Sorted Sets for fast alert matching
* PostgreSQL as the source of truth
* Atomic alert claiming to prevent duplicate processing
* Transactional Outbox for reliable event publishing
* Redis-backed queues with Laravel Horizon
* Notification idempotency
* Retry and stale-processing recovery
* Redis index rebuild capability
* Docker-based local environment
* Unit, feature and end-to-end tests
* Health check endpoint

---

## Architecture

The system separates the domain logic from infrastructure concerns.

```text
                         ┌──────────────────────┐
                         │  Global Gold Price   │
                         │    API / Mock        │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │     Price Poller     │
                         └──────────┬───────────┘
                                    │
                         ┌──────────▼───────────┐
                         │    Redis Price       │
                         │ Current / Previous   │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │ Redis Sorted Sets    │
                         │                      │
                         │ ABOVE   target price│
                         │ BELOW   target price│
                         └──────────┬───────────┘
                                    │
                              Candidate IDs
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │     PostgreSQL       │
                         │   Atomic Claim       │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │ Transactional Outbox │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │   Outbox Publisher   │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │    Redis Queue       │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │ Laravel Horizon      │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │ Notification Job     │
                         └──────────┬───────────┘
                                    │
                         ┌──────────▼───────────┐
                         │ Notification Delivery│
                         │    Idempotency       │
                         └──────────┬───────────┘
                                    │
                                    ▼
                              Email Provider
```

### Source of Truth

PostgreSQL is the authoritative data store.

Redis is intentionally used as a **rebuildable performance index**, not as the source of truth.

If Redis data is lost, the indexes can be rebuilt from PostgreSQL:

```bash
php artisan alerts:rebuild-index
```

---

## Price Crossing Semantics

An alert is triggered only when the price actually crosses the target.

For an `above` alert:

```text
previous_price < target_price
AND
current_price >= target_price
```

For a `below` alert:

```text
previous_price > target_price
AND
current_price <= target_price
```

For example:

```text
3490 → 3510
target = 3500
direction = above

=> triggered
```

But:

```text
3510 → 3520
target = 3500
direction = above

=> not triggered
```

This prevents an alert created while the price is already above its target from being immediately triggered.

The same crossing semantics apply in the opposite direction for `below` alerts.

---

## Data Model

### `price_alerts`

Stores user-created alerts.

Important fields:

```text
id
user_id
target_price
direction
status
processing_at
triggered_at
created_at
updated_at
```

Possible states:

```text
ACTIVE
   │
   │ claim
   ▼
PROCESSING
   │
   │ notification success
   ▼
TRIGGERED
```

### `outbox_messages`

Stores domain events that must eventually be published to the queue.

The outbox is written in the same database transaction as the alert state transition.

### `notification_deliveries`

Tracks notification delivery and provides an idempotency boundary.

Each alert uses:

```text
price-alert:{alert_id}
```

as its idempotency key.

A unique database constraint prevents multiple delivery records for the same alert.

---

## Redis Alert Index

Two Redis Sorted Sets are used:

```text
price_alerts:above
price_alerts:below
```

The alert ID is the member and the target price is the score.

Example:

```text
price_alerts:above

score     member
3500      101
3510      102
3550      103
3600      104
```

If the price moves from:

```text
3490 → 3550
```

the system can query:

```text
(3490, 3550]
```

and immediately identify relevant candidates.

For downward movement:

```text
3550 → 3490
```

the relevant range is:

```text
[3490, 3550)
```

This avoids scanning every active alert on every price update.

---

## Processing Flow

When a new price arrives:

1. Fetch the current price from the configured provider.
2. Atomically update Redis current/previous price state.
3. Determine the direction of movement.
4. Query the appropriate Redis Sorted Set.
5. Retrieve only candidate alert IDs.
6. Load active candidates from PostgreSQL.
7. Validate crossing semantics.
8. Atomically claim each matching alert.
9. Create an Outbox event in the same transaction.
10. Remove the claimed alert from the Redis index.
11. Publish the Outbox event to the Redis queue.
12. Horizon processes the notification job.
13. The notification is sent using the idempotency key.
14. The delivery is marked as sent.
15. The alert is marked as triggered.

---

## Concurrency

The critical state transition is performed using an atomic conditional update:

```sql
UPDATE price_alerts
SET
    status = 'processing',
    processing_at = NOW()
WHERE
    id = ?
    AND status = 'active';
```

Only the worker that changes one row successfully owns the alert.

If multiple workers attempt to claim the same alert:

```text
Worker A → affected rows = 1 → claimed
Worker B → affected rows = 0 → skipped
```

This avoids relying on application-level locks.

---

## Transactional Outbox

The alert claim and notification event are persisted in one PostgreSQL transaction.

```text
BEGIN
    UPDATE price_alerts
    SET status = 'processing';

    INSERT INTO outbox_messages (...);
COMMIT;
```

If the transaction fails, neither change is persisted.

The Outbox Publisher then claims pending messages and dispatches queue jobs.

The publisher uses:

```text
FOR UPDATE SKIP LOCKED
```

The system intentionally uses **at-least-once publishing**.

If the application crashes after dispatching a queue job but before marking the Outbox message as processed, the event may be published again.

This is expected and handled by downstream idempotency.

---

## Notification Delivery

Notification jobs are retried using:

```text
tries: 3

backoff:
5s
30s
120s
```

The delivery record uses a unique idempotency key:

```text
price-alert:{alert_id}
```

This prevents duplicate delivery records.

Exactly-once delivery of an external email cannot generally be guaranteed by the application alone. If the email provider supports idempotency keys, the same key should be forwarded to the provider.

Therefore the notification pipeline is designed as:

```text
At-least-once processing
+
Idempotent consumer
+
Provider-side idempotency when available
```

---

## Failure Recovery

### Redis Failure

Redis is not the source of truth.

If Redis data is lost:

```bash
php artisan alerts:rebuild-index
```

rebuilds the active alert indexes from PostgreSQL.

### Stale Alert Processing

If an alert remains in `PROCESSING` beyond the configured timeout, the recovery command can detect it.

```bash
php artisan alerts:recover-stale
```


### Queue Failure

If the queue is unavailable, the Outbox message remains unprocessed.

Once the publisher becomes available again, it can publish the message.

---

## API

### Create Price Alert

```http
POST /api/alerts
```

Authentication:

```text
Sanctum
```

Request:

```json
{
    "target_price": "3500",
    "direction": "above"
}
```

Response:

```json
{
    "data": {
        "target_price": "3500",
        "direction": "above",
        "status": "active"
    }
}
```

Supported directions:

```text
above
below
```

---

## Running Locally

Copy the environment file:

```bash
cp .env.example .env
```

Start the containers:

```bash
docker compose up -d --build
```

Install dependencies if required:

```bash
docker compose exec app composer install
```

Generate the application key:

```bash
docker compose exec app php artisan key:generate
```

Run migrations:

```bash
docker compose exec app php artisan migrate
```

The API is available at:

```text
http://localhost:8080
```

Health check:

```text
GET /api/health
```

---

## Running the Queue

Laravel Horizon is used for queue processing.

```bash
docker compose logs -f horizon
```

The Horizon dashboard is available at:

```text
/horizon
```

---

## Scheduler

The scheduler runs:

```text
gold:poll
outbox:publish
alerts:recover-stale
alerts:rebuild-index
```

For local development:

```bash
php artisan schedule:work
```

The Docker Compose setup includes a dedicated scheduler container.

---

## Testing

Run the test suite:

```bash
docker compose exec app php artisan test
```

The test suite covers:

* Price crossing rules
* Above/below boundary conditions
* Redis Sorted Set matching
* Alert claiming
* Duplicate processing
* Outbox publishing
* Notification delivery
* Failure and retry behavior
* Redis index rebuilding
* API validation
* Authentication
* End-to-end price crossing flow

---

## Performance

The naive implementation would scan all active alerts for every price update:

```text
O(N)
```

where `N` is the number of active alerts.

This implementation uses Redis Sorted Sets to narrow the search to alerts whose target lies inside the actual price movement range.

Conceptually:

```text
O(log N + K)
```

where:

* `N` = number of indexed alerts
* `K` = number of alerts whose targets were crossed

This is particularly beneficial when the number of active alerts is large but each price movement crosses only a small subset of targets.

PostgreSQL remains responsible for authoritative state validation and atomic claiming.

---

## Production Considerations

The provided implementation uses polling because the price provider is mocked for the assignment.

For a real production gold price feed, a streaming/WebSocket provider would be preferable when available.

Other production improvements could include:

* Prometheus metrics
* Sentry error tracking
* Provider-specific timeout and circuit breaker
* Multiple Horizon supervisors
* Dedicated queues for price alerts and other workloads
* Redis high availability
* PostgreSQL replication
* Read replicas for non-critical reads
* Shadow Redis index rebuilds followed by atomic key swaps
* Provider-side notification idempotency
* Distributed tracing

These are intentionally not required for the assignment and would add operational complexity without improving the core demonstration.

---

## Design Trade-offs

### PostgreSQL + Redis

PostgreSQL is the source of truth because alert correctness must not depend on Redis availability.

Redis is used as a rebuildable performance index.

### Redis Sorted Set

A Sorted Set was selected because alert matching is fundamentally a range query over target prices.

### Atomic UPDATE instead of application locks

The database is the authority for alert state, so the claim operation is protected at the database level.

### Transactional Outbox

The Outbox pattern is used for notification events because losing a notification event after successfully claiming an alert would be unacceptable.

It is intentionally not used for Redis indexing. Redis is a rebuildable cache/index and does not require the additional complexity of an Outbox.

### At-least-once processing

Exactly-once processing across PostgreSQL, Redis and an external email provider is not realistically guaranteed without distributed transaction support.

The design therefore uses at-least-once processing with idempotency at the consumer/provider boundary.

---

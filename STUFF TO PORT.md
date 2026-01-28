# API Porting Checklist

DIFFICULTY:

- 🟢 Easy
- 🟡 Medium
- 🔴 Hard
- ❓ Unknown

## Maps Endpoints

- [ ] 🔴 GET /maps/submit
    > **Hard**: Discord webhook signature validation + complex lookup logic
- [ ] 🔴 POST /maps/submit
    > **Hard**: File uploads + duplicate checking + Ninja Kiwi API calls + async webhook notifications
- [ ] ❓ POST 🤖 /maps/submit
- [ ] 🔴 DELETE /maps/submit
    > **Hard**: Discord signature validation + message lookup + permission checks + async webhook updates
- [ ] ❓ DELETE 🤖 /maps/submit
- [ ] 🟡 DELETE /maps/submit/{code}/formats/{format_id}
    > **Medium**: Permission checks + soft delete + async webhook notifications
- [ ] 🟡 GET /maps
    > **Medium**: Format-specific queries with conditional filtering logic
- [ ] 🔴 POST /maps
    > **Hard**: File handling + multiple format validation + permission checks + async webhook + logging
- [ ] 🟢 GET /maps/legacy
    > **Easy**: Simple query with is_deleted filter
- [ ] 🟡 GET /maps/leaderboard
    > **Medium**: Complex leaderboard query with CTEs + pagination + format filtering
- [ ] 🟡 GET /maps/retro
    > **Medium**: Grouped query with complex response structure (by game/category)
- [ ] 🟢 GET /maps/{code}/completions/@me
    > **Easy**: Simple filtered query for authenticated user
- [ ] 🔴 POST /maps/{code}/completions/submit
    > **Hard**: File handling + multiple user validation + permission checks + async logging
- [ ] ❓ POST 🤖 /maps/{code}/completions/submit
- [ ] 🟡 GET /maps/{code}/completions
    > **Medium**: Paginated query + format filtering + permission checks
- [ ] 🟡 POST /maps/{code}/completions
    > **Medium**: Permission validation + user completion checking + database insert
- [ ] 🔴 PUT /maps/{code}/completions/transfer
    > **Hard**: Map validation + permission checks + bulk data transfer between maps + async logging
- [ ] 🟢 GET /maps/{code}
    > **Easy**: Single database query by code
- [ ] 🔴 PUT /maps/{code}
    > **Hard**: File uploads + database update + async webhook notifications
- [ ] 🟡 DELETE /maps/{code}
    > **Medium**: Permission checks + soft delete + async logging

## Formats Endpoints

- [x] 🟢 GET /formats
    > **Easy**: Simple query returning all formats ordered by ID ✅
- [ ] 🟢 GET /formats/{id}
    > **Easy**: Single query + edit:config permission check, returns hidden fields
- [ ] 🟡 PUT /formats/{id}
    > **Medium**: Permission check + validation + database update (no async tasks)
- [ ] ❓ GET 🤖 /formats

## Config Endpoints

- [ ] 🟢 GET /config
    > **Easy**: Simple key-value query
- [ ] 🟡 PUT /config/{key}
    > **Medium**: Permission check + validation + update specific config key

## Auth Endpoints

- [ ] 🟢 POST /auth
    > **Easy**: Discord OAuth + user lookup/creation (implemented, untested)

## Server Roles Endpoints

- [ ] 🔴 GET /server-roles
    > **Hard**: Multiple concurrent Discord API calls + complex filtering + semaphore management

## Completions Endpoints

- [ ] 🟡 PUT /completions/{cid}/accept
    > **Medium**: Permission validation + database update + async webhook + logging
- [ ] ❓ PUT 🤖 /completions/{cid}/accept
- [ ] 🟢 GET /completions/{cid}
    > **Easy**: Single database query by completion ID
- [ ] 🟡 PUT /completions/{cid}
    > **Medium**: Permission validation + database update + async logging
- [ ] 🟡 DELETE /completions/{cid}
    > **Medium**: Permission checks + conditional async webhook + logging
- [ ] ❓ DELETE 🤖 /completions/{cid}
- [ ] 🟡 GET /completions/unapproved
    > **Medium**: Permission-filtered query + pagination
- [ ] 🟢 GET /completions/recent
    > **Easy**: Simple recent completions query with limit

## Roles Endpoints

- [ ] 🟢 GET /roles/achievement
    > **Easy**: Simple database query
- [ ] 🟡 PUT /roles/achievement
    > **Medium**: Validation + database update + complex response handling
- [ ] ❓ GET /roles/achievement/updates
- [ ] ❓ POST 🤖 /roles/achievement/updates
- [ ] 🟢 GET /roles
    > **Easy**: Simple database query

## Users Endpoints

- [x] 🟢 PUT /read-rules
    > **Easy**: Simple database update, idempotent ✅
- [ ] ❓ PUT 🤖 /read-rules
- [ ] 🟡 GET /search
    > **Medium**: Text search across multiple entity types (users, maps)
- [ ] 🔴 GET /img/medal-banner/{banner}
    > **Hard**: Image processing with PIL + multiple overlays + dynamic text rendering
- [ ] 🟡 PUT /users/@me
    > **Medium**: Validation + name collision check + external API call + database update
- [ ] 🟡 GET /users/@me/submissions
    > **Medium**: Conditional pagination with multiple query types
- [ ] 🟢 POST /users/{uid}/unban
    > **Easy**: Permission check + database update
- [ ] 🟡 GET /users/{uid}/completions
    > **Medium**: Paginated query with user filtering
- [ ] 🟢 GET /users/{uid}
    > **Easy**: Database query + optional Ninja Kiwi API call
- [ ] ❓ GET 🤖 /users/{uid}
- [ ] 🟡 PUT /users/{uid}
    > **Medium**: Permission check + multiple field updates + validation
- [ ] ❓ PUT 🤖 /users/{uid}
- [ ] 🟡 PATCH /users/{uid}/roles
    > **Medium**: Permission validation + role management + complex response
- [ ] 🟢 POST /users/{uid}/ban
    > **Easy**: Permission check + database update
- [ ] 🟡 POST /users
    > **Medium**: Permission check + user validation + database creation

## Bot Routes (Skipped - Different Middleware)

All routes marked with 🤖 use bot-specific authentication/middleware and should be ported separately after user-facing routes are complete.

## Progress

**Fully Done (✅):**

- GET /formats
- PUT /read-rules

**Partially Done (🟡):**

- POST /auth (implemented, untested)

**Total:** 2.5 / ~58 endpoints

---

## Summary by Difficulty

**Easy (🟢):** 13 routes

- Simple CRUD operations
- Basic database queries
- No async tasks
- Minimal validation

**Medium (🟡):** 26 routes

- Paginated queries
- Permission filtering
- Some async operations
- File handling (simple cases)
- External API calls

**Hard (🔴):** 10 routes

- Complex file uploads
- Discord webhook integrations
- CTEs in GET queries
- Image processing
- Bulk data operations
- Complex permission systems

**Unknown (❓):** 9 routes (all bot routes)

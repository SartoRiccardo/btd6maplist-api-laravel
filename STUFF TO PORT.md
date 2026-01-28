# API Porting Checklist

DIFFICULTY:

- 🟢 Easy
- 🟡 Medium
- 🔴 Hard
- ❓ Unknown

## Maps Endpoints

- [ ] 🔴 GET /maps/submit
- [ ] 🔴 POST /maps/submit
- [ ] ❓ POST 🤖 /maps/submit
- [ ] ❓ DELETE /maps/submit
- [ ] ❓ DELETE 🤖 /maps/submit
- [ ] ❓ DELETE /maps/submit/{code}/formats/{format_id}
- [ ] 🟢 GET /maps
- [ ] 🔴 POST /maps (file uploads)
- [ ] ❓ GET /maps/legacy
- [ ] ❓ GET /maps/leaderboard
- [ ] ❓ GET /maps/retro
- [ ] ❓ GET /maps/{code}/completions/@me
- [ ] ❓ POST /maps/{code}/completions/submit
- [ ] ❓ POST 🤖 /maps/{code}/completions/submit
- [ ] ❓ GET /maps/{code}/completions
- [ ] ❓ POST /maps/{code}/completions
- [ ] ❓ PUT /maps/{code}/completions/transfer
- [ ] 🟢 GET /maps/{code}
- [ ] 🔴 PUT /maps/{code} (file uploads)
- [ ] ❓ DELETE /maps/{code}

## Formats Endpoints

- [x] 🟢 GET /formats
- [ ] 🟡 GET /formats/{id}
- [ ] 🟡 PUT /formats/{id}
- [ ] ❓ GET 🤖 /formats

## Config Endpoints

- [ ] 🟢 GET /config
- [ ] 🟡 PUT /config/{key}

## Auth Endpoints

- [ ] 🟡 POST /auth

## Server Roles Endpoints

- [ ] ❓ GET /server-roles

## Completions Endpoints

- [ ] ❓ PUT /completions/{cid}/accept
- [ ] ❓ PUT 🤖 /completions/{cid}/accept
- [ ] ❓ GET /completions/{cid}
- [ ] ❓ PUT /completions/{cid}
- [ ] ❓ DELETE /completions/{cid}
- [ ] ❓ DELETE 🤖 /completions/{cid}
- [ ] ❓ GET /completions/unapproved
- [ ] ❓ GET /completions/recent

## Roles Endpoints

- [ ] ❓ GET /roles/achievement
- [ ] ❓ PUT /roles/achievement
- [ ] ❓ GET /roles/achievement/updates
- [ ] ❓ POST 🤖 /roles/achievement/updates
- [ ] ❓ GET /roles

## Users Endpoints

- [x] 🟢 PUT /read-rules
- [ ] ❓ PUT 🤖 /read-rules
- [ ] ❓ GET /search
- [ ] ❓ GET /img/medal-banner/{banner}
- [ ] ❓ PUT /users/@me
- [ ] ❓ GET /users/@me/submissions
- [ ] ❓ POST /users/{uid}/unban
- [ ] ❓ GET /users/{uid}/completions
- [ ] ❓ GET /users/{uid}
- [ ] ❓ GET 🤖 /users/{uid}
- [ ] ❓ PUT /users/{uid}
- [ ] ❓ PUT 🤖 /users/{uid}
- [ ] ❓ PATCH /users/{uid}/roles
- [ ] ❓ POST /users/{uid}/ban
- [ ] ❓ POST /users

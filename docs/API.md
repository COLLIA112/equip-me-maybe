# Equip Me Maybe Checkpoint API

Use `http://127.0.0.1:8000` with `php artisan serve`. Import `docs/Equip-Me-Maybe.postman_collection.json` into Postman. The collection stores the token after a successful login. Run **List equipment** first and copy an item's `data[].code` into the `equipment_code` variable. For an authorized checkout, use a code from an in-window item in **Active checkouts**.

## Requests

| Postman request | Method and URL | Headers | Body | Expected success |
|---|---|---|---|---|
| Auditor login | `POST {{base_url}}/api/login` | `Accept: application/json`, `Content-Type: application/json` | Raw JSON below | `200` with `token` and `abilities` |
| List equipment | `GET {{base_url}}/api/equipment` | `Accept: application/json`, `Authorization: Bearer {{token}}` | None | `200` with `data`, `links`, and `meta` |
| Verify equipment authorization | `GET {{base_url}}/api/equipment/{{equipment_code}}/authorization` | `Accept: application/json`, `Authorization: Bearer {{token}}` | None | `200` with a single `data` object |
| Active checkouts | `GET {{base_url}}/api/reservations/active` | `Accept: application/json`, `Authorization: Bearer {{token}}` | None | `200` with a `data` array |

```json
{
  "email": "auditor1@equip.test",
  "password": "password",
  "device_name": "Postman"
}
```

`device_name` is optional and can contain up to 100 characters. Successful login returns the plaintext token once and `"abilities": ["checkpoint:verify"]`. Use the demo password only on a local seeded database.

Equipment listing accepts optional `lab_id`, `status`, and `page` query parameters. Equipment status values are `available`, `reserved`, and `under_repair`. Results are ordered by name, 20 per page. Each entry includes the equipment ID, code, name, serial number, status, status label, photo URL, lab, and tags.

## Authorization response

```json
{
  "data": {
    "equipment": {
      "id": 42,
      "code": "EQ-ABCD1234",
      "name": "Canon EOS R6",
      "status": "reserved"
    },
    "authorized": true,
    "borrower": "Example Borrower",
    "returns_at": "2026-11-20T13:00:00+00:00"
  }
}
```

`authorized` is true when the item is checked out and the current time falls within its reservation window. An existing item with no checkout returns `200`, `authorized: false`, and null borrower and return time. An overdue checkout can still show the borrower and due time, with `authorized: false`.

Active checkouts are ordered by due time and include overdue checkouts until the manager records the return. Each row contains `id`, `status`, `starts_at`, `ends_at`, `handed_over_at`, `returned_at`, `borrower`, and an `equipment` object. Dates use ISO 8601.

## Failure cases

| Case | Exact change in Postman | Expected |
|---|---|---|
| Missing token | Set Auth to **No Auth** on any protected GET | `401 Unauthorized`, JSON |
| Invalid token | Set Bearer Token to `invalid-token` on any protected GET | `401 Unauthorized`, JSON |
| Wrong ability | Send a valid local test token created with only a different ability | `403 Forbidden`, JSON |
| Unknown equipment | With the auditor token, GET `{{base_url}}/api/equipment/EQ-DOESNOTEXIST/authorization` | `404` with `{"message":"Equipment not found."}` |
| Invalid login | Try the login JSON with a wrong password or use `student1@equip.test` | `422 Unprocessable Content`, with `errors.email` for rejected credentials |

The login endpoint is public. The existing credential validation contract uses `422` for unsuccessful login. The protected GET routes return `401` for missing or invalid credentials and `403` for a valid token missing the ability.

To reproduce the `403` inside Postman using a dedicated local demo database, open `php artisan tinker` and issue a temporary token:

```php
$auditor = App\Models\User::where('email', 'auditor1@equip.test')->firstOrFail();
$limited = $auditor->createToken('postman-limited-demo', ['checkpoint:other'], now()->addMinutes(10));
$limited->plainTextToken;
```

Put that value in the collection variable `limited_token`, run **Active checkouts — wrong ability**, and remove the variable value when done. In the same Tinker session, revoke the token with `$limited->accessToken->delete();`. The existing `DELETE /api/logout` endpoint revokes the current token when called with the normal checkpoint token and returns `200` with `{"message":"Token revoked."}`.

PHPUnit

```powershell
php artisan test --filter=CheckpointApiTest
```

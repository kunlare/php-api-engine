# Admin Panel UI

The admin panel is a single-page application (SPA) served at `/admin`. It uses Bootstrap 5.3, Bootstrap Icons, and vanilla JavaScript with `fetch()` API calls.

No build step is required — all assets are loaded via CDN.

## Access

```
http://your-domain/admin
```

Login with the credentials created during `php vendor/bin/setup.php`.

## Roles & Permissions

There are three roles: **admin**, **developer**, and **user**.

### Sidebar Visibility

| Page | Admin | Developer | User |
|------|-------|-----------|------|
| Dashboard | Yes | Yes | Yes |
| Tables | Yes | Yes | Yes |
| Users | Yes | — | — |
| API Keys | Yes | Yes | — |
| Queues | Yes | Yes | — |
| API Explorer | Yes | Yes | — |
| Profile | Yes | Yes | Yes |

### Dashboard

| Element | Admin | Developer | User |
|---------|-------|-----------|------|
| Table count | Yes | Yes | Yes |
| User count | Yes (number) | — (dash) | — (dash) |
| Quick action: Create Table | Yes | — | — |
| Quick action: Browse Tables | — | Yes | Yes |
| Quick action: Manage Users | Yes | — | — |
| Quick action: API Keys | Yes | Yes | — |
| Quick action: Profile | Yes | Yes | Yes |
| Tables mini-list | Yes | Yes | Yes |

### Tables

| Action | Admin | Developer | User |
|--------|-------|-----------|------|
| See table list | Yes | Yes | Yes |
| Create new table | Yes | — | — |
| View table data (read) | Yes | Yes (read-only badge) | Yes (read-only badge) |
| Search/filter data | Yes | Yes | Yes |
| Create record | Yes | — | — |
| Edit record | Yes | — | — |
| Delete record | Yes | — | — |
| View table structure | Yes | — | — |
| Add/modify/drop column | Yes | — | — |
| Drop table | Yes | — | — |

**Note:** The system tables `users`, `api_keys`, `queues`, and `queue_messages` are hidden from the table list for all roles. They are managed exclusively through their dedicated sidebar pages.

### Users Management

Only **admin** can access the Users page.

| Action | Admin |
|--------|-------|
| List all users | Yes |
| Create user (with plain-text password, hashed by backend) | Yes |
| Edit user (username, email, password, role, active) | Yes |
| Delete user (cannot delete self) | Yes |

### API Keys

| Action | Admin | Developer |
|--------|-------|-----------|
| View keys | All keys (all users, with owner column) | Own keys only |
| Generate new key | Yes | Yes |
| Revoke key | Any key | Own keys only |

API keys are stored hashed (SHA-256) in the database. The plain-text key is shown **only once** at generation time.

### Queues

Available to **admin** and **developer**.

| Action | Admin | Developer |
|--------|-------|-----------|
| List queues with stats | Yes | Yes |
| View queue detail (stats, messages) | Yes | Yes |
| Create queue | Yes | — |
| Edit queue settings | Yes | — |
| Delete queue | Yes | — |
| Publish message | Yes | Yes |
| View messages (all statuses) | Yes | Yes |
| Cancel pending message | Yes | Yes |
| Retry dead/failed message | Yes | — |

Queue detail page shows:
- Stat cards (pending, processing, completed, failed, dead counts)
- Delivery URL for push queues
- Status filter tabs
- Paginated message list with status badges
- Action buttons per message (cancel, retry)

See [QUEUES.md](QUEUES.md) for full queue system documentation.

### API Explorer

Available to **admin** and **developer**. A Swagger-style interactive API reference and tester.

**Features:**
- **Auth card** at the top: paste an API key to authenticate requests externally, or use the current JWT session as fallback
- **Table selector**: changes example paths for all endpoints to reference the selected table
- **Base URL** displayed read-only for easy copy
- **Endpoint catalog** grouped by category:
  - Data — List & Search (GET list, GET by ID, GET filter)
  - Data — Create, Update, Delete (POST, PATCH, DELETE)
  - Schema — Read (list tables, get structure)
  - Schema — Write / Admin only (create table, add/modify/drop columns, drop table)
  - Queues — Management (list, create, get, update, delete)
  - Queues — Messages (publish, list, consume, ack, nack, retry, cancel)
  - Authentication (login, refresh, profile)
- Each endpoint is an **expandable accordion** showing:
  - HTTP method badge (color-coded: blue GET, green POST, orange PATCH, red DELETE)
  - Path pattern and description
  - Parameters table (name, location, required, description)
  - Editable request path and optional JSON body
  - **Send Request** button that fires a live API call
  - Response area showing HTTP status, timing, and formatted JSON response

### Profile

All roles can access the Profile page.

| Action | Admin | Developer | User |
|--------|-------|-----------|------|
| Edit username | Yes | Yes | Yes |
| Edit email | Yes | Yes | Yes |
| Change password | Yes (requires current password) | Yes (requires current password) | Yes (requires current password) |

## API Endpoints Used by the UI

| UI Page | Endpoint | Method | Access |
|---------|----------|--------|--------|
| Login | `/api/v1/auth/login` | POST | Public |
| Dashboard | `/api/v1/schema/tables` | GET | All authenticated |
| Dashboard (admin) | `/api/v1/users?per_page=1` | GET | Admin |
| Tables list | `/api/v1/schema/tables` | GET | All authenticated |
| Create table | `/api/v1/schema/tables` | POST | Admin |
| Table structure | `/api/v1/schema/tables/{table}` | GET | All authenticated |
| Add column | `/api/v1/schema/tables/{table}/columns` | POST | Admin |
| Modify column | `/api/v1/schema/tables/{table}/columns/{col}` | PATCH | Admin |
| Drop column | `/api/v1/schema/tables/{table}/columns/{col}` | DELETE | Admin |
| Drop table | `/api/v1/schema/tables/{table}` | DELETE | Admin |
| Table data | `/api/v1/{table}?page=N&per_page=N` | GET | All authenticated |
| Search/filter | `/api/v1/{table}/filter?col[like]=%q%` | GET | All authenticated |
| Create record | `/api/v1/{table}` | POST | Admin |
| Update record | `/api/v1/{table}/{id}` | PATCH | Admin |
| Delete record | `/api/v1/{table}/{id}` | DELETE | Admin |
| Users list | `/api/v1/users` | GET | Admin |
| Create user | `/api/v1/users` | POST | Admin |
| Update user | `/api/v1/users/{id}` | PATCH | Admin |
| Delete user | `/api/v1/users/{id}` | DELETE | Admin |
| API Keys (own) | `/api/v1/auth/apikeys` | GET | All authenticated |
| API Keys (all) | `/api/v1/auth/apikeys/all` | GET | Admin |
| Generate key | `/api/v1/auth/apikey` | POST | All authenticated |
| Revoke key | `/api/v1/auth/apikey/{id}` | DELETE | Owner or Admin |
| Get profile | `/api/v1/auth/profile` | GET | All authenticated |
| Update profile | `/api/v1/auth/profile` | PATCH | All authenticated |
| List queues | `/api/v1/queues` | GET | All authenticated |
| Create queue | `/api/v1/queues` | POST | Admin |
| Get queue | `/api/v1/queues/{queue}` | GET | All authenticated |
| Update queue | `/api/v1/queues/{queue}` | PATCH | Admin |
| Delete queue | `/api/v1/queues/{queue}` | DELETE | Admin |
| Publish message | `/api/v1/queues/{queue}/messages` | POST | All authenticated |
| List messages | `/api/v1/queues/{queue}/messages` | GET | All authenticated |
| Get message | `/api/v1/queues/{queue}/messages/{id}` | GET | All authenticated |
| Cancel message | `/api/v1/queues/{queue}/messages/{id}` | DELETE | All authenticated |
| Consume (pull) | `/api/v1/queues/{queue}/consume` | GET | All authenticated |
| Ack message | `/api/v1/queues/{queue}/messages/{id}/ack` | POST | All authenticated |
| Nack message | `/api/v1/queues/{queue}/messages/{id}/nack` | POST | All authenticated |
| Retry message | `/api/v1/queues/{queue}/messages/{id}/retry` | POST | Admin |

## Theme

The UI supports light and dark themes, toggled via the moon/sun icon in the sidebar footer. The preference is saved in `localStorage`.

## Technical Details

- The entire admin panel is rendered by a single PHP class: `Kunlare\PhpCrudApi\Ui\AdminPanel`
- HTML, CSS, and JS are all inline — no external files to deploy
- Authentication uses JWT tokens stored in `localStorage`
- Token refresh is automatic when a 401 is received
- The SPA uses hash-based routing (`#dashboard`, `#tables`, `#table/{name}`, etc.)

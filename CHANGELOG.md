# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-02-15

### Added
- **Message queue system** with three delivery modes:
  - Inbound handler: internal PHP job processing via cron
  - Outbound pull: external consumers poll for messages via REST API
  - Outbound push: webhook delivery with HMAC-SHA256 signing
- Queue management API: create, update, delete, list queues
- Message operations: publish, consume, ack, nack, retry, cancel
- Message state machine: pending → processing → completed/failed/dead
- Exponential backoff retry with configurable max attempts
- Dead letter queue for permanently failed messages
- Queue worker CLI (`bin/queue-worker.php`) for cron-based processing
- `QueueHandlerInterface` for registering custom PHP handlers
- Visibility timeout for pull queues (prevents double-processing)
- `FOR UPDATE SKIP LOCKED` for safe concurrent worker processing
- Queue Explorer page in admin panel (admin/developer)
- API Explorer page in admin panel — Swagger-style interactive endpoint tester
- Sortable table columns (click to sort ASC/DESC)
- Per-page selector (10/20/50/100) with localStorage persistence
- Previous/Next pagination with smart page range and ellipsis
- Profile page for all roles (edit username, email, change password)
- Role-based API key management (admin sees all, developer sees own)
- Schema read endpoints accessible to all authenticated users

### Changed
- Admin panel sidebar now role-aware with `data-role` attributes
- System tables (users, api_keys, queues, queue_messages) hidden from table listings
- API key auth now works alongside JWT (X-API-Key header checked first)
- Auth middleware supports multi-strategy authentication

### Fixed
- Empty "New Record" modal when table has no data (now fetches schema columns)
- Developer/user roles blocked from viewing tables (schema read was admin-only)
- API keys list not refreshing after generating a new key

## [1.1.0] - 2024-02-07

### Added
- Web-based admin panel at `/admin` with Bootstrap 5.3 SPA
  - Dashboard with database overview and quick actions
  - Table management: create, view structure, drop tables
  - Column management: add, modify, drop columns
  - Data browsing with pagination, search/filter, inline CRUD
  - User management with role editing
  - API key generation and revocation
  - Dark/light theme toggle
- Schema management API endpoints under `/api/v1/schema/tables`
  - Create/drop tables via API
  - Add/modify/drop columns via API
  - List tables and get table structure via API
  - System table protection (users, api_keys cannot be dropped)

## [1.0.0] - 2024-02-07

### Added
- Initial release
- RESTful CRUD operations for any MySQL/MariaDB table
- Multi-mode authentication: Basic Auth, API Key, JWT
- Schema builder for creating/altering/dropping tables
- Fluent query builder with filter support
- User management with role-based access control (admin, user, developer)
- API key generation and management
- JWT token generation with refresh token support
- CORS middleware with configurable origins
- Input validation with extensible rules
- CLI setup tool for initial configuration
- Standardized JSON response format with pagination
- Comprehensive documentation and examples

# Changelog

All notable changes to `byrcsc/laravel-payrex` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-07-26

Corrections found by exercising the package against a live PayRex test-mode
account.

### Removed

- `billingStatementLineItems()->retrieve()` - PayRex serves no GET route for a
  line item. Read them from the parent statement's `lineItems`.
- `PaymentStatus::Pending`, never documented by PayRex. An unmodelled status
  decodes to `null` with the literal kept on `$raw`.

### Changed

- `livemode` is `?bool` on every DTO. Embedded objects omit it, and the old
  `bool` default reported `false` as though PayRex had said so.
- `PaymentIntent::$amount` and `CheckoutSession::$amount` are `?int` for the
  same reason. A checkout session has no `amount` at all; its total lives on
  `line_items`, so the old default made `$session->amount` always `0`.

### Fixed

- 404s are now split by error code (`route_not_found` vs `resource_not_found`)
  rather than by whether a body is present. Both carry one, so
  `RouteNotFoundException` was previously unreachable.
- `RouteNotFoundException` keeps PayRex's own error detail, which names the
  offending URL.
- `BillingStatement::from()` read a `url` key PayRex does not send, instead of
  `billing_statement_url`.
- `Customer::from()` read a `billing_details` key PayRex does not send, instead
  of `billing`.

### Added

- `Payment::$consolidatedNetAmount`, `Payment::$consolidatedStatus`,
  `PaymentIntent::$merchantId`, `CheckoutSession::$customerId` and
  `CustomerSession::$customerId`: all sent by PayRex, none previously read.
- `Payload::nullableBool()`.
- `WebhookSignature::DEFAULT_TOLERANCE`, the 300-second webhook freshness
  window, previously repeated as a literal in the config and every call site.
  The value is unchanged.
- `BillingStatements::finalize()` documents that PayRex drops `due_at` at
  creation and requires it at finalize: create → line items → `update(dueAt:)`
  → `finalize()`.

## [0.1.0] - 2026-07-25

Initial release.

- `PayrexClient` + `Payrex` facade covering the documented PayRex API surface:
  payment intents, checkout sessions, setup intents, customers, customer
  sessions, payments, refunds, payouts, billing statements and line items,
  and webhook endpoint CRUD.
- Readonly DTOs with the raw payload retained on `$raw`; unknown enum values
  decode to `null` instead of throwing.
- Typed exception hierarchy under `PayrexException`.
- Webhook receiving: signed route, constant-time verification, typed Laravel
  events per type plus a generic fallback, and `parseEvent()` for custom routes.
- `autoPaging()` generators and Laravel `CursorPaginator` support on
  list-capable resources.
- `HasPayrexCustomer` Eloquent trait with publishable migration.
- Artisan commands: `payrex:webhook-test`, `-create`, `-list`, `-update`,
  `-delete`, `-toggle`.

[Unreleased]: https://github.com/byrcsc/laravel-payrex/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/byrcsc/laravel-payrex/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/byrcsc/laravel-payrex/releases/tag/v0.1.0

# Changelog

All notable changes to `byrcsc/laravel-payrex` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - Unreleased

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

[Unreleased]: https://github.com/byrcsc/laravel-payrex/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/byrcsc/laravel-payrex/releases/tag/v0.1.0

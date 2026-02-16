# Changelog

All notable changes to `laravel-request-forwarder` will be documented in this file.

## 2.0.0 - 2026-02-16

### Added

- Strict runtime validation for webhook group shape and targets.
- Provider validation (`class_exists` + `ProviderInterface`) before dispatch.
- Extended edge-case test coverage for queue, providers, middleware, facade, and config.
- Upgrade guide for `v1.x -> v2.0` in `README.md`.
- Maintainer section update including Emir Karşıyakalı.

### Changed

- Improved queue dispatch behavior when `queue_name` is empty.
- Runtime config resolution hardened to reduce stale-config risks.
- Provider timeout, method, URL, and header validation improved.
- `WebhookFailed` event now carries `\Throwable` instead of `\Exception`.

### Compatibility

- Laravel: `10.x`, `11.x`, `12.x`
- PHP: `8.2+`

## 1.1.0

- Added custom queue config.

## 1.0.2 - 2024-03-14

- Laravel 11 compatibility.

## 1.0.0 - 2024-02-23

- Initial release.

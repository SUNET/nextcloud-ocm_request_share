<!--
SPDX-FileCopyrightText: 2026 SUNET <kano@sunet.se>
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# nextcloud-ocm_request_share

Spec-compliant OCM Request-for-a-Share for Nextcloud.

Two independent features in one app:

1. **OCM Request-for-a-Share** — implements `POST /ocm/request-share` as
   defined in the cs3org OCM specification, plus the outgoing composer,
   pending-request inbox, and accept/decline flow with optional Share
   Creation Notification.

2. **Public dataset registry** — a per-server registry of shareable resources,
   exposed at `/apps/ocm_request_share/datasets[.json|.jws]`. JWS is signed
   with the server's existing OCM signing key (served at `/.well-known/jwks.json`
   by core). This registry is **not** advertised via OCM discovery — peers
   reach it by path convention or out-of-band.

## Requirements

- Nextcloud 34 (depends on the dual-stack HTTP-Sig, OCM bearer-token, and
  contacts-app extraction PRs landing first)
- `cloud_federation_api` and `files_sharing` enabled

## Status

Early development. See `plan.md` and the `Tasks` view.

## License

AGPL-3.0-or-later. See `LICENSE`.

# Current task

> **Completed** in Geo Core **v1.8.97** + Geo AI **v0.4.131** (2026-06-29).

## Problem

Create rule showed ready UI but execute still reported 1 unresolved field (Google Ads mapping).

## Expected

After applying Standard Google Ads UTM tracking, execute payload and backend validation agree; Create rule succeeds or names the exact unresolved field.

## Acceptance

- [x] Resolver syncs into proposal payload (`applyResolutionsToProposalCards`)
- [x] `collectCardResolutions` uses canonical field lookup
- [x] Execute preflight + named blocked message
- [x] Backend returns `unresolved_details` with `google_ads_mapping`
- [ ] Manual browser pass on Local

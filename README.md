# Scoped Agent Credentials

WordPress Application Passwords carry every capability the issuing user has, never expire, and can't be limited to a named ability or post type. This plugin adds that scoping layer on top of core's Application Passwords.

A credential can be limited to:

- specific abilities (via the [Abilities API](https://developer.wordpress.org/apis/abilities-api/))
- specific post types
- an expiry date
- revocation at any time, effective immediately

## Status

Core enforcement is implemented: expiry/revoke at auth time, ability and post-type
scope at dispatch time. No admin UI yet; credentials are issued and managed through
WP-CLI. Architecture decisions are tracked in [`decisions/`](decisions/).

## Requirements

- WordPress 6.9+ (Abilities API)
- PHP 7.4+

## Usage

```
wp hlb-sap issue 12 --name="Content agent" --abilities=my-plugin/export-users --post-types=post
wp hlb-sap issue 12 --name="Read-only reporting" --post-types=post,page --expires="+30 days"
wp hlb-sap list-credentials
wp hlb-sap revoke <uuid>
wp hlb-sap revoke <uuid> --delete
```

Omit `--abilities` or `--post-types` entirely to leave that dimension unrestricted;
pass an empty value to allow none. A credential with neither flag set behaves like
an ordinary Application Password with an expiry.

## Development

```
composer install
composer run lint
```

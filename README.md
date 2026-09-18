# Scoped Agent Credentials

WordPress Application Passwords carry every capability the issuing user has, never expire, and can't be limited to a named ability or post type. This plugin adds that scoping layer on top of core's Application Passwords.

A credential can be limited to:

- specific abilities (via the [Abilities API](https://developer.wordpress.org/apis/abilities-api/))
- specific post types
- an expiry date
- revocation at any time, effective immediately

## Status

Early scaffold. Architecture decisions are tracked in [`decisions/`](decisions/).

## Requirements

- WordPress 6.9+ (Abilities API)
- PHP 7.4+

## Development

```
composer install
composer run lint
```

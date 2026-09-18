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

## Scope boundaries

Post-type scope restricts REST routes for post-type content only
(`/wp/v2/posts`, `/wp/v2/pages`, and any custom post type's route). It does
**not** restrict other REST routes - `/wp/v2/users`, `/wp/v2/media`,
`/wp/v2/settings`, `/wp/v2/plugins`, and so on are governed only by the
credential's own WordPress capabilities, same as an unscoped Application
Password. Ability scope only restricts calls through the Abilities API's
`run` endpoint.

This plugin adds two extra dimensions on top of capabilities; it doesn't
replace them. For a credential that should touch nothing outside a narrow
set of abilities and post types, issue it to a low-privilege user as well -
capabilities are still the floor.

## Development

```
composer install
composer run lint
```

## Testing

End-to-end tests boot a real WordPress instance via
[WordPress Playground](https://wordpress.github.io/wordpress-playground/) and drive
every enforcement path with actual HTTP requests: auth-time revoke/expiry/XML-RPC
gating, and dispatch-time post-type and ability scope.

```
npm install
npx playwright install --with-deps chromium
npm run test:e2e
```

`npm run playground:start` boots the same environment for manual poking, at
whatever URL it prints.

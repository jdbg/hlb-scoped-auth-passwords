# Scope Application Passwords by ability and post type, enforced in two phases

Date: 2026-09-18

## Status

Accepted

## Context

WordPress Application Passwords authenticate with the full capability set of
the issuing user, never expire, and cannot be limited to a named operation
or content type. An agent credential should only be able to do what it was
issued for.

Three plugins already address parts of this:

- **Mandate App Security** subtracts capabilities from a password's
  allowlist (`user_has_cap` filtering) and adds an optional expiry date,
  swept by daily cron.
- **Agent Abilities for MCP** builds an off-by-default ability allowlist on
  top of core's Abilities API, but binds it to a dedicated low-privilege
  user's role rather than to the credential itself.
- **WSP MCP** enforces capability per tool, again at the user/role level.

None of them scope the credential to a named post type, and none bind
ability scope, post-type scope, expiry, and revoke to the token itself
rather than to a role provisioned around it.

Two facts from core, verified against `wp-includes/user.php` and
`wp-includes/class-wp-application-passwords.php`, constrain the design:

- `wp_authenticate_application_password()` fires the
  `wp_authenticate_application_password_errors` filter with
  `$error, $user, $item, $password` before authentication completes, where
  `$item` includes the password's `uuid`. Returning a `WP_Error` here
  rejects the request before a session is established.
- `WP_Application_Passwords::create_new_application_password()` persists
  only a fixed set of fields (`uuid`, `app_id`, `name`, `password`,
  `created`, `last_used`, `last_ip`). It does not accept arbitrary extra
  fields, so scope cannot be stored on the core record.
- `wp_authenticate_application_password()` only runs when `REST_REQUEST` or
  `XMLRPC_REQUEST` is true (filterable via
  `application_password_is_api_request`). XML-RPC is therefore in scope for
  enforcement by default, not just REST.

Core capabilities are largely not granular per post type (`edit_posts`
covers every post type unless a CPT registered its own
`capability_type`). Capability subtraction alone, Mandate's approach,
cannot express "this token may touch `post` but not `page`".

## Decision

Store scope in a companion table keyed by the application password's
`uuid`, not on the core Application Passwords record and not as a role.
Each row holds the allowed ability names, allowed post types, an optional
`expires_at`, and a `revoked` flag.

Enforce scope in two phases:

1. **Auth time.** On `wp_authenticate_application_password_errors`, look up
   the authenticating credential's `uuid` in the companion table. Reject
   with `WP_Error` if revoked or past `expires_at`. This is the only check
   for XML-RPC requests, which are denied outright for scoped credentials
   unless a token explicitly allows XML-RPC, since XML-RPC has none of the
   REST layer's route or post-type granularity to check against in phase 2.
2. **Dispatch time.** On `rest_request_before_callbacks`, resolve the
   route's post type (where applicable) and check it against the
   credential's allowed post types. Wrap the Abilities API's
   `permission_callback` for any ability invocation so it also checks the
   credential's allowed ability list. Both checks are positive-list
   membership tests against the credential's declared scope, run in
   addition to whatever capability check the endpoint or ability already
   performs, not a replacement for it.

Revocation take effect on the next request, since both checks read the
companion table live. No propagation delay, no reliance on a cron sweep for
enforcement (a daily sweep still runs for housekeeping, to clear dead rows
from the credentials list UI).

Rejected alternatives:

- **Capability subtraction only** (Mandate's model). Keeps scope
  expressible only in terms of existing WordPress capabilities, which
  cannot represent per-post-type or per-ability limits. Rejected because it
  cannot meet the stated requirement.
- **OAuth 2.1.** Gives scopes and token expiry natively, but requires
  standing up an authorization server and consent flow that Application
  Passwords infrastructure, already in core since WP 5.6, makes
  unnecessary for this use case. Rejected as disproportionate setup for the
  problem.
- **Dedicated low-privilege user plus plain Application Password** (Agent
  Abilities for MCP's model). Scope lives in the user's role, so every
  credential issued to that user shares the same scope, and per-ability or
  per-post-type limits still cannot be expressed. Rejected because scope
  needs to live on the credential, not the account.

## Consequences

- Scope enforcement requires two separate hook points instead of one;
  auth-time rejection alone is not sufficient for post-type or ability
  granularity.
- The companion table is the single source of truth for scope and must be
  cleaned up when a credential is deleted through core's own Application
  Passwords UI (deleting the WP record does not remove our row
  automatically), or the table accumulates orphaned rows referencing
  a `uuid` that no longer authenticates.
- Scoped credentials cannot use XML-RPC by default. Any client that needs
  XML-RPC access must be issued a credential with that explicitly allowed,
  a follow-on decision on how that opt-in is exposed in the admin UI.
- Ability-scoped enforcement is only as complete as Abilities API adoption
  across the abilities a credential is granted. An ability invoked through
  a path that bypasses `permission_callback` wrapping (a plugin calling its
  own execute callback directly, outside the registry) is not covered, and
  needs a documented boundary in the plugin's own README.
- Introspection (a credential reading its own scope back) becomes possible
  almost for free, since the companion table already holds everything
  needed. Worth registering as its own ability once the base enforcement
  ships.

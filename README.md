# DravenCMS API

A modular, read-only REST API for DravenCMS, built on Contributte Apitte.
The package handles bearer authentication, client scopes, JSON errors, OpenAPI
documentation, and per-client rate limiting. Feature packages own their
controllers, queries, response fields, and record visibility rules.

## Installation

Requires PHP 8.2 or newer and a DravenCMS installation using Doctrine attribute
mapping. The admin interface is enabled when `dravencms/admin` is configured.

Install the package with Composer after the repository is registered on Packagist:

```sh
composer require 'dravencms/api:^0.1' \
    'contributte/psr7-http-message:dev-master#423f75340a37bdfc15e71b768fb4d5a920f8faed as 0.11.99'
```

The bridge override currently needs to be declared in the consuming application's
root `composer.json`; Composer does not inherit inline aliases from dependencies.
It provides Guzzle PSR-7 3 support required by current Guzzle releases. It is not
a PHP 8.5 workaround.

After installation, run DravenCMS Packager to copy `dravencms.config.neon` into
the application, and include `dravencms/api.neon` in the package configuration.
Register its route factory before generic frontend routes. Rebuild the Nette DI
cache after configuration changes.

Apitte 0.14's stable bridge dependencies currently target Guzzle PSR-7 2.
The upstream bridge commit above supports PSR-7 3, and the alias satisfies
Apitte's bridge constraint. Composer security checks remain enabled. Replace
the pin with a stable dependency when the bridge releases this support.

For local package development, a consuming application can use a Composer path
repository pointing to this checkout instead of the published release.

## Database Setup

Preview the SQL for missing API tables:

```sh
php bin/console api:setup
```

Create only the missing `apiClient` and `apiToken` tables:

```sh
php bin/console api:setup --apply
```

The command leaves existing tables and records untouched. It creates the initial
API schema; later schema changes should be handled through application migrations.
It does not run fixtures or update the rest of the database schema.

For the optional admin interface, add the management permission (the menu is supplied automatically by its provider):

```sh
php bin/console api:admin:install
```

This command reuses existing records on subsequent runs. It grants
`api:clientsEdit` to the `Administrator` group; use `--group=NAME` for a different
group. API clients are managed at `/admin/api.client`, using the existing admin
login and ACL system. The key action in each client row opens that client's
token list at `/admin/api.token?clientId=ID`. Token creation and revocation have
separate form pages; the trash action opens a revocation confirmation.
Scope grants and token creation/revocation are protected by the management
permission and CSRF protection. Revoked tokens remain in the list as history.

## Clients and Tokens

List the scopes exposed by installed controllers and create an integration:

Resource scopes and URLs below assume the example article controller is registered;
replace them with resources provided by your installed packages.

```sh
php bin/console api:scopes
php bin/console api:client:create "Partner integration" \
    --scope=article.articles:read \
    --scope=api.documentation:read \
    --limit=60
php bin/console api:token:create 1 "Production" \
    --expires=2027-01-01T00:00:00+00:00
```

The final command prints the token once. Only its SHA-256 digest is stored in the
database. Expiry is optional. Use the token in the HTTP header:

```sh
curl -H "Authorization: Bearer $API_TOKEN" \
    'https://example.com/api/v1/articles?limit=20'
```

The web server must pass the `Authorization` header to PHP. Use HTTPS in production.
Tokens inherit their client's current scopes and request limit; grant changes,
client deactivation, token expiry, and revocation take effect on subsequent requests.

```sh
php bin/console api:client:list
php bin/console api:client:disable 1
php bin/console api:client:enable 1
php bin/console api:token:list 1
php bin/console api:token:revoke 1
```

## Resource Conventions

- Only GET and HEAD endpoints are accepted. DI compilation rejects write methods
  and endpoints without explicit required scopes.
- Every required scope must be granted to the client. There are no implied scope
  hierarchies or wildcard grants.
- Scopes come from controller metadata, with client grants stored as strings.
  New resources need no scope fixtures or database scope synchronization.
- Feature packages decide which records and fields are exposed. The convention
  is to return all records, including inactive records, expose `is_active` where
  meaningful, and offer an optional boolean filter.
- List and detail endpoints must use the same access rules. A read scope does
  not imply public visibility or permission to follow arbitrary relationships.
- Responses use explicit DTOs. Doctrine entities are never automatically serialized.
- Collection responses contain `data` and `pagination` (`limit`, `offset`,
  `next_offset`). The default limit is 50, the maximum is 100, and the maximum
  offset is 1,000,000. Ordering must be deterministic.
- Invalid filters produce HTTP 400. Unknown resources produce 404, invalid tokens
  401, insufficient scopes 403, disallowed methods 405, and exhausted limits 429.
- Authenticated responses use `Cache-Control: private, no-store`.

GET `/api/v1/openapi` returns the generated OpenAPI specification and requires
`api.documentation:read`. Response schemas are derived from DTO properties;
endpoint parameters and required scopes come from controller metadata.

Open `/api/docs` for Swagger UI, also linked from the admin client list. Enter a
bearer token with `api.documentation:read` to load the protected specification.
Swagger's Authorize button then manages credentials for GET/HEAD "Try it out"
requests, which still require the individual resource scopes. The initial HTML
shell is public; it does not embed the specification or credentials.

The page serves the Swagger UI assets bundled with `contributte/openapi` from
the same application. It does not use a CDN or external specification validator,
and authorization is not persisted in browser storage.

## Adding Package Endpoints

Implement Apitte's controller interface and register the controller as a Nette
service. For example:

```php
use Apitte\Core\Annotation\Controller\{Method, Path, Response};
use Apitte\Core\UI\Controller\IController;
use Dravencms\Api\Attributes\RequiresScopes;

#[Path('/api/v1/articles')]
#[RequiresScopes('article.articles:read')]
final class ArticleController implements IController
{
    #[Path('/')]
    #[Method(['GET', 'HEAD'])]
    #[Response(code: '200', description: 'Articles', entity: ArticlePage::class)]
    public function listing(): array
    {
        // Return explicitly serialized article data and pagination.
    }
}
```

The controller's scope applies to all its endpoints. A method-level
`RequiresScopes` overrides it. Multiple scope arguments require all listed scopes.
Use stable, package-qualified scope names ending in `:read`.

For optional integration, register controllers from the feature package's DI
extension only when this extension is enabled:

```php
if (class_exists(\Dravencms\Api\DI\ApiExtension::class)
    && $this->compiler->getExtensions(\Dravencms\Api\DI\ApiExtension::class) !== []
) {
    $this->getContainerBuilder()->addDefinition($this->prefix('api.articles'))
        ->setFactory(ArticleController::class);
}
```

Classes merely being installed does not expose endpoints. Add a Composer
`suggest` entry for `dravencms/api` in packages with optional API integration.

## Rate Limits

Each client has a fixed one-minute request window, shared by its tokens. Valid
authenticated requests consume the limit, including requests denied by scopes.
Success responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining`;
429 responses include `Retry-After`.

Symfony RateLimiter uses filesystem-backed cache and locks under
`%tempDir%/api-rate-limits`. Configure a different directory with:

```neon
dravencms.api:
    rateLimitDirectory: /var/cache/my-application/api-rate-limits
```

This backend shares limits between PHP workers on one host. Deployments with
multiple hosts need shared limiter storage and a distributed lock backend.
Unauthenticated traffic should be limited at the web server or proxy.

## Verification

Run integration checks in the consuming application against an isolated test
database. Cover Apitte dispatch, resource queries and visibility rules, token
expiry and revocation, scope enforcement, pagination, rate limits, admin forms,
and repeatable setup. Verify Swagger UI with the application's generated schema.

The package does not currently include a standalone test suite.

API administration declares `api.clientsEdit` through its ACL provider when the User extension is enabled. The optional `api:admin:install` command assigns that known permission through the grant store; it no longer defines permissions or writes labels to ACL tables. Existing installations upgrading to User 3 must run `user:acl:migrate --apply` before using administration.

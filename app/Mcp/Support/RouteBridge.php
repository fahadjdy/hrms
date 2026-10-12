<?php

namespace App\Mcp\Support;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Runs a named web route in-process as a given user and turns the result into
 * plain data for an MCP tool.
 *
 * The request goes through the router with the route's full middleware stack:
 * `auth`, `verified`, `tenant`, the `can:` permission gates, route model
 * binding through the company scope, validation, services and audit logging.
 * An AI agent therefore gets exactly the access the user has in the browser,
 * no more. The request asks for Inertia JSON, so a page returns its props
 * instead of HTML, and validation failures come back as 422 JSON.
 */
final class RouteBridge
{
    /** Largest download returned inline (bytes). */
    public const MAX_FILE_BYTES = 3_000_000;

    /** Shared Inertia props that describe the browser shell, not the page. */
    private const SHELL_PROPS = ['name', 'auth', 'company', 'sidebarOpen', 'errors'];

    /**
     * Input the agent may not set, forced to the user's current value. The
     * sign-in email stays a browser-only change: with it, a leaked token could
     * be turned into a permanent web login through "forgot password".
     *
     * @var array<string, list<string>>
     */
    private const LOCKED_FIELDS = [
        'profile.update' => ['email'],
    ];

    /**
     * @param  array<string, mixed>  $params  Route URI parameters.
     * @param  array<string, mixed>  $input  Query string (GET) or request body.
     * @return array<string, mixed>
     */
    public static function call(User $user, string $routeName, array $params = [], array $input = []): array
    {
        $route = app(Router::class)->getRoutes()->getByName($routeName);

        if (! $route instanceof Route) {
            throw new InvalidArgumentException("Unknown route [{$routeName}].");
        }

        if (! $user->is_active || (! $user->is_super_admin && $user->company_id !== null && ! RouteAccess::isCompanyUser($user))) {
            throw new InvalidArgumentException('This account or its company has been deactivated.');
        }

        foreach (self::LOCKED_FIELDS[$routeName] ?? [] as $field) {
            if (isset($input[$field]) && $input[$field] !== $user->{$field}) {
                throw new InvalidArgumentException("[{$field}] can only be changed in the web app.");
            }

            $input[$field] = $user->{$field};
        }

        $method = collect($route->methods())->first(fn (string $m) => $m !== 'HEAD') ?? 'GET';
        $missing = array_diff($route->parameterNames(), array_keys(array_filter($params, fn ($v) => $v !== null && $v !== '')));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing route parameter(s): '.implode(', ', $missing).'.');
        }

        $path = route($routeName, array_intersect_key($params, array_flip($route->parameterNames())), false);
        [$input, $files] = self::extractFiles(self::normaliseInput($input));

        try {
            $result = self::dispatch($user, $method, $path, $input, $files);

            // Deferred props (e.g. the dashboard sections) are loaded by the browser
            // in a follow-up partial request; do the same so the agent gets them.
            if ($method === 'GET' && isset($result['page']['deferredProps']) && is_array($result['page']['deferredProps'])) {
                $names = collect($result['page']['deferredProps'])->flatten()->unique()->values()->all();

                if ($names !== []) {
                    $partial = self::dispatch($user, 'GET', $path, $input, [], [
                        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => $result['page']['component'],
                        'HTTP_X_INERTIA_PARTIAL_DATA' => implode(',', $names),
                    ]);

                    if (isset($partial['page']['props'])) {
                        $result['page']['props'] = array_merge($result['page']['props'], $partial['page']['props']);
                    }
                }
            }
        } finally {
            foreach ($files as $file) {
                @unlink($file->getPathname());
            }
        }

        return self::present($result, $method, $path);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, UploadedFile>  $files
     * @param  array<string, string>  $extraServer
     * @return array<string, mixed>
     */
    private static function dispatch(User $user, string $method, string $path, array $input, array $files, array $extraServer = []): array
    {
        $app = Container::getInstance();

        // Building the HTTP kernel registers the middleware aliases and groups on
        // the router. Over stdio (console) nothing else has built it yet.
        $app->make(HttpKernel::class);

        $auth = Auth::getFacadeRoot();
        $originalRequest = $app->make('request');
        $originalGuard = $auth->getDefaultDriver();
        $originalSessionDriver = config('session.driver');
        $originalTenant = app(TenantContext::class)->company();

        $request = Request::create(
            url($path),
            $method,
            $input,
            [],
            $files,
            [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'HTTP_X_INERTIA' => 'true',
                // A request built here, not a cross-site browser request.
                'HTTP_SEC_FETCH_SITE' => 'same-origin',
                'REMOTE_ADDR' => $originalRequest->ip() ?? '127.0.0.1',
                'HTTP_USER_AGENT' => 'HRMS-MCP ('.Str::limit((string) $originalRequest->userAgent(), 80, '').')',
                ...$extraServer,
            ],
        );

        $request->headers->set('X-Inertia-Version', (string) app(HandleInertiaRequests::class)->version($request));

        // Sessions of internal requests live in memory only: nothing is written
        // to the sessions table and no cookie leaves the server. The cached
        // array store is dropped so no flash data or errors carry over from the
        // previous call in a long-running (stdio) process.
        config(['session.driver' => 'array']);
        app('session')->forgetDrivers();

        $auth->shouldUse('web');
        $auth->guard('web')->setUser($user);
        $request->setUserResolver(fn ($guard = null) => $auth->guard($guard)->user());
        $app->instance('request', $request);

        try {
            $response = app(Router::class)->dispatch($request);

            return [
                'response' => $response,
                'status' => $response->getStatusCode(),
                'page' => self::inertiaPage($response),
                'flash' => self::flash($request),
                // Streamed downloads run their callback now, while the tenant and
                // user of this request are still set.
                'download' => self::download($response),
            ];
        } finally {
            // The route set the tenant of this user; put back whatever was set before.
            app(TenantContext::class)->set($originalTenant);
            $auth->guard('web')->forgetUser();
            app('session')->forgetDrivers();
            $app->instance('request', $originalRequest);
            $auth->shouldUse($originalGuard);
            config(['session.driver' => $originalSessionDriver]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private static function present(array $result, string $method, string $requestedPath): array
    {
        /** @var SymfonyResponse $response */
        $response = $result['response'];
        $status = $result['status'];
        $flash = $result['flash'];

        if ($result['page'] !== null) {
            $props = array_diff_key($result['page']['props'] ?? [], array_flip(self::SHELL_PROPS));

            return array_filter([
                'ok' => $status < 400,
                'status' => $status < 400 ? null : $status,
                'page' => $result['page']['component'] ?? null,
                'data' => $props,
                'flash' => $flash ?: ($result['page']['flash'] ?? null),
            ], fn ($v) => $v !== null);
        }

        if ($response->isRedirection() || $status === 409) {
            $location = (string) ($response->headers->get('X-Inertia-Location') ?: $response->headers->get('Location'));
            $target = self::relative($location);
            $error = self::redirectError($target, $flash, $requestedPath);

            if ($error !== null) {
                return ['ok' => false, 'status' => $status, 'error' => $error];
            }

            return array_filter([
                'ok' => true,
                'message' => $method === 'GET' ? 'The page redirected.' : 'Saved.',
                'redirect' => $target,
                'flash' => $flash ?: null,
            ], fn ($v) => $v !== null);
        }

        if ($download = $result['download']) {
            return ['ok' => true, 'file' => $download];
        }

        $content = (string) $response->getContent();
        $json = $response instanceof JsonResponse || str_contains((string) $response->headers->get('Content-Type'), 'json')
            ? json_decode($content, true)
            : null;

        if ($status >= 400) {
            return array_filter([
                'ok' => false,
                'status' => $status,
                'error' => self::statusError($status, is_array($json) ? ($json['message'] ?? null) : null),
                'errors' => is_array($json) ? ($json['errors'] ?? null) : null,
            ], fn ($v) => $v !== null);
        }

        return ['ok' => true, 'status' => $status, 'data' => $json ?? Str::limit(trim(strip_tags($content)), 4000)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function inertiaPage(SymfonyResponse $response): ?array
    {
        if (! $response instanceof JsonResponse || $response->headers->get('X-Inertia') !== 'true') {
            return null;
        }

        $page = $response->getData(true);

        return is_array($page) && isset($page['component']) ? $page : null;
    }

    /**
     * Inertia flash data (toasts, one-time credentials) plus plain session
     * flashes such as `status`.
     *
     * @return array<string, mixed>
     */
    private static function flash(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        $session = $request->session();
        $flash = Inertia::getFlashed($request);

        foreach ((array) $session->get('_flash.new', []) as $key) {
            if (is_string($key) && ! str_starts_with($key, 'inertia') && $key !== 'errors' && ! array_key_exists($key, $flash)) {
                $flash[$key] = $session->get($key);
            }
        }

        if ($session->has('errors')) {
            // A ViewErrorBag after validation, a plain array from withErrors() on a
            // redirect that skips ShareErrorsFromSession (e.g. EnsureTenant).
            $errors = $session->get('errors');
            $flash['errors'] = $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : (array) $errors;
        }

        return $flash;
    }

    /**
     * Redirects that mean "not allowed here" rather than "saved".
     *
     * @param  array<string, mixed>  $flash
     */
    private static function redirectError(string $target, array $flash, string $requestedPath): ?string
    {
        $path = '/'.ltrim((string) parse_url($target, PHP_URL_PATH), '/');

        if ($path === '/login') {
            return 'Not signed in, or this account / its company is deactivated.';
        }

        if ($path === '/admin/companies' && ! str_starts_with($requestedPath, '/admin')) {
            return 'This tool works on company data; a super admin account has no company. Use the admin tools (list_companies, ...).';
        }

        if (str_starts_with($path, '/email/verify') || $path === '/verify-email') {
            return 'Verify this account\'s email address in the web app before using company tools.';
        }

        if ($path === '/user/confirm-password') {
            return 'This action needs a recent password confirmation and is only available in the web app.';
        }

        if (! empty($flash['errors'])) {
            return 'Validation failed: '.json_encode($flash['errors'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $toast = $flash['toast'] ?? null;

        if (is_array($toast) && ($toast['type'] ?? null) === 'error') {
            return (string) ($toast['message'] ?? 'The action was refused.');
        }

        return null;
    }

    private static function statusError(int $status, ?string $message): string
    {
        return match (true) {
            $status === 422 => 'Validation failed'.($message ? ": {$message}" : '.'),
            $status === 403 => 'Forbidden: your role does not have the permission this action needs'.($message && $message !== 'This action is unauthorized.' ? " ({$message})" : '.'),
            $status === 404 => 'Not found: the record does not exist in your company, or the area is not available to your account.',
            $status === 419 => 'Session expired (internal request rejected).',
            $status === 429 => 'Too many attempts; wait a minute and try again.',
            default => $message ?: "The request failed with HTTP {$status}.",
        };
    }

    /**
     * File downloads (exports, salary slip PDFs, documents): text inline,
     * binary as base64.
     *
     * @return array<string, mixed>|null
     */
    private static function download(SymfonyResponse $response): ?array
    {
        $disposition = (string) $response->headers->get('Content-Disposition');
        $type = (string) $response->headers->get('Content-Type');

        if (! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse && ! str_contains($disposition, 'filename')) {
            return null;
        }

        try {
            if ($response instanceof BinaryFileResponse) {
                $content = (string) file_get_contents($response->getFile()->getPathname());
                $type = $type ?: (string) $response->getFile()->getMimeType();
            } elseif ($response instanceof StreamedResponse) {
                ob_start();

                try {
                    $response->sendContent();
                } finally {
                    $content = (string) ob_get_clean();
                }
            } else {
                $content = (string) $response->getContent();
            }
        } catch (Throwable $e) {
            return ['error' => 'Could not read the file: '.$e->getMessage()];
        }

        preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m);
        $file = [
            'name' => isset($m[1]) ? rawurldecode($m[1]) : null,
            'mime_type' => $type ?: 'application/octet-stream',
            'size_bytes' => strlen($content),
        ];

        if (strlen($content) > self::MAX_FILE_BYTES) {
            return $file + ['error' => 'The file is too large to return here; download it from the web app.'];
        }

        $isText = preg_match('#^(text/|application/(json|csv|xml))#', $file['mime_type']) === 1 && mb_check_encoding($content, 'UTF-8');

        return $file + ($isText ? ['content' => $content] : ['content_base64' => base64_encode($content)]);
    }

    /**
     * What the global TrimStrings / ConvertEmptyStringsToNull middleware would
     * do for a browser request (the router dispatch skips global middleware).
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private static function normaliseInput(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value) && ! self::isFileSpec($value)) {
                $input[$key] = self::normaliseInput($value);
            } elseif (is_string($value)) {
                $value = is_string($key) && str_contains($key, 'password') ? $value : trim($value);
                $input[$key] = $value === '' ? null : $value;
            }
        }

        return $input;
    }

    /**
     * Files arrive from the agent as {name, content_base64}.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, UploadedFile>}
     */
    private static function extractFiles(array $input): array
    {
        $files = [];

        foreach ($input as $key => $value) {
            if (is_array($value) && self::isFileSpec($value)) {
                $files[$key] = self::uploadedFile($value);
                unset($input[$key]);
            }
        }

        return [$input, $files];
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function isFileSpec(array $value): bool
    {
        return isset($value['content_base64']) && is_string($value['content_base64']);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private static function uploadedFile(array $spec): UploadedFile
    {
        $content = base64_decode((string) preg_replace('#^data:[^;]+;base64,#', '', $spec['content_base64']), true);

        if ($content === false) {
            throw new InvalidArgumentException('content_base64 is not valid base64.');
        }

        $name = basename((string) ($spec['name'] ?? 'upload.bin')) ?: 'upload.bin';
        $path = tempnam(sys_get_temp_dir(), 'mcp');
        file_put_contents($path, $content);

        // test mode: the file did not arrive through PHP's upload handler.
        return new UploadedFile($path, $name, $spec['mime_type'] ?? null, null, true);
    }

    private static function relative(string $url): string
    {
        $root = rtrim(url('/'), '/');

        return str_starts_with($url, $root) ? (substr($url, strlen($root)) ?: '/') : $url;
    }
}

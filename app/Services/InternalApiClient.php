<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\App;

class InternalApiClient
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function get(Request $request, string $uri, array $data = []): array
    {
        return $this->request($request, 'GET', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function post(Request $request, string $uri, array $data = []): array
    {
        return $this->request($request, 'POST', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function put(Request $request, string $uri, array $data = []): array
    {
        return $this->request($request, 'PUT', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function patch(Request $request, string $uri, array $data = []): array
    {
        return $this->request($request, 'PATCH', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function delete(Request $request, string $uri, array $data = []): array
    {
        return $this->request($request, 'DELETE', $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status:int, data:array<string,mixed>|null}
     */
    public function request(Request $request, string $method, string $uri, array $data = []): array
    {
        $token = $this->resolveToken($request);

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];

        $queryData = [];
        $content = null;
        $files = [];
        [$formData, $files] = $this->splitFilesFromPayload($data);
        $hasFiles = $files !== [];

        if (in_array($method, ['GET', 'DELETE'], true)) {
            $queryData = $this->normalizeQueryParameters($data);
        } else {
            if ($hasFiles) {
                $queryData = $formData;
            } else {
                $server['CONTENT_TYPE'] = 'application/json';
                $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $subRequest = Request::create($uri, $method, $queryData, [], $files, $server, $content);
        $this->copyRequestContext($request, $subRequest);

        $app = App::getFacadeRoot();
        $originalRequest = $app->make('request');
        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $app->make('url');

        try {
            $response = $app->handle($subRequest);
        } finally {
            // App::handle() for the internal sub-request swaps the current request instance.
            // Restore the original request so later redirects keep the browser host/port.
            $app->instance('request', $originalRequest);
            $urlGenerator->setRequest($originalRequest);
        }

        $rawBody = (string) $response->getContent();
        $decoded = null;

        if ($rawBody !== '') {
            $json = json_decode($rawBody, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        return [
            'status' => $response->getStatusCode(),
            'data' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: array<string, UploadedFile>}
     */
    private function splitFilesFromPayload(array $data): array
    {
        $formData = [];
        $files = [];

        foreach ($data as $key => $value) {
            [$normalizedForm, $normalizedFiles] = $this->extractFormAndFiles($value);

            if ($normalizedForm !== null) {
                $formData[$key] = $normalizedForm;
            }

            if ($normalizedFiles !== null) {
                $files[$key] = $normalizedFiles;
            }
        }

        return [$formData, $files];
    }

    /**
     * @return array{0:mixed,1:mixed}
     */
    private function extractFormAndFiles(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [null, $value];
        }

        if (! is_array($value)) {
            return [$value, null];
        }

        $form = [];
        $files = [];

        foreach ($value as $nestedKey => $nestedValue) {
            [$nestedForm, $nestedFiles] = $this->extractFormAndFiles($nestedValue);

            if ($nestedForm !== null) {
                $form[$nestedKey] = $nestedForm;
            }

            if ($nestedFiles !== null) {
                $files[$nestedKey] = $nestedFiles;
            }
        }

        return [
            $form === [] ? null : $form,
            $files === [] ? null : $files,
        ];
    }

    private function copyRequestContext(Request $source, Request $target): void
    {
        $target->server->set('HTTP_HOST', $source->getHttpHost());
        $target->server->set('SERVER_NAME', (string) $source->server('SERVER_NAME', $source->getHost()));
        $target->server->set('SERVER_PORT', (string) $source->getPort());
        $target->server->set('REQUEST_SCHEME', $source->getScheme());
        $target->server->set('HTTPS', $source->isSecure() ? 'on' : 'off');
    }

    private function resolveToken(Request $request): string
    {
        $token = (string) $request->session()->get('web_api_token', '');
        if ($token !== '') {
            return $token;
        }

        $user = $request->user();
        if (! $user) {
            return '';
        }

        $newToken = $user->createToken('web-panel-auto');
        $request->session()->put('web_api_token', $newToken->plainTextToken);
        $request->session()->put('web_api_token_id', $newToken->accessToken->id);

        return $newToken->plainTextToken;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeQueryParameters(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[$key] = $this->normalizeQueryParameterValue($value);
        }

        return $normalized;
    }

    private function normalizeQueryParameterValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $nestedValue) {
                $normalized[$key] = $this->normalizeQueryParameterValue($nestedValue);
            }

            return $normalized;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $value;
    }
}

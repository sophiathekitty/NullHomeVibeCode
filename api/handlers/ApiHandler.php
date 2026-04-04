<?php
/**
 * ApiHandler — base class for all API endpoint handlers.
 *
 * Provides a consistent JSON response envelope:
 *   { "success": true|false, "data": ..., "error": null|"message" }
 *
 * Subclasses implement handle(array $params, string $method, array $body): void
 */
abstract class ApiHandler {
    /**
     * Handle the incoming request.
     *
     * @param array  $params  URL path segments after the handler key
     *                        e.g. for /api/lights/1/toggle → ["1", "toggle"]
     * @param string $method  HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param array  $body    Decoded JSON request body (may be empty)
     */
    abstract public function handle(array $params, string $method, array $body): void;

    /** Send a successful JSON response. */
    protected function ok(mixed $data = null): void {
        $this->respond(true, $data, null);
    }

    /** Send an error JSON response with an HTTP status code. */
    protected function error(string $message, int $status = 400): void {
        http_response_code($status);
        $this->respond(false, null, $message);
    }

    /** Send a 404 Not Found error. */
    protected function notFound(string $message = 'Not found'): void {
        $this->error($message, 404);
    }

    /** Send a 405 Method Not Allowed error. */
    protected function methodNotAllowed(): void {
        $this->error('Method not allowed', 405);
    }

    private function respond(bool $success, mixed $data, ?string $error): void {
        header('Content-Type: application/json; charset=utf-8');
        $response = [
            'success' => $success,
            'data'    => $data,
            'error'   => $error,
        ];
        if (class_exists('Debug') && Debug::isEnabled()) {
            $response['debug'] = Debug::getEntries();
        }
        echo json_encode($response);
        exit;
    }
}

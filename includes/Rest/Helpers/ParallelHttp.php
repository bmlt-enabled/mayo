<?php

namespace BmltEnabled\Mayo\Rest\Helpers;

class ParallelHttp
{
    /**
     * Execute multiple GET requests in parallel using the Requests library.
     * Falls back to sequential wp_remote_get when Requests is unavailable.
     *
     * @param array $requests Associative array of [key => url]
     * @param array $options  Optional overrides: timeout, verify
     * @return array Associative array of [key => ['body' => string|null, 'status' => int|string, 'error' => string|null, 'duration_ms' => int, 'size_bytes' => int]]
     */
    public static function get_multiple(array $requests, array $options = []): array
    {
        if (empty($requests)) {
            return [];
        }

        if (!class_exists('\\WpOrg\\Requests\\Requests')) {
            return self::get_multiple_sequential($requests, $options);
        }

        $timeout = $options['timeout'] ?? 15;
        $verify  = $options['verify'] ?? true;

        $batch_start = microtime(true);
        $results     = [];

        // Initialize results with defaults
        foreach ($requests as $key => $url) {
            $results[$key] = [
                'body'        => null,
                'status'      => 0,
                'error'       => null,
                'duration_ms' => 0,
                'size_bytes'  => 0,
            ];
        }

        // Build request array for Requests library
        $req_array = [];
        foreach ($requests as $key => $url) {
            $req_array[$key] = [
                'url'     => $url,
                'type'    => \WpOrg\Requests\Requests::GET,
                'headers' => [],
                'data'    => [],
            ];
        }

        $req_options = [
            'timeout'  => $timeout,
            'verify'   => $verify,
            'complete' => function ($response, $key) use ($batch_start, &$results) {
                $elapsed_ms = round((microtime(true) - $batch_start) * 1000);

                if ($response instanceof \WpOrg\Requests\Exception) {
                    $results[$key]['error']       = $response->getMessage();
                    $results[$key]['status']      = $response->getMessage();
                    $results[$key]['duration_ms'] = $elapsed_ms;
                    return;
                }

                $results[$key]['body']        = $response->body;
                $results[$key]['status']      = (int) $response->status_code;
                $results[$key]['duration_ms'] = $elapsed_ms;
                $results[$key]['size_bytes']  = strlen($response->body);
            },
        ];

        try {
            \WpOrg\Requests\Requests::request_multiple($req_array, $req_options);
        } catch (\WpOrg\Requests\Exception $e) {
            // If the entire batch fails, mark all as errored
            foreach ($results as $key => &$result) {
                if ($result['status'] === 0) {
                    $result['error']       = $e->getMessage();
                    $result['status']      = $e->getMessage();
                    $result['duration_ms'] = round((microtime(true) - $batch_start) * 1000);
                }
            }
        }

        return $results;
    }

    /**
     * Sequential fallback using wp_remote_get.
     *
     * @param array $requests Associative array of [key => url]
     * @param array $options  Optional overrides: timeout, verify
     * @return array Same shape as get_multiple()
     */
    private static function get_multiple_sequential(array $requests, array $options = []): array
    {
        $timeout = $options['timeout'] ?? 15;
        $verify  = $options['verify'] ?? true;
        $results = [];

        foreach ($requests as $key => $url) {
            $t0       = microtime(true);
            $response = wp_remote_get($url, [
                'timeout'   => $timeout,
                'sslverify' => $verify,
            ]);
            $elapsed_ms = round((microtime(true) - $t0) * 1000);

            if (is_wp_error($response)) {
                $results[$key] = [
                    'body'        => null,
                    'status'      => $response->get_error_message(),
                    'error'       => $response->get_error_message(),
                    'duration_ms' => $elapsed_ms,
                    'size_bytes'  => 0,
                ];
            } else {
                $body = wp_remote_retrieve_body($response);
                $results[$key] = [
                    'body'        => $body,
                    'status'      => (int) wp_remote_retrieve_response_code($response),
                    'error'       => null,
                    'duration_ms' => $elapsed_ms,
                    'size_bytes'  => strlen($body),
                ];
            }
        }

        return $results;
    }
}

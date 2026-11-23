<?php
require_once __DIR__ . '/ratelimit.php';

class UserFacingException extends Exception {}

class GithubRateLimitException extends UserFacingException {}

const GITHUB_MAX_BODY_BYTES = 2097152;   // 2 MB is far more than 100 commits ever need

function github_budget_limit($source)
{
    $hasToken = defined('GITHUB_TOKEN') && GITHUB_TOKEN !== '';
    if ($hasToken) {
        return $source === 'cron' ? 4500 : 1500;
    }
    return $source === 'cron' ? 55 : 30;
}

function github_get($url, $source = 'web')
{
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'api.github.com') {
        throw new Exception('Refusing to request ' . $url);
    }

    if (rate_limit_count('github', 'all', 3600) >= github_budget_limit($source)) {
        throw new GithubRateLimitException('GitHub request limit reached. Try again later.');
    }
    rate_limit_record('github', 'all');

    $headers = ['User-Agent: CodeClock', 'Accept: application/vnd.github+json'];
    if (defined('GITHUB_TOKEN') && GITHUB_TOKEN !== '') {
        $headers[] = 'Authorization: Bearer ' . GITHUB_TOKEN;
    }

    if (function_exists('curl_init')) {
        $body = '';
        $options = [
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => false,   // never follow redirects (and never forward the token)
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Collect the body ourselves so we can stop reading when it gets too big.
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > GITHUB_MAX_BODY_BYTES) {
                    return 0;   // abort the transfer
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        } elseif (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch);
        if ($ok === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Could not reach GitHub: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = $body;
    } else {
        // Fallback without cURL. HTTPS streams need the openssl extension.
        if (!extension_loaded('openssl')) {
            throw new Exception('Neither cURL nor OpenSSL is enabled in PHP.');
        }
        $context = stream_context_create(['http' => [
            'method'          => 'GET',
            'header'          => implode("\r\n", $headers),
            'timeout'         => 15,
            'ignore_errors'   => true,   // still give us the body on 404 etc.
            'follow_location' => 0,      // never follow redirects
            'max_redirects'   => 0,
        ]]);
        $response = @file_get_contents($url, false, $context, 0, GITHUB_MAX_BODY_BYTES);
        if ($response === false || empty($http_response_header)) {
            throw new Exception('Could not reach GitHub.');
        }
        $status = 0;
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int)$m[1];   // the last status line wins
            }
        }
    }

    return ['status' => $status, 'body' => json_decode($response, true)];
}

function github_fetch_commits($owner, $repo, $since = null, $source = 'web')
{
    // Checked again here, not only when the project is created: old rows may hold bad values.
    if (!is_valid_github_name($owner) || !is_valid_github_name($repo)) {
        throw new UserFacingException('Invalid repository owner or name.');
    }

    $commits = [];

    for ($page = 1; $page <= 5; $page++) {
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
             . '/commits?per_page=100&page=' . $page;
        if ($since) {
            $url .= '&since=' . urlencode($since);
        }

        $result = github_get($url, $source);

        if ($result['status'] === 404) {
            throw new UserFacingException('Repository not found (or it is private).');
        }
        if ($result['status'] === 409) {
            break;   // GitHub answers 409 for an empty repository: no commits yet, not an error
        }
        if ($result['status'] === 403 || $result['status'] === 429) {
            throw new GithubRateLimitException('GitHub rate limit reached. Try again later.');
        }
        if ($result['status'] !== 200 || !is_array($result['body'])) {
            throw new UserFacingException('GitHub returned an error (HTTP ' . $result['status'] . ').');
        }

        foreach ($result['body'] as $item) {
            $lines = explode("\n", $item['commit']['message'] ?? '');
            $date  = $item['commit']['author']['date'] ?? ($item['commit']['committer']['date'] ?? null);
            if (!$date || empty($item['sha'])) {
                continue;
            }
            $commits[] = [
                'sha'     => $item['sha'],
                'message' => mb_substr($lines[0], 0, 500),
                'date'    => gmdate('Y-m-d H:i:s', strtotime($date)),
            ];
        }

        if (count($result['body']) < 100) {
            break;   // last page
        }
    }

    return $commits;
}

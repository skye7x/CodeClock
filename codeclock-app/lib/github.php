<?php
// Talks to the GitHub REST API (public repos only for now).

// One HTTP GET. Returns status code and decoded JSON body.
function github_get($url)
{
    $headers = ['User-Agent: CodeClock', 'Accept: application/vnd.github+json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Could not reach GitHub: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        // Fallback without cURL. HTTPS streams need the openssl extension.
        if (!extension_loaded('openssl')) {
            throw new Exception('Neither cURL nor OpenSSL is enabled in PHP.');
        }
        $context = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => 15,
            'ignore_errors' => true,   // still give us the body on 404 etc.
        ]]);
        $response = @file_get_contents($url, false, $context);
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

// Fetches commits (newest first). $since is an ISO date like 2026-11-01T00:00:00Z or null.
// Gets at most 5 pages x 100 commits per call.
function github_fetch_commits($owner, $repo, $since = null)
{
    $commits = [];

    for ($page = 1; $page <= 5; $page++) {
        $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
             . '/commits?per_page=100&page=' . $page;
        if ($since) {
            $url .= '&since=' . urlencode($since);
        }

        $result = github_get($url);

        if ($result['status'] === 404) {
            throw new Exception('Repository not found (or it is private).');
        }
        if ($result['status'] === 403 || $result['status'] === 429) {
            throw new Exception('GitHub rate limit reached. Try again later.');
        }
        if ($result['status'] !== 200 || !is_array($result['body'])) {
            throw new Exception('GitHub returned an error (HTTP ' . $result['status'] . ').');
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

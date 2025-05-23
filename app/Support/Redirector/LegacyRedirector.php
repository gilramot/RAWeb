<?php

declare(strict_types=1);

namespace App\Support\Redirector;

use App\Models\Forum;
use App\Models\System;
use Exception;
use GuzzleHttp\Psr7\Query;
use Spatie\MissingPageRedirector\Redirector\Redirector;
use Spatie\Url\Url;
use Symfony\Component\HttpFoundation\Request;

class LegacyRedirector implements Redirector
{
    public function getRedirectsFor(Request $request): array
    {
        $redirects = config('missing-page-redirector.redirects');

        // allow scripts in public/ to abort with 404 without triggering legacy redirects
        // removing the script will redirect eventually
        if (file_exists(public_path($request->getPathInfo()))) {
            return [];
        }

        // Handle legacy viewtopic.php URLs.
        if ($request->getPathInfo() === '/viewtopic.php' && $request->query->has('t')) {
            return $this->handleViewTopicRedirect($request);
        }

        // Handle the legacy create topic URL pattern.
        if (preg_match('#^/forums/forum/(\d+)/topic/create$#', $request->getPathInfo(), $matches)) {
            return $this->handleForumTopicCreateRedirect($matches[1]);
        }

        // Handle system game index URLs with old slug-id format.
        if (preg_match('#^/system/([^/]+-(\d+))/games$#', $request->getPathInfo(), $matches)) {
            return $this->handleSystemGameIndexRedirect($matches[1], $matches[2]);
        }

        /*
         * handle query string
         */
        if (isset($redirects[$request->getPathInfo()])) {
            $queryParams = Query::parse($request->getQueryString() ?? '');
            $redirectUrl = $redirects[$request->getPathInfo()];

            // handle single url mapping to multiple paths based on parameters
            if (is_array($redirectUrl)) {
                foreach ($redirectUrl as $param => $url) {
                    if (empty($param) || array_key_exists($param, $queryParams)) {
                        $redirectUrl = $url;
                        break;
                    }
                }
            } elseif (empty($queryParams)) {
                // no query params to replace. no multiple path mapping.
                // just let default behavior handle it.
                $parsedRedirectUrl = Url::fromString($redirectUrl);

                return [$request->getPathInfo() => (string) $parsedRedirectUrl];
            }

            // forward route and query string values
            foreach ($queryParams as $key => $value) {
                $redirectUrl = str_replace("{{$key}}", $value, $redirectUrl);
            }

            // remove remaining, unused markers
            $parsedRedirectUrl = Url::fromString($redirectUrl);
            foreach ($parsedRedirectUrl->getAllQueryParameters() as $queryParameter => $queryParameterValue) {
                if (str_starts_with($queryParameterValue, '{')) {
                    $parsedRedirectUrl = $parsedRedirectUrl->withoutQueryParameter($queryParameter);
                }
            }

            return [$request->getPathInfo() => (string) $parsedRedirectUrl];
        }

        return $redirects;
    }

    /**
     * Handles the redirection of legacy forum topic create URLs to the new format.
     * Returns an empty array if the forum is not found to allow 404 handling.
     */
    private function handleForumTopicCreateRedirect(string $forumId): array
    {
        try {
            // Find the forum and load the category relation.
            $forum = Forum::with('category')->find($forumId);

            if (!$forum || !$forum->category) {
                return [];
            }

            // Construct the new URL with all required segments.
            $newUrl = sprintf(
                '/forums/%s/%s/create',
                $forum->category->id,
                $forum->id
            );

            return [sprintf('/forums/forum/%s/topic/create', $forumId) => $newUrl];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Handles the redirection of legacy system game index URLs with slug-ID format to the new ID-slug format.
     * Returns an empty array if the system is not found to allow 404 handling.
     */
    private function handleSystemGameIndexRedirect(string $oldSlug, string $systemId): array
    {
        try {
            // Find the system to get its current slug.
            $system = System::find($systemId);

            if (!$system) {
                return [];
            }

            // Construct the new URL with ID-slug format.
            $newUrl = sprintf(
                '/system/%s/games',
                $system->slug // This will now be in ID-slug format from HasSelfHealingUrls.
            );

            return [sprintf('/system/%s/games', $oldSlug) => $newUrl];
        } catch (Exception $e) {
            return [];
        }
    }

    private function handleViewTopicRedirect(Request $request): array
    {
        try {
            // Get the topic ID from the 't' parameter.
            $topicId = $request->query->get('t');

            if (!$topicId) {
                return [];
            }

            // Start building the route parameters.
            $routeParams = ['topic' => $topicId];

            // Add the optional 'c' parameter if it exists.
            if ($request->query->has('c')) {
                $routeParams['comment'] = $request->query->get('c');
            }

            // Generate the base URL with route parameters.
            $newUrl = route('forum-topic.show', $routeParams);

            // Add the fragment identifier if 'c' parameter exists.
            // This is the comment ID to scroll to.
            if ($request->query->has('c')) {
                $newUrl .= '#' . $request->query->get('c');
            }

            return ['/viewtopic.php' => $newUrl];
        } catch (Exception $e) {
            return [];
        }
    }
}

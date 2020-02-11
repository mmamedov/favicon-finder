<?php

namespace App\Inspector;

use App\HttpResponse;

class HeadersInspector implements InspectorInterface
{
    private const GET_HEADERS_TIMEOUT = 2;
    private const MAX_LOCATION_REDIRECTS_TO_FOLLOW = 3;
    private const GET_HEADERS_TO_RETURN_ARRAY = 1;
    private const FAVICON_PATH = '/favicon.ico';

    /**
     * @var HttpResponse
     */
    private $httpResponse;

    /**
     * Load domain headers.
     * Lookup starts with the default favicon URL based on domain provided.
     *
     * @inheritDoc
     * @throws \Exception get_headers() exception
     */
    public function loadByDomain(string $domain): void
    {
        $this->loadByUrl($this->defaultFaviconURL($domain));
    }

    /**
     * @param string $url
     * @throws \Exception
     */
    public function loadByUrl(string $url): void
    {
        $this->httpResponse = new HttpResponse();
        $this->initStreamContext();
        $headers = @get_headers($url, self::GET_HEADERS_TO_RETURN_ARRAY);
        if ($headers === false) {
            throw new \Exception('get_headers() false on ' . $url);
        }
        $this->httpResponse->populateHeaders($headers, $url);
    }

    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    public function findFavicon() : string
    {
        if ($this->getHttpResponse()->isEmpty()) {
            return '';
        }

        /**
         * Happy path, direct hit
         */
        if ($this->getHttpResponse()->isFavicon()) {
            return $this->getHttpResponse()->getUrl();
        }

        /**
         * We have a hit with redirect
         *
         */
        if(!empty($this->getHttpResponse()->getLocationHeader())) {
            return $this->handleLocationRedirect() ?? '';
        }

        return '';
    }

    /**
     * @return HttpResponse
     */
    public function getHttpResponse(): HttpResponse
    {
        return $this->httpResponse;
    }

    /**
     * @return null|string Favicon URL or null
     * @throws \Exception
     */
    private function handleLocationRedirect() : ?string
    {
        $count = 1;
        $response = $this->getHttpResponse();

        while ($count < self::MAX_LOCATION_REDIRECTS_TO_FOLLOW) {
            // Get first redirect in the header
            $redirectLocation = $response->getLocationHeader();
            $redirectLocation = is_array($redirectLocation) ? $redirectLocation[0] : $redirectLocation;

            // We hit a global redirect, like in https://www.qq.com?fromdefault
            if (empty($redirectLocation)) {
                return null;
            }

            // Visit redirect
            try {
                $inspector = new self();
                $inspector->loadByUrl($redirectLocation);
                $response = $inspector->getHttpResponse();
            }
            catch (\Exception $e) {
                return null;
            }

            $newRedirectLocation = $response->getLocationHeader();
            // Found favicon
            if ($newRedirectLocation === null && $response->isFavicon()) {
                return $redirectLocation;
            }
            ++$count;
        }

        return null;
    }

    /**
     * @param string $domain Domain (i.e. yahoo.com)
     * @return string URL to look for favicon
     */
    private function defaultFaviconURL(string $domain): string
    {
        return 'https://' . $domain . self::FAVICON_PATH;
    }

    /**
     * get_headers() settings
     */
    private function initStreamContext() : void {
        stream_context_set_default(
            [
                'http' => [
                    'timeout' => self::GET_HEADERS_TIMEOUT,
                    'header' => [
                        'Connection: close',
                    ],
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]
        );
    }

}
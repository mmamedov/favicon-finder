<?php

namespace App;

class HttpResponse
{
    private const CONTENT_TYPE = 'content-type';
    private const CONTENT_LENGTH = 'content-length';
    private const LOCATION = 'location';

    public const STATUS_OK = '200';
    public const STATUS_MOVED = '301,302';
    public const STATUS_NOT_FOUND = '404';
    public const STATUS_OTHER = 'Any other response code';

    private $headers;
    private $allHeaders;

    private $url;

    /**
     * Populate response headers for defaultStartUrl of the domain
     *
     * @param array $headers HTTP Response headers
     * @param string $url URL to get headers for
     */
    public function populateHeaders(array $headers, string $url) : void
    {
        $this->url = $url;

        //Store internal headers in lowercase, since like in netflix.com 'location', they can be in wrong case
        $headers = array_change_key_case($headers, CASE_LOWER);
        $this->allHeaders = $headers;

        $this->headers[self::LOCATION] = $headers[self::LOCATION] ?? null;
        $this->headers[self::CONTENT_TYPE] = $headers[self::CONTENT_TYPE] ?? null;
        $this->headers[self::CONTENT_LENGTH] = $headers[self::CONTENT_LENGTH] ?? null;
    }

    public function isEmpty() : bool {
        return empty($this->allHeaders);
    }

    public function getAllHeaders() : array
    {
        return $this->allHeaders ?? [];
    }

    public function getLocationHeader() {
        return $this->headers[self::LOCATION];
    }

    public function getContentTypeHeader(){
        return $this->headers[self::CONTENT_TYPE];
    }

    public function getContentLengthHeader(){
        return $this->headers[self::CONTENT_LENGTH];
    }

    public function getFaviconContentTypes() : array
    {
        return [
            'image/x-icon',
            'image/vnd.microsoft.icon',  //i.e. tmall.com
            'image/png', //facebook
            'application/octet-stream' //downloadable like twitch
        ];
    }

    /**
     * Get domain used to get response headers
     *
     * @return null|string
     */
    public function getUrl() : ?string
    {
        return $this->url;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isFavicon(): bool
    {
        if (empty($this->headers)) {
            throw new \Exception('No response found. Please load response first.');
        }

        // No good way to tell with multiple response codes, could be a redirect
        if (isset($this->getAllHeaders()[1])) {
            return false;
        }

        // Eliminate false positives, like with chouftv.ma
        // We might be eliminating some good results here, weirdly sometimes they do return 0 as length
        // Playing safe, as these will be picked up by scraper
        if ($this->getContentLengthHeader() === '0') {
            return false;
        }

        return
            // Higher confidence with Content-Type set
            (in_array($this->getContentTypeHeader(), $this->getFaviconContentTypes(), true)
            && $this->getFirstResponseStatus() === self::STATUS_OK)
            // Pass anything with an image in it
            // Makes above comparison obsolete, but keeping this separate if we need to assign confidence score
            || ( false!== stripos($this->getContentTypeHeader(),'image')
                && $this->getFirstResponseStatus() === self::STATUS_OK)

            ;
    }

    /**
     * Get first response status code.
     * Required to handle redirects.
     *
     * For redirects mutliple responses present. It can be in [1], [2].. This function only cares about first [0]
     * i.e. '[0] => HTTP/1.0 200 OK'
     * @return string
     */
    public function getFirstResponseStatus(): string
    {
        $responseStatus = $this->getAllHeaders()[0] ?? '';

        //200 OK, protocol signature varies, HTTP 1.1/1.0, etc.
        if (preg_match('/200 OK/i', $responseStatus) === 1) {
            return self::STATUS_OK;
        }

        if (preg_match('/404 Not Found/i', $responseStatus) === 1) {
            return self::STATUS_NOT_FOUND;
        }

        return self::STATUS_OTHER;
    }
}
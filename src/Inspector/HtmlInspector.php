<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace App\Inspector;

class HtmlInspector implements InspectorInterface
{
    private const CURL_MAX_REDIRECTS_TO_FOLLOW = 3;
    private const CURL_ESTABLISH_CONNECTION_TIMEOUT = 2;
    private const CURL_WAITING_FOR_RESPONSE_TIMEOUT = 2;

    private $htmlContent;

    /**
     * @var string Curl actual crawl URL
     */
    private $url;

    /**
     * @var string lowercase Curl actual crawling scheme (https/http).
     *             This is needed, because sometimes $url doesn't contain scheme portion.
     */
    private $scheme;

    /**
     * Load domain HTML
     *
     * @inheritDoc
     * @throws \Exception Curl Error
     */
    public function loadByDomain(string $domain) : void
    {
        $url = $this->buildUrlFromDomain($domain);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CURL_ESTABLISH_CONNECTION_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_WAITING_FOR_RESPONSE_TIMEOUT);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, self::CURL_MAX_REDIRECTS_TO_FOLLOW);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) '
            .'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.87 Safari/537.36');

        // For Curl debug uncomment below
        // curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

        $htmlContents = curl_exec($ch);
        if($htmlContents===false){
            throw new \Exception('Curl error: ' . curl_error($ch));
        }

        // Destination URL info, after all Curl redirects, or default one that we passed
        $actualUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?? $url;

        $actualScheme = curl_getinfo($ch, CURLINFO_SCHEME);
        $this->url = is_string($actualUrl) ? $actualUrl : null;
        $this->scheme = is_string($actualScheme) ? strtolower($actualScheme) : null;

        $this->htmlContent = $htmlContents;

        curl_close($ch);
    }

    /**
     * Get Favicon from HTML
     *
     * Assumed that favicon exists in <link rel="shortcut icon" or rel="icon" forms, with the following considerations:
     *  - Return first matching tag. Multiple <link rel>"s possible, some favicon related some not.
     *  - quote agnostic(single,double quotes or none)
     *  - multiple forms of <link> considered, and not dependent on attribute order within tag
     *
     * Favicon code standards reference:
     * https://developer.mozilla.org/en-US/docs/Learn/HTML/Introduction_to_HTML/The_head_metadata_in_HTML
     *
     * @return string favicon URL as-is match returned without manipulation, might return relative path and not full URL.
     */
    public function findFavicon() : string
    {
        if (empty($this->getHtmlContent())) {
            return '';
        }

        // DOM internal error handling only
        libxml_use_internal_errors(true);

        // Many possibilities, including <link .. rel=""(or '')..></link> OR <link ..>
        $linkTags = null;
        preg_match_all('@<link (.*?)>@si', $this->getHtmlContent(), $linkTags);
        foreach($linkTags[0] as $tag)
        {
            // Catch something like <link rel="shortcut icon" href="https://...favicon.ico?v=4" type="image/x-icon"/>
            $doc = new \DOMDocument();
            $doc->loadHTML($tag);
            $currentLink = $doc->getElementsByTagName('link');
            if (in_array($currentLink[0]->getAttribute('rel') ?? '', ['shortcut icon', 'icon'])) {
                return $this->formatFaviconLinkHrefToUrl($currentLink[0]->getAttribute('href') ?? '');
            }
        }

        return '';
    }

    /**
     * @return null|string
     */
    public function getHtmlContent() : ?string
    {
        return $this->htmlContent;
    }

    /**
     * @return null|string URL or input href
     */
    public function getUrl() : ?string
    {
        return $this->url;
    }

    /**
     * Whether http or https was Curled
     *
     * @return null|string scheme
     */
    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    /**
     * Format favicon <link> href to a fully qualified URL
     *
     * @param string $href
     * @return string
     */
    public function formatFaviconLinkHrefToUrl(string $href) : string
    {
        /**
         * Ignore if empty or base_64 encoded string inside href
         */
        if (empty($href) || mb_strpos($href, 'data:image') === 0) {
            return '';
        }

        $href = mb_strtolower(trim($href));

        /**
         * Good URL if starts with http or https, like with youtube.com
         */
        if (mb_strpos($href, 'http') === 0) {
            return $href;
        }

        /**
         * Get host for further processing.
         * If parse_url() fails do not continue and return href, further processing relies on it.
         *
         * We return href here, as it was found in HTML code, even if we don't know relative domain URL.
         */
        $host = parse_url($this->getUrl(), PHP_URL_HOST);
        if (empty($host) || empty($this->getScheme())) {
            return $href;
        }

        /**
         * Start with single or double slash
         *
         * Add crawled scheme(https or http) when starts with //, like with tmall.com
         * or if starts with a single slash /, then it is a relative path to hostname
         */
        if (mb_strpos($href, '//') === 0) {
                return $this->getScheme() . ':' . $href;
        }
        if (mb_strpos($href, '/') === 0){
            // i.e  domain=savefrom.net, $href=/favicon.ico, $url=https://en.savefrom.net/8/
            // output should be: https://en.savefrom.net/favicon.ico
            return $this->getScheme(). '://' . $host . $href;
        }

        /**
         * If no slashes, then relative to url path
         *
         * Relative path will be part until the last slash in URL. parse_url() can't do this.
         * i.e. In http://thestartmagazine.com/feed/summary, relative path should be until feed/
         *
         * i.e  domain=blogspot.com, href=favicon/favicon-32x32.png, url=https://www.blogger.com/about/?r=1-null_user
         * output - https://www.blogger.com/about/favicon/favicon-32x32.png
         */
        return mb_substr($this->getUrl(), 0, mb_strpos($this->getUrl(), '/') + 1) . $href;
    }

    /**
     * @param string $domain domain.com
     * @return string URL
     */
    private function buildUrlFromDomain(string $domain) : string
    {
        return 'https://' . $domain;
    }
}
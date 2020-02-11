<?php

namespace App\Inspector;

/**
 * Interface InspectorInterface
 * @package App\Inspector
 *
 * Inspectors for finding Favicons
 */
interface InspectorInterface
{

    /**
     * Load contents by domain address (i.e. live.com)
     *
     * @param string $domain domain to inspect
     * @return void
     */
    public function loadByDomain(string $domain) : void;

    /**
     * Return Favicon URL based on contents from getByURL()
     *
     * @return string favicon URL
     */
    public function findFavicon() : string;
}
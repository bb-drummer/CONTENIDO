<?php

/**
 * This file contains an implementation of HttpRequest using fsockopen
 *
 * @package    Core
 * @subpackage Core
 * @author     Mischa Holz
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * fsockopen implementation of HttpRequest.
 *
 * @package    Core
 * @subpackage Core
 */
class cHttpRequestSocket extends cHttpRequest {

    /**
     * Array for the post parameters.
     *
     * @var array
     */
    protected $postArray;

    /**
     * Array for the get parameters.
     *
     * @var array
     */
    protected $getArray;

    /**
     * Array for the HTTP-headers.
     *
     * @var array
     */
    protected $headerArray;

    /**
     * Request URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Boundary for the multipart from-data.
     *
     * @var string
     */
    protected $boundary;

    /**
     * The HTTP header.
     *
     * @var string
     */
    protected $header;

    /**
     * The HTTP body.
     *
     * @var string
     */
    protected $body;

    /**
     * Constructor to create an instance of this class.
     *
     * @see cHttpRequest::__construct()
     * @see cHttpRequest::getHttpRequest()
     * @param string $url [optional]
     *         URL for the request
     */
    public function __construct($url = '') {
        $this->url = $url;
    }

    /**
     * Set the request URL.
     *
     * @see cHttpRequest::setURL()
     * @param string $url
     *         the URL
     * @return cHttpRequest
     */
    public function setURL($url) {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the GET parameters.
     *
     * @see cHttpRequest::setGetParams()
     * @param array $array
     *         associative array containing keys and values of the GET parameters
     * @return cHttpRequest
     */
    public function setGetParams($array) {
        $this->getArray = $array;

        return $this;
    }

    /**
     * Set the POST parameters.
     *
     * @see cHttpRequest::setPostParams()
     * @param array $array
     *         associative array containing keys and values of the POST parameters
     * @return cHttpRequest
     */
    public function setPostParams($array) {
        $this->postArray = $array;

        return $this;
    }

    /**
     * Set the HTTP headers.
     *
     * @see cHttpRequest::setHeaders()
     * @param array $array
     *         associative array containing the HTTP headers
     * @return cHttpRequest
     */
    public function setHeaders($array) {
        $this->headerArray = $array;

        return $this;
    }

    /**
     * Inserts the custom headers into the header string.
     */
    protected function prepareHeaders() {
        $this->header = '';
        if (!is_array($this->headerArray)) {
            return;
        }
        foreach ($this->headerArray as $key => $value) {
            $headerString = '';
            if (is_array($value)) {
                $headerString .= $value[0] . ': ' . $value[1];
            } else {
                $headerString .= $key . ': ' . $value;
            }
            $this->header .= $headerString . "\r\n";
        }
    }

    /**
     * Appends teh GET array to the URL.
     */
    protected function prepareGetRequest() {
        if (is_array($this->getArray)) {
            if (!cString::contains($this->url, '?')) {
                $this->url .= '?';
            } else {
                $this->url .= '&';
            }
            foreach ($this->getArray as $key => $value) {
                $this->url .= urlencode($key) . '=' . urlencode($value) . '&';
            }
            $this->url = cString::getPartOfString($this->url, 0, cString::getStringLength($this->url) - 1);
        }
    }

    /**
     * Prepares the headers to send a POST request and encodes the data.
     */
    protected function preparePostRequest() {
        $this->boundary = md5(time()) . md5(time() * rand());
        $this->headerArray['Content-Type'] = 'multipart/form-data; boundary=' . $this->boundary;
        $this->boundary = '--' . $this->boundary;

        $this->body = $this->boundary . "\r\n";
        foreach ($this->postArray as $key => $value) {
            $this->body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
            $this->body .= $value . "\r\n";
            $this->body .= $this->boundary . "\r\n";
        }
        $this->headerArray['Content-Length'] = cString::getStringLength($this->body);
    }

    /**
     * Send the request to the server.
     *
     * @param bool $return
     *         Wether the function should return the servers response
     * @param string $method
     *         GET or PUT
     * @param bool $returnHeaders [optional]
     *         Wether the headers should be included in the response
     * @return string|bool
     */
    protected function sendRequest($return, $method, $returnHeaders = false) {
        if (!(cString::findFirstPos($this->url, 'http') === 0)) {
            $this->url = 'http://' . $this->url;
        }

        $urlInfo = @parse_url($this->url);
        $scheme = '';
        if ($urlInfo['port'] == '') {
            if ($urlInfo['scheme'] == 'https') {
                $urlInfo['port'] = 443;
                $scheme = 'ssl://';
            } else {
                $urlInfo['port'] = 80;
            }
        }

        $this->headerArray['Host'] = ($this->headerArray['Host'] != '') ? $this->headerArray['Host'] : $urlInfo['host'];
        $this->headerArray['Connection'] = ($this->headerArray['Connection'] != '') ? $this->headerArray['Host'] : 'close';
        $this->headerArray['Accept'] = ($this->headerArray['Accept'] != '') ? $this->headerArray['Host'] : '*/*';

        $this->prepareHeaders();

        $handle = @fsockopen($scheme . $urlInfo['host'], $urlInfo['port']);
        if (!$handle) {
            return false;
        }

        $request = $method . ' ';
        $request .= $urlInfo['path'] . '?' . $urlInfo['query'] . ' HTTP/1.1' . "\r\n";
        $request .= $this->header . "\r\n";
        $request .= $this->body;

        fwrite($handle, $request);

        $ret = '';
        while (!feof($handle)) {
            $ret .= fgets($handle);
        }

        fclose($handle);

        if ($return) {
            if (!$returnHeaders) {
                $ret = cString::getPartOfString(cString::strstr($ret, "\r\n\r\n"), cString::getStringLength("\r\n\r\n"));
            }
            return $ret;
        } else {
            return cString::findFirstPos(cString::strstr($ret, '\r\n', true), '200') !== false;
        }
    }

    /**
     * Perform the request using POST.
     *
     * @see cHttpRequest::postRequest()
     * @param bool $return [optional]
     *         If true, response of the server gets returned as string
     * @param bool $returnHeaders [optional]
     *         If true, headers will be included in the response
     * @return string|bool
     *         False on error, response otherwise
     */
    public function postRequest($return = true, $returnHeaders = false) {
        $this->preparePostRequest();

        return $this->sendRequest($return, 'POST', $returnHeaders);
    }

    /**
     * Perform the request using GET.
     *
     * @see cHttpRequest::getRequest()
     * @param bool $return [optional]
     *         If true, response of the server gets returned as string
     * @param bool $returnHeaders [optional]
     *         If true, headers will be included in the response
     * @return string|bool
     *         False on error, response otherwise
     */
    public function getRequest($return = true, $returnHeaders = false) {
        $this->prepareGetRequest();

        return $this->sendRequest($return, 'GET', $returnHeaders);
    }

    /**
     * Perform the request using POST AND append all GET parameters.
     *
     * @see cHttpRequest::request()
     * @param bool $return [optional]
     *         If true, response of the server gets returned as string
     * @param bool $returnHeaders [optional]
     *         If true, headers will be included in the response
     * @return string|bool
     *         False on error, response otherwise
     */
    public function request($return = true, $returnHeaders = false) {
        $this->prepareGetRequest();
        $this->preparePostRequest();

        return $this->sendRequest($return, 'POST', $returnHeaders);
    }
}

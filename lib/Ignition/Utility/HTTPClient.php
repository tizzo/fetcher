<?php

/**
 * @file
 *   Provide a convenient and fully featured HTTP client without any
 *   reliance on bootstrapping Drupal (drupal_http_request) curl, or
 *   enabling `allow_url_fopen` by using PHP streams.
 */

namespace Ignition\Utility;

class HTTPClient {

  /**
   * The URL that will be used for the request.
   */
  public $url = '';

  /**
   * The path of within the URL without a leading `/` (which is added
   * automatically).
   */
  public $path = '';

  /**
   * An internal list of parameters to be used in the request.
   */
  public $params = array();

  /**
   * An array of headers to be used inthe request.
   */
  public $headers = array();

  /**
   * The HTTP request method, acceptable values are GET, SET, PUT,
   * POST and DELETE.
   */
  public $method = 'GET';

  /**
   * An array of supported formats and their decode functions.
   */
  protected $formats = array();

  /**
   * The format to be requested, acceptable values are json and xml.
   */
  public $format = FALSE;

  /**
   * The timeout for this request.
   */
  public $timeout = FALSE;

  /**
   * A container for the raw response of the request.
   */
  public $response = FALSE;

  /**
   * A container for the metadata returned from the stream wrapper,
   * useful for tracking down redirects and the like.
   */
  public $meta = FALSE;

  /**
   * A constructor function to register our default encodings and set a default format.
   */
  public function __construct() {

    // Register plaintext decoding (the default).
    $plainTextDecode = function($textString) {
      return (string) $textString;
    };
    $this->registerEncoding('plain', 'text/plain', $plainTextDecode);

    // Default our decoding to plain
    $this->setFormat('plain');

    // Register our json decoding.
    $jsonDecode = function($jsonString) {
      $response = json_decode($jsonString);
      if ($response === null) {
        $response = FALSE;
      }
      return $response;
    };
    $this->registerEncoding('json', 'application/json', $jsonDecode);

    // Register xml decoding.
    $xmlDecode = function($xmlString) {
      $response = simplexml_load_string($xmlString);
      if ($response === null) {
        $response = FALSE;
      }
      return $response;
    };
    $this->registerEncoding('xml', 'application/xml', $xmlDecode);
  }

  /**
   * Set the URL for this request.
   *
   * @param $url
   *   The URL against which to make the request.
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function setURL($url, $port = FALSE) {
    $this->url = $url;
    if ($port) {
      $this->url = $url . ':' . $port;
    }
    return $this;
  }

  /**
   * Set the path to go after the URL.
   *
   * @param $path
   *   The path to be added to the $url without a leading `/` (which is added automatically).
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  /**
   * Set the HTTP method to be used.
   *
   * @param $method
   *   The method to use for the request. Acceptable values are `GET`, `PUT`, `POST`, and `DELETE`.
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function setMethod($method) {
    $this->method = $method;
    return $this;
  }

  /**
   * Set the format to be used.
   *
   * @param $format
   *   The format with which to send and receive data.  Acceptable values
   *   by default are `plain`, `xml` and `json` defaulting to `plain`.  New
   *   formats may be added via the HTTPClient::registerEncoding() method.
   * @return
   *   This request object, allowing this method to be chainable on success,
   *   FALSE on failure.
   */
  public function setFormat($format) {
    if (isset($this->formats[$format])) {
      $this->format = $format;
      return $this;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Set a header string.
   *
   * @param $name
   *   The name of the header to set (the string before the colon).
   * @param $value
   *   The value of the header to set (the string after the colon and space).
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function setHeader($name, $value) {
    $this->headers[$name] = $value;
    return $this;
  }

  /**
   * Set username and password to use basic HTTP authentication.
   *
   * This is a convenience method to set the appropriate header
   * for basic HTTP authentication.
   *
   * @param $username
   *   A string containing the plaintext username.
   * @param $password
   *   A string containing the plaintext password.
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function setBasicAuth($username, $password) {
    $this->setHeader('Authorization', 'Basic ' .  base64_encode($username.':'.$password));
    return $this;
  }
  
  /**
   * Reset the response data so that a similar request
   * can be made with the same object.
   *
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function reset() {
    $this->response = FALSE;
    $this->meta = FALSE;
    return $this;
  }

  /**
   * Add a parameter for this request.
   *
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function addParam($name, $value) {
    $this->params[$name] = $value;
    return $this;
  }

  /**
   * Remove a parameter from this request.
   *
   * @return
   *   This request object, allowing this method to be chainable.
   */
  public function removeParam($name, $value) {
    unset($this->params[$name]);
    return $this;
  }

  /**
   * Execute the request and return the response object.
   *
   * This method returns this request object rather than the response
   * under the assumption that HTTPRequest::decode() will likely be called immediately after.
   *
   * @return
   *   The body of the request or FALSE of failure.
   */
  public function execute() {
    
    $request_url = $this->url;

    if ($this->path != '') {
      $request_url = $request_url . '/' . $this->path;
    }
    $context_parameters = array(
      'http' => array(
        'method' => $this->method,
        'ignore_errors' => true,
        'user_agent' => 'drush',
      )
    );

    if ($this->timeout) {
      $context_parameters['http']['timeout'] = ($this->timeout['seconds'] * 60) + $this->timeout['microseconds'];
    }

    // Use the configured mime type, this shuold always be set because we default
    // to plain.
    $mimeType = $this->formats[$this->format]['mime type'];
    if (!isset($this->headers['Content Type'])) {
      $this->setHeader('Content Type', $mimeType);
    }
    if (!isset($this->headers['Accept'])) {
      $this->setHeader('Accept', $mimeType);
    }
    if (count($this->headers)) {
      $headers = '';
      foreach ($this->headers as $name => $value) {
        $headers .= $name . ': ' . $value . "\r\n";
      }
      $context_parameters['http']['header'] = $headers;
    }
    $params = $this->params;
    if (count($params)) {
      $params = http_build_query($params);
      if ($this->method == 'POST') {
        $context_parameters['http']['content'] = $params;
      } 
      else {
        $request_url .= '?' . $params;
      }
    }
    $context = stream_context_create($context_parameters);
    $file_resource = @fopen($request_url, 'rb', false, $context);
    if (!$file_resource) {
      $response = false;
    }
    else {
      // Track redirects and other metadata in a member variable.
      $this->meta = stream_get_meta_data($file_resource);
      $response = stream_get_contents($file_resource);
    }
    return $this->response = $response;
  }

  public function setTimeout($seconds, $microseconds = 0) {
    $this->timeout = array('seconds' => $seconds, 'microseconds' => $microseconds);
    return $this;
  }

  /**
   * Decode the response.
   *
   * If format is set to something we understand, decode the response.
   *
   * See HTTPClient::registerEncoding() to add formats.
   *
   * @return
   *  The decoded PHP representation of the data.
   */
  public function decode() {
    if ($this->format && isset($this->formats[$this->format]) && $this->response) {
      return $this->formats[$this->format]['function']($this->response);
    }
  }

  /**
   * Make the request and decode the response.
   *
   * This is a convenience wrapper around execute() and decode().
   */
  public function fetch() {
    $this->execute();
    return $this->decode();
  }

  /**
   * Get the result.
   *
   * Returns the raw result string.
   *
   * @return
   *   The string returned from the request.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Register a format and provide a function to deal with it.
   */
  public function registerEncoding($name, $mimeType, Closure $function) {
    // It would be cool to allow decode callbacks to be injected to allow
    // support for arbitrary formats.
    // TODO: The body of this function :D.
    $this->formats[$name] = array(
      'function' => $function,
      'mime type' => $mimeType,
    );
  }
}

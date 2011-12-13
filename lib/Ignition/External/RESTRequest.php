<?php

// TODO: External is a stupid name.
namespace Ignition\External;

class RESTRequest {

  public $url = '';

  public $path = '';

  public $params = array();

  public $method = 'GET';

  public $format = 'json';

  public $response = FALSE;

  public $meta = FALSE;

  /**
   *
   */
  public function reset() {
    $this->response = FALSE;
    $this->meta = FALSE;
  }

  public function addParam($name, $value) {
    $this->params[$name] = $value;
    return $this;
  }

  public function execute() {
    
    $request_url = $this->url;

    if ($this->path != '') {
      $request_url = $request_url . '/' . $this->path;
    }
    switch ($this->format) {
      default:
        break;
      case 'json':
        $header = 'application/json';
        break;
      case 'xml':
        $header = 'application/xml';
        break;
    }
    $context_parameters = array(
      'http' => array(
        'method' => $this->method,
        'ignore_errors' => true,
        'header' => 'Content-Type: ' . $header . "\n" . 'Accept: ' . $header,
        'user_agent' => 'drush',
      )
    );
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
    $file_resource = fopen($request_url, 'rb', false, $context);
    if (!$file_resource) {
      $res = false;
    }
    else {
      // Track redirects and other metadata in a member variable.
      $this->meta = stream_get_meta_data($file_resource);
      $response = stream_get_contents($file_resource);
    }

    if ($response === false) {
      return FALSE;
    }
    
    $this->response = $response;

    return $this;
  }

  function decode() {
     switch ($this->format) {
      case 'json':
        $response = json_decode($this->response);
        if ($response === null) {
          $response = FALSE;
        }
        break;

      case 'xml':
        $response = simplexml_load_string($this->response);
        if ($r === null) {
          $response = FALSE;
        }
        break;

      default:
        $response = $this->response;
    }
    return $response;
  }
}


<?php
namespace Fetcher\Authentication;

  /**
   *
   */
interface AuthenticationInterface {

  /**
   * Recieve a client object similar to \Fetcher\Utility\HTTPClient() and add authentication parameters.
   *
   * @param $client
   *   An HTTPClient descended object.
   * @return Void
   */
  public function addAuthenticationToHTTPClientFromDrushContext(\Fetcher\Utility\HTTPClient $client);

}

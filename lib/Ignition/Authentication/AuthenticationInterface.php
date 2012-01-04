<?php
namespace Ignition\Authentication;

  /**
   *
   */
interface AuthenticationInterface {

  /**
   *
   */
  static public function addAuthenticationToHTTPClientFromDrushContext($client, $container);

}

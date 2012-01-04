<?php

/**
 * @file
 *   
 */

namespace Ignition\Authentication;

class OpenSshKeys implements \Ignition\Authentication\AuthenticationInterface {

  /**
   *
   */
  static public function addAuthenticationToHTTPClientFromDrushContext($client, $container) {

    // Generate pseudo random noise to sign for this transaction preventing replay attacks.
    $text = $container['random'];
    $private_key = `openssl rsa -in ~/.ssh/id_rsa`;
    $private_key_id = openssl_get_privatekey($private_key);
    // TODO: Dynamically generate the source to prevent replay attacks.
    $signature = '';
    openssl_sign($source, $signature, $private_key_id);

    $client->addParam('ssh.signature', $signature);
    $client->addParam('ssh.fingerprint', $signature);
  }

  /**
   * Generate the fingerprint for an OpenSSH formatted RSA key.
   */
  public function getKeyFingerprint($key) {
  }

}

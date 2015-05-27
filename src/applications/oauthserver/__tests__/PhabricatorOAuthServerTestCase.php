<?php

final class PhabricatorOAuthServerTestCase
  extends PhabricatorTestCase {

  public function testValidateRedirectURI() {
    static $map = array(
      'http://www.google.com'              => true,
      'http://www.google.com/'             => true,
      'http://www.google.com/auth'         => true,
      'www.google.com'                     => false,
      'http://www.google.com/auth#invalid' => false,
    );
    $server = new PhabricatorOAuthServer();
    foreach ($map as $input => $expected) {
      $uri = new PhutilURI($input);
      $result = $server->validateRedirectURI($uri);
      $this->assertEqual(
        $expected,
        $result,
        pht("Validation of redirect URI '%s'", $input));
    }
  }

  public function testValidateSecondaryRedirectURI() {
    $server      = new PhabricatorOAuthServer();
    $primary_uri = new PhutilURI('http://www.google.com/');
    static $test_domain_map = array(
      'http://www.google.com'               => false,
      'http://www.google.com/'              => true,
      'http://www.google.com/auth'          => false,
      'http://www.google.com/?auth'         => true,
      'www.google.com'                      => false,
      'http://www.google.com/auth#invalid'  => false,
      'http://www.example.com'              => false,
    );
    foreach ($test_domain_map as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        pht(
          "Validation of redirect URI '%s' relative to '%s'",
          $input,
          $primary_uri));
    }

    $primary_uri = new PhutilURI('http://www.google.com/?auth');
    static $test_query_map = array(
      'http://www.google.com'               => false,
      'http://www.google.com/'              => false,
      'http://www.google.com/auth'          => false,
      'http://www.google.com/?auth'         => true,
      'http://www.google.com/?auth&stuff'   => true,
      'http://www.google.com/?stuff'        => false,
    );
    foreach ($test_query_map as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        pht(
          "Validation of secondary redirect URI '%s' relative to '%s'",
          $input,
          $primary_uri));
    }

    $primary_uri = new PhutilURI('https://secure.example.com/');
    $tests = array(
      'https://secure.example.com/' => true,
      'http://secure.example.com/'  => false,
    );
    foreach ($tests as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        pht('Validation (https): %s', $input));
    }

    $primary_uri = new PhutilURI('http://example.com/?z=2&y=3');
    $tests = array(
      'http://example.com/?z=2&y=3'      => true,
      'http://example.com/?y=3&z=2'      => true,
      'http://example.com/?y=3&z=2&x=1'  => true,
      'http://example.com/?y=2&z=3'      => false,
      'http://example.com/?y&x'          => false,
      'http://example.com/?z=2&x=3'      => false,
    );
    foreach ($tests as $input => $expected) {
      $uri = new PhutilURI($input);
      $this->assertEqual(
        $expected,
        $server->validateSecondaryRedirectURI($uri, $primary_uri),
        pht('Validation (params): %s', $input));
    }

  }

}

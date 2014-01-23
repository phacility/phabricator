<?php

/**
 * Consolidates Phabricator application cookies, including registration
 * and session management.
 */
final class PhabricatorCookies extends Phobject {

  /**
   * Stores the login username for password authentication. This is just a
   * display value for convenience, used to prefill the login form. It is not
   * authoritative.
   */
  const COOKIE_USERNAME       = 'phusr';


  /**
   * Stores the user's current session ID. This is authoritative and establishes
   * the user's identity.
   */
  const COOKIE_SESSION        = 'phsid';


  /**
   * Stores a secret used during new account registration to prevent an attacker
   * from tricking a victim into registering an account which is linked to
   * credentials the attacker controls.
   */
  const COOKIE_REGISTRATION   = 'phreg';


  /**
   * Stores a secret used during OAuth2 handshakes to prevent various attacks
   * where an attacker hands a victim a URI corresponding to the middle of an
   * OAuth2 workflow and we might otherwise do something sketchy. Particularly,
   * this corresponds to the OAuth2 "code".
   */
  const COOKIE_CLIENTID       = 'phcid';


  /**
   * Stores the URI to redirect the user to after login. This allows users to
   * visit a path like `/feed/`, be prompted to login, and then be redirected
   * back to `/feed/` after the workflow completes.
   */
  const COOKIE_NEXTURI        = 'next_uri';

}

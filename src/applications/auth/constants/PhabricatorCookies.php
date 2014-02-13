<?php

/**
 * Consolidates Phabricator application cookies, including registration
 * and session management.
 *
 * @task next Next URI Cookie
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


/* -(  Next URI Cookie  )---------------------------------------------------- */


  /**
   * Set the Next URI cookie. We only write the cookie if it wasn't recently
   * written, to avoid writing over a real URI with a bunch of "humans.txt"
   * stuff. See T3793 for discussion.
   *
   * @param   AphrontRequest    Request to write to.
   * @param   string            URI to write.
   * @param   bool              Write this cookie even if we have a fresh
   *                            cookie already.
   * @return  void
   *
   * @task next
   */
  public static function setNextURICookie(
    AphrontRequest $request,
    $next_uri,
    $force = false) {

    if (!$force) {
      $cookie_value = $request->getCookie(self::COOKIE_NEXTURI);
      list($set_at, $current_uri) = self::parseNextURICookie($cookie_value);

      // If the cookie was set within the last 2 minutes, don't overwrite it.
      // Primarily, this prevents browser requests for resources which do not
      // exist (like "humans.txt" and various icons) from overwriting a normal
      // URI like "/feed/".
      if ($set_at > (time() - 120)) {
        return;
      }
    }

    $new_value = time().','.$next_uri;
    $request->setCookie(self::COOKIE_NEXTURI, $new_value);
  }


  /**
   * Read the URI out of the Next URI cookie.
   *
   * @param   AphrontRequest  Request to examine.
   * @return  string|null     Next URI cookie's URI value.
   *
   * @task next
   */
  public static function getNextURICookie(AphrontRequest $request) {
    $cookie_value = $request->getCookie(self::COOKIE_NEXTURI);
    list($set_at, $next_uri) = self::parseNExtURICookie($cookie_value);

    return $next_uri;
  }


  /**
   * Parse a Next URI cookie into its components.
   *
   * @param   string        Raw cookie value.
   * @return  list<string>  List of timestamp and URI.
   *
   * @task next
   */
  private static function parseNextURICookie($cookie) {
    // Old cookies look like: /uri
    // New cookies look like: timestamp,/uri

    if (!strlen($cookie)) {
      return null;
    }

    if (strpos($cookie, ',') !== false) {
      list($timestamp, $uri) = explode(',', $cookie, 2);
      return array((int)$timestamp, $uri);
    }

    return array(0, $cookie);
  }

}

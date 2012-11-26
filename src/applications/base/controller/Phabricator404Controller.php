<?php

final class Phabricator404Controller extends PhabricatorController {

  public function shouldRequireLogin() {

    // NOTE: See T2102 for discussion. When a logged-out user requests a page,
    // we give them a login form and set a `next_uri` cookie so we send them
    // back to the page they requested after they login. However, some browsers
    // or extensions request resources which may not exist (like
    // "apple-touch-icon.png" and "humans.txt") and these requests may overwrite
    // the stored "next_uri" after the login page loads. Our options for dealing
    // with this are all bad:
    //
    //  1. We can't put the URI in the form because some login methods (OAuth2)
    //     issue redirects to third-party sites. After T1536 we might be able
    //     to.
    //  2. We could set the cookie only if it doesn't exist, but then a user who
    //     declines to login will end up in the wrong place if they later do
    //     login.
    //  3. We can blacklist all the resources browsers request, but this is a
    //     mess.
    //  4. We can just allow users to access the 404 page without login, so
    //     requesting bad URIs doesn't set the cookie.
    //
    // This implements (4). The main downside is that an attacker can now detect
    // if a URI is routable (i.e., some application is installed) by testing for
    // 404 vs login. If possible, we should implement T1536 in such a way that
    // we can pass the next URI through the login process.

    return false;
  }

  public function processRequest() {
    return new Aphront404Response();
  }

}

<?php

final class PhabricatorDatasourceURIEngineExtension
  extends PhabricatorDatasourceEngineExtension {

  public function newQuickSearchDatasources() {
    return array();
  }

  public function newJumpURI($query) {
    // If you search for a URI on the local install, just redirect to that
    // URI as though you had pasted it into the URI bar.
    if (PhabricatorEnv::isSelfURI($query)) {
      // Strip off the absolute part of the URI. If we don't, the URI redirect
      // validator will get upset that we're performing an unmarked external
      // redirect.

      // The correct host and protocol may also differ from the host and
      // protocol used in the search: for example, if you search for "http://"
      // we want to redirect to "https://" if an install is HTTPS, and
      // the "isSelfURI()" check includes alternate domains in addition to the
      // canonical domain.

      $uri = id(new PhutilURI($query))
        ->setDomain(null)
        ->setProtocol(null)
        ->setPort(null);

      $uri = phutil_string_cast($uri);

      // See T13412. If the URI was in the form "http://dev.example.com" with
      // no trailing slash, there may be no path. Redirecting to the empty
      // string is considered an error by safety checks during redirection,
      // so treat this like the user entered the URI with a trailing slash.
      if (!strlen($uri)) {
        $uri = '/';
      }

      return $uri;
    }

    return null;
  }
}

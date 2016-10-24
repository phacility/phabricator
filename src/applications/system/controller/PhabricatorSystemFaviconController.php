<?php

final class PhabricatorSystemFaviconController extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $webroot = dirname(phutil_get_library_root('phabricator')).'/webroot/';
    $content = Filesystem::readFile($webroot.'/rsrc/favicons/favicon.ico');

    return id(new AphrontFileResponse())
      ->setContent($content)
      ->setMimeType('image/x-icon')
      ->setCacheDurationInSeconds(phutil_units('24 hours in seconds'))
      ->setCanCDN(true);
  }
}

<?php

final class PhabricatorFileProxyController extends PhabricatorFileController {

  private $uri;

  public function processRequest() {

    if (!PhabricatorEnv::getEnvConfig('files.enable-proxy')) {
      return new Aphront400Response();
    }

    $request = $this->getRequest();
    $uri = $request->getStr('uri');

    $proxy = id(new PhabricatorFileProxyImage())->loadOneWhere(
      'uri = %s',
      $uri);

    if (!$proxy) {
      // This write is fine to skip CSRF checks for, we're just building a
      // cache of some remote image.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $file = PhabricatorFile::newFromFileDownload(
        $uri,
        nonempty(basename($uri), 'proxied-file'));
      if ($file) {
        $proxy = new PhabricatorFileProxyImage();
        $proxy->setURI($uri);
        $proxy->setFilePHID($file->getPHID());
        $proxy->save();
      }

      unset($unguarded);
    }

    if ($proxy) {
      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s',
                                                      $proxy->getFilePHID());
      if ($file) {
        $view_uri = $file->getBestURI();
      } else {
        $bad_phid = $proxy->getFilePHID();
        throw new Exception(
          "Unable to load file with phid {$bad_phid}."
        );
      }
      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    return new Aphront400Response();
  }
}

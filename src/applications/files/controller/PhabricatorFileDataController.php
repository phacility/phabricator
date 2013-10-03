<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->key  = $data['key'];
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    $uri = new PhutilURI($alt);
    $alt_domain = $uri->getDomain();
    if ($alt_domain && ($alt_domain != $request->getHost())) {
      return id(new AphrontRedirectResponse())
        ->setURI($uri->setPath($request->getPath()));
    }

    // NOTE: This endpoint will ideally be accessed via CDN or otherwise on
    // a non-credentialed domain. Knowing the file's secret key gives you
    // access, regardless of authentication on the request itself.

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($this->phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    if (!$file->validateSecretKey($this->key)) {
      return new Aphront403Response();
    }

    $data = $file->loadFileData();
    $response = new AphrontFileResponse();
    $response->setContent($data);
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);

    // NOTE: It's important to accept "Range" requests when playing audio.
    // If we don't, Safari has difficulty figuring out how long sounds are
    // and glitches when trying to loop them. In particular, Safari sends
    // an initial request for bytes 0-1 of the audio file, and things go south
    // if we can't respond with a 206 Partial Content.
    $range = $request->getHTTPHeader('range');
    if ($range) {
      $matches = null;
      if (preg_match('/^bytes=(\d+)-(\d+)$/', $range, $matches)) {
        $response->setHTTPResponseCode(206);
        $response->setRange((int)$matches[1], (int)$matches[2]);
      }
    }

    $is_viewable = $file->isViewableInBrowser();
    $force_download = $request->getExists('download');

    if ($is_viewable && !$force_download) {
      $response->setMimeType($file->getViewableMimeType());
    } else {
      if (!$request->isHTTPPost()) {
        // NOTE: Require POST to download files. We'd rather go full-bore and
        // do a real CSRF check, but can't currently authenticate users on the
        // file domain. This should blunt any attacks based on iframes, script
        // tags, applet tags, etc., at least. Send the user to the "info" page
        // if they're using some other method.
        return id(new AphrontRedirectResponse())
          ->setURI(PhabricatorEnv::getProductionURI($file->getBestURI()));
      }
      $response->setMimeType($file->getMimeType());
      $response->setDownload($file->getName());
    }

    return $response;
  }
}

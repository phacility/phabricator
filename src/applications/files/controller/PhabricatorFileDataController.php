<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;
  private $file;

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $this->phid = $request->getURIData('phid');
    $this->key = $request->getURIData('key');

    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    $alt_uri = new PhutilURI($alt);
    $alt_domain = $alt_uri->getDomain();
    $req_domain = $request->getHost();
    $main_domain = id(new PhutilURI($base_uri))->getDomain();

    if (!strlen($alt) || $main_domain == $alt_domain) {
      // No alternate domain.
      $should_redirect = false;
      $is_alternate_domain = false;
    } else if ($req_domain != $alt_domain) {
      // Alternate domain, but this request is on the main domain.
      $should_redirect = true;
      $is_alternate_domain = false;
    } else {
      // Alternate domain, and on the alternate domain.
      $should_redirect = false;
      $is_alternate_domain = true;
    }

    $response = $this->loadFile();
    if ($response) {
      return $response;
    }

    $file = $this->getFile();

    if ($should_redirect) {
      return id(new AphrontRedirectResponse())
        ->setIsExternal(true)
        ->setURI($file->getCDNURI());
    }

    $response = new AphrontFileResponse();
    $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    $response->setCanCDN($file->getCanCDN());

    $begin = null;
    $end = null;

    // NOTE: It's important to accept "Range" requests when playing audio.
    // If we don't, Safari has difficulty figuring out how long sounds are
    // and glitches when trying to loop them. In particular, Safari sends
    // an initial request for bytes 0-1 of the audio file, and things go south
    // if we can't respond with a 206 Partial Content.
    $range = $request->getHTTPHeader('range');
    if ($range) {
      $matches = null;
      if (preg_match('/^bytes=(\d+)-(\d+)$/', $range, $matches)) {
        // Note that the "Range" header specifies bytes differently than
        // we do internally: the range 0-1 has 2 bytes (byte 0 and byte 1).
        $begin = (int)$matches[1];
        $end = (int)$matches[2] + 1;

        $response->setHTTPResponseCode(206);
        $response->setRange($begin, ($end - 1));
      }
    }

    $is_viewable = $file->isViewableInBrowser();
    $force_download = $request->getExists('download');

    $request_type = $request->getHTTPHeader('X-Phabricator-Request-Type');
    $is_lfs = ($request_type == 'git-lfs');

    if ($is_viewable && !$force_download) {
      $response->setMimeType($file->getViewableMimeType());
    } else {
      if (!$request->isHTTPPost() && !$is_alternate_domain && !$is_lfs) {
        // NOTE: Require POST to download files from the primary domain. We'd
        // rather go full-bore and do a real CSRF check, but can't currently
        // authenticate users on the file domain. This should blunt any
        // attacks based on iframes, script tags, applet tags, etc., at least.
        // Send the user to the "info" page if they're using some other method.

        // This is marked as "external" because it is fully qualified.
        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI(PhabricatorEnv::getProductionURI($file->getBestURI()));
      }
      $response->setMimeType($file->getMimeType());
      $response->setDownload($file->getName());
    }

    $iterator = $file->getFileDataIterator($begin, $end);

    $response->setContentLength($file->getByteSize());
    $response->setContentIterator($iterator);

    return $response;
  }

  private function loadFile() {
    // Access to files is provided by knowledge of a per-file secret key in
    // the URI. Knowledge of this secret is sufficient to retrieve the file.

    // For some requests, we also have a valid viewer. However, for many
    // requests (like alternate domain requests or Git LFS requests) we will
    // not. Even if we do have a valid viewer, use the omnipotent viewer to
    // make this logic simpler and more consistent.

    // Beyond making the policy check itself more consistent, this also makes
    // sure we're consitent about returning HTTP 404 on bad requests instead
    // of serving HTTP 200 with a login page, which can mislead some clients.

    $viewer = PhabricatorUser::getOmnipotentUser();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->phid))
      ->executeOne();

    if (!$file) {
      return new Aphront404Response();
    }

    // We may be on the CDN domain, so we need to use a fully-qualified URI
    // here to make sure we end up back on the main domain.
    $info_uri = PhabricatorEnv::getURI($file->getInfoURI());


    if (!$file->validateSecretKey($this->key)) {
      $dialog = $this->newDialog()
        ->setTitle(pht('Invalid Authorization'))
        ->appendParagraph(
          pht(
            'The link you followed to access this file is no longer '.
            'valid. The visibility of the file may have changed after '.
            'the link was generated.'))
        ->appendParagraph(
          pht(
            'You can continue to the file detail page to get more '.
            'information and attempt to access the file.'))
        ->addCancelButton($info_uri, pht('Continue'));

      return id(new AphrontDialogResponse())
        ->setDialog($dialog)
        ->setHTTPResponseCode(404);
    }

    if ($file->getIsPartial()) {
      $dialog = $this->newDialog()
        ->setTitle(pht('Partial Upload'))
        ->appendParagraph(
          pht(
            'This file has only been partially uploaded. It must be '.
            'uploaded completely before you can download it.'))
        ->appendParagraph(
          pht(
            'You can continue to the file detail page to monitor the '.
            'upload progress of the file.'))
        ->addCancelButton($info_uri, pht('Continue'));

      return id(new AphrontDialogResponse())
        ->setDialog($dialog)
        ->setHTTPResponseCode(404);
    }

    $this->file = $file;

    return null;
  }

  private function getFile() {
    if (!$this->file) {
      throw new PhutilInvalidStateException('loadFile');
    }
    return $this->file;
  }

}

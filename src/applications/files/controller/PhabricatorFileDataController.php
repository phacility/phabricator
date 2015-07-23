<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;
  private $token;
  private $file;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->key  = $data['key'];
    $this->token = idx($data, 'token');
  }

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $alt = PhabricatorEnv::getEnvConfig('security.alternate-file-domain');
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    $alt_uri = new PhutilURI($alt);
    $alt_domain = $alt_uri->getDomain();
    $req_domain = $request->getHost();
    $main_domain = id(new PhutilURI($base_uri))->getDomain();

    $cache_response = true;

    if (empty($alt) || $main_domain == $alt_domain) {
      // Alternate files domain isn't configured or it's set
      // to the same as the default domain

      $response = $this->loadFile($viewer);
      if ($response) {
        return $response;
      }
      $file = $this->getFile();

      // when the file is not CDNable, don't allow cache
      $cache_response = $file->getCanCDN();
    } else if ($req_domain != $alt_domain) {
      // Alternate domain is configured but this request isn't using it

      $response = $this->loadFile($viewer);
      if ($response) {
        return $response;
      }
      $file = $this->getFile();

      // if the user can see the file, generate a token;
      // redirect to the alt domain with the token;
      $token_uri = $file->getCDNURIWithToken();
      $token_uri = new PhutilURI($token_uri);
      $token_uri = $this->addURIParameters($token_uri);

      return id(new AphrontRedirectResponse())
        ->setIsExternal(true)
        ->setURI($token_uri);

    } else {
      // We are using the alternate domain. We don't have authentication
      // on this domain, so we bypass policy checks when loading the file.

      $bypass_policies = PhabricatorUser::getOmnipotentUser();
      $response = $this->loadFile($bypass_policies);
      if ($response) {
        return $response;
      }
      $file = $this->getFile();

      $acquire_token_uri = id(new PhutilURI($file->getViewURI()))
        ->setDomain($main_domain);
      $acquire_token_uri = $this->addURIParameters($acquire_token_uri);

      if ($this->token) {
        // validate the token, if it is valid, continue
        $validated_token = $file->validateOneTimeToken($this->token);

        if (!$validated_token) {
          $dialog = $this->newDialog()
            ->setShortTitle(pht('Expired File'))
            ->setTitle(pht('File Link Has Expired'))
            ->appendParagraph(
              pht(
                'The link you followed to view this file is invalid or '.
                'expired.'))
            ->appendParagraph(
              pht(
                'Continue to generate a new link to the file. You may be '.
                'required to log in.'))
            ->addCancelButton(
              $acquire_token_uri,
              pht('Continue'));

          // Build an explicit response so we can respond with HTTP/403 instead
          // of HTTP/200.
          $response = id(new AphrontDialogResponse())
            ->setDialog($dialog)
            ->setHTTPResponseCode(403);

          return $response;
        }
        // return the file data without cache headers
        $cache_response = false;
      } else if (!$file->getCanCDN()) {
        // file cannot be served via cdn, and no token given
        // redirect to the main domain to aquire a token

        // This is marked as an "external" URI because it is fully qualified.
        return id(new AphrontRedirectResponse())
          ->setIsExternal(true)
          ->setURI($acquire_token_uri);
      }
    }

    $response = new AphrontFileResponse();
    if ($cache_response) {
      $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    }

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
    } else if (isset($validated_token)) {
      // We set this on the response, and the response deletes it after the
      // transfer completes. This allows transfers to be resumed, in theory.
      $response->setTemporaryFileToken($validated_token);
    }

    $is_viewable = $file->isViewableInBrowser();
    $force_download = $request->getExists('download');

    if ($is_viewable && !$force_download) {
      $response->setMimeType($file->getViewableMimeType());
    } else {
      if (!$request->isHTTPPost() && !$alt_domain) {
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

  /**
   * Add passthrough parameters to the URI so they aren't lost when we
   * redirect to acquire tokens.
   */
  private function addURIParameters(PhutilURI $uri) {
    $request = $this->getRequest();

    if ($request->getBool('download')) {
      $uri->setQueryParam('download', 1);
    }

    return $uri;
  }

  private function loadFile(PhabricatorUser $viewer) {
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->phid))
      ->executeOne();

    if (!$file) {
      return new Aphront404Response();
    }

    if (!$file->validateSecretKey($this->key)) {
      return new Aphront403Response();
    }

    if ($file->getIsPartial()) {
      // We may be on the CDN domain, so we need to use a fully-qualified URI
      // here to make sure we end up back on the main domain.
      $info_uri = PhabricatorEnv::getURI($file->getInfoURI());

      return $this->newDialog()
        ->setTitle(pht('Partial Upload'))
        ->appendParagraph(
          pht(
            'This file has only been partially uploaded. It must be '.
            'uploaded completely before you can download it.'))
        ->addCancelButton($info_uri);
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

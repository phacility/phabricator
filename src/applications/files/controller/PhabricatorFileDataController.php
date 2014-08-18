<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;
  private $token;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->key  = $data['key'];
    $this->token = idx($data, 'token');
  }

  public function shouldRequireLogin() {
    return false;
  }

  protected function checkFileAndToken($file) {
    if (!$file) {
      return new Aphront404Response();
    }

    if (!$file->validateSecretKey($this->key)) {
      return new Aphront403Response();
    }

    return null;
  }

  public function processRequest() {
    $request = $this->getRequest();

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

      // load the file with permissions checks;
      $file = id(new PhabricatorFileQuery())
        ->setViewer($request->getUser())
        ->withPHIDs(array($this->phid))
        ->executeOne();

      $error_response = $this->checkFileAndToken($file);
      if ($error_response) {
        return $error_response;
      }

      // when the file is not CDNable, don't allow cache
      $cache_response = $file->getCanCDN();
    } else if ($req_domain != $alt_domain) {
      // Alternate domain is configured but this request isn't using it

      // load the file with permissions checks;
      $file = id(new PhabricatorFileQuery())
        ->setViewer($request->getUser())
        ->withPHIDs(array($this->phid))
        ->executeOne();

      $error_response = $this->checkFileAndToken($file);
      if ($error_response) {
        return $error_response;
      }

      // if the user can see the file, generate a token;
      // redirect to the alt domain with the token;
      return id(new AphrontRedirectResponse())
        ->setIsExternal(true)
        ->setURI($file->getCDNURIWithToken());

    } else {
      // We are using the alternate domain

      // load the file, bypassing permission checks;
      $file = id(new PhabricatorFileQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(array($this->phid))
        ->executeOne();

      $error_response = $this->checkFileAndToken($file);
      if ($error_response) {
        return $error_response;
      }

      $acquire_token_uri = id(new PhutilURI($file->getViewURI()))
        ->setDomain($main_domain);


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

    $data = $file->loadFileData();
    $response = new AphrontFileResponse();
    $response->setContent($data);
    if ($cache_response) {
      $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);
    }

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
    } else if (isset($validated_token)) {
      // consume the one-time token if we have one.
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $validated_token->delete();
      unset($unguarded);
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

    return $response;
  }
}

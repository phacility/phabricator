<?php

final class PhabricatorFileDataController extends PhabricatorFileController {

  private $phid;
  private $key;
  private $file;

  public function shouldRequireLogin() {
    return false;
  }

  public function shouldAllowPartialSessions() {
    return true;
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

    $request_kind = $request->getURIData('kind');
    $is_download = ($request_kind === 'download');

    if (($alt === null || !strlen($alt)) || $main_domain == $alt_domain) {
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
        ->setURI($file->getCDNURI($request_kind));
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
    if ($range !== null && strlen($range)) {
      list($begin, $end) = $response->parseHTTPRange($range);
    }

    if (!$file->isViewableInBrowser()) {
      $is_download = true;
    }

    $request_type = $request->getHTTPHeader('X-Phabricator-Request-Type');
    $is_lfs = ($request_type == 'git-lfs');

    if (!$is_download) {
      $response->setMimeType($file->getViewableMimeType());
    } else {
      $is_post = $request->isHTTPPost();
      $is_public = !$viewer->isLoggedIn();

      // NOTE: Require POST to download files from the primary domain. If the
      // request is not a POST request but arrives on the primary domain, we
      // render a confirmation dialog. For discussion, see T13094.

      // There are two exceptions to this rule:

      // Git LFS requests can download with GET. This is safe (Git LFS won't
      // execute files it downloads) and necessary to support Git LFS.

      // Requests with no credentials may also download with GET. This
      // primarily supports downloading files with `arc download` or other
      // API clients. This is only "mostly" safe: if you aren't logged in, you
      // are likely immune to XSS and CSRF. However, an attacker may still be
      // able to set cookies on this domain (for example, to fixate your
      // session). For now, we accept these risks because users running
      // Phabricator in this mode are knowingly accepting a security risk
      // against setup advice, and there's significant value in having
      // API development against test and production installs work the same
      // way.

      $is_safe = ($is_alternate_domain || $is_post || $is_lfs || $is_public);
      if (!$is_safe) {
        return $this->newDialog()
          ->setSubmitURI($file->getDownloadURI())
          ->setTitle(pht('Download File'))
          ->appendParagraph(
            pht(
              'Download file %s (%s)?',
              phutil_tag('strong', array(), $file->getName()),
              phutil_format_bytes($file->getByteSize())))
          ->addCancelButton($file->getURI())
          ->addSubmitButton(pht('Download File'));
      }

      $response->setMimeType($file->getMimeType());
      $response->setDownload($file->getName());
    }

    $iterator = $file->getFileDataIterator($begin, $end);

    $response->setContentLength($file->getByteSize());
    $response->setContentIterator($iterator);

    // In Chrome, we must permit this domain in "object-src" CSP when serving a
    // PDF or the browser will refuse to render it.
    if (!$is_download && $file->isPDF()) {
      $request_uri = id(clone $request->getAbsoluteRequestURI())
        ->setPath(null)
        ->setFragment(null)
        ->removeAllQueryParams();

      $response->addContentSecurityPolicyURI(
        'object-src',
        (string)$request_uri);
    }

    if ($this->shouldCompressFileDataResponse($file)) {
      $response->setCompressResponse(true);
    }

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
    // sure we're consistent about returning HTTP 404 on bad requests instead
    // of serving HTTP 200 with a login page, which can mislead some clients.

    $viewer = PhabricatorUser::getOmnipotentUser();

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->phid))
      ->withIsDeleted(false)
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

  private function shouldCompressFileDataResponse(PhabricatorFile $file) {
    // If the client sends "Accept-Encoding: gzip", we have the option of
    // compressing the response.

    // We generally expect this to be a good idea if the file compresses well,
    // but maybe not such a great idea if the file is already compressed (like
    // an image or video) or compresses poorly: the CPU cost of compressing and
    // decompressing the stream may exceed the bandwidth savings during
    // transfer.

    // Ideally, we'd probably make this decision by compressing files when
    // they are uploaded, storing the compressed size, and then doing a test
    // here using the compression savings and estimated transfer speed.

    // For now, just guess that we shouldn't compress images or videos or
    // files that look like they are already compressed, and should compress
    // everything else.

    if ($file->isViewableImage()) {
      return false;
    }

    if ($file->isAudio()) {
      return false;
    }

    if ($file->isVideo()) {
      return false;
    }

    $compressed_types = array(
      'application/x-gzip',
      'application/x-compress',
      'application/x-compressed',
      'application/x-zip-compressed',
      'application/zip',
    );
    $compressed_types = array_fuse($compressed_types);

    $mime_type = $file->getMimeType();
    if (isset($compressed_types[$mime_type])) {
      return false;
    }

    return true;
  }

}

<?php

final class PhabricatorFileImageProxyController
  extends PhabricatorFileController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {

    $show_prototypes = PhabricatorEnv::getEnvConfig(
      'phabricator.show-prototypes');
    if (!$show_prototypes) {
      throw new Exception(
        pht('Show prototypes is disabled.
          Set `phabricator.show-prototypes` to `true` to use the image proxy'));
    }

    $viewer = $request->getViewer();
    $img_uri = $request->getStr('uri');

    // Validate the URI before doing anything
    PhabricatorEnv::requireValidRemoteURIForLink($img_uri);
    $uri = new PhutilURI($img_uri);
    $proto = $uri->getProtocol();
    if (!in_array($proto, array('http', 'https'))) {
      throw new Exception(
        pht('The provided image URI must be either http or https'));
    }

    // Check if we already have the specified image URI downloaded
    $cached_request = id(new PhabricatorFileExternalRequest())->loadOneWhere(
      'uriIndex = %s',
      PhabricatorHash::digestForIndex($img_uri));

    if ($cached_request) {
      return $this->getExternalResponse($cached_request);
    }

    $ttl = PhabricatorTime::getNow() + phutil_units('7 days in seconds');
    $external_request = id(new PhabricatorFileExternalRequest())
      ->setURI($img_uri)
      ->setTTL($ttl);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    // Cache missed so we'll need to validate and download the image
    try {
      // Rate limit outbound fetches to make this mechanism less useful for
      // scanning networks and ports.
      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorFilesOutboundRequestAction(),
        1);

      $file = PhabricatorFile::newFromFileDownload(
        $uri,
        array(
          'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
          'canCDN' => true,
        ));
      if (!$file->isViewableImage()) {
        $mime_type = $file->getMimeType();
        $engine = new PhabricatorDestructionEngine();
        $engine->destroyObject($file);
        $file = null;
        throw new Exception(
          pht(
            'The URI "%s" does not correspond to a valid image file, got '.
            'a file with MIME type "%s". You must specify the URI of a '.
            'valid image file.',
            $uri,
            $mime_type));
      } else {
        $file->save();
      }

      $external_request->setIsSuccessful(true)
        ->setFilePHID($file->getPHID())
        ->save();
      unset($unguarded);
      return $this->getExternalResponse($external_request);
    } catch (HTTPFutureHTTPResponseStatus $status) {
      $external_request->setIsSuccessful(false)
        ->setResponseMessage($status->getMessage())
        ->save();
      return $this->getExternalResponse($external_request);
    } catch (Exception $ex) {
      // Not actually saving the request in this case
      $external_request->setResponseMessage($ex->getMessage());
      return $this->getExternalResponse($external_request);
    }
  }

  private function getExternalResponse(
    PhabricatorFileExternalRequest $request) {
    if ($request->getIsSuccessful()) {
      $file = id(new PhabricatorFileQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPHIDs(array($request->getFilePHID()))
        ->executeOne();
      if (!file) {
        throw new Exception(pht(
          'The underlying file does not exist, but the cached request was '.
          'successful. This likely means the file record was manually deleted '.
          'by an administrator.'));
      }
      return id(new AphrontRedirectResponse())
        ->setIsExternal(true)
        ->setURI($file->getViewURI());
    } else {
      throw new Exception(pht(
        "The request to get the external file from '%s' was unsuccessful:\n %s",
        $request->getURI(),
        $request->getResponseMessage()));
    }
  }
}

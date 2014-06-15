<?php

final class PhabricatorFileTransformController
  extends PhabricatorFileController {

  private $transform;
  private $phid;
  private $key;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->transform = $data['transform'];
    $this->phid      = $data['phid'];
    $this->key       = $data['key'];
  }

  public function processRequest() {
    $viewer = $this->getRequest()->getUser();

    // NOTE: This is a public/CDN endpoint, and permission to see files is
    // controlled by knowing the secret key, not by authentication.

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

    $xform = id(new PhabricatorTransformedFile())
      ->loadOneWhere(
        'originalPHID = %s AND transform = %s',
        $this->phid,
        $this->transform);

    if ($xform) {
      return $this->buildTransformedFileResponse($xform);
    }

    $type = $file->getMimeType();

    if (!$file->isViewableInBrowser() || !$file->isTransformableImage()) {
      return $this->buildDefaultTransformation($file);
    }

    // We're essentially just building a cache here and don't need CSRF
    // protection.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    switch ($this->transform) {
      case 'thumb-profile':
        $xformed_file = $this->executeThumbTransform($file, 50, 50);
        break;
      case 'thumb-280x210':
        $xformed_file = $this->executeThumbTransform($file, 280, 210);
        break;
      case 'thumb-220x165':
        $xformed_file = $this->executeThumbTransform($file, 220, 165);
        break;
      case 'preview-100':
        $xformed_file = $this->executePreviewTransform($file, 100);
        break;
      case 'preview-220':
        $xformed_file = $this->executePreviewTransform($file, 220);
        break;
      case 'thumb-160x120':
        $xformed_file = $this->executeThumbTransform($file, 160, 120);
        break;
      case 'thumb-60x45':
        $xformed_file = $this->executeThumbTransform($file, 60, 45);
        break;
      default:
        return new Aphront400Response();
    }

    if (!$xformed_file) {
      return new Aphront400Response();
    }

    $xform = new PhabricatorTransformedFile();
    $xform->setOriginalPHID($this->phid);
    $xform->setTransform($this->transform);
    $xform->setTransformedPHID($xformed_file->getPHID());
    $xform->save();

    return $this->buildTransformedFileResponse($xform);
  }

  private function buildDefaultTransformation(PhabricatorFile $file) {
    static $regexps = array(
      '@application/zip@'     => 'zip',
      '@image/@'              => 'image',
      '@application/pdf@'     => 'pdf',
      '@.*@'                  => 'default',
    );

    $type = $file->getMimeType();
    $prefix = 'default';
    foreach ($regexps as $regexp => $implied_prefix) {
      if (preg_match($regexp, $type)) {
        $prefix = $implied_prefix;
        break;
      }
    }

    switch ($this->transform) {
      case 'thumb-280x210':
        $suffix = '280x210';
        break;
      case 'thumb-160x120':
        $suffix = '160x120';
        break;
      case 'thumb-60x45':
        $suffix = '60x45';
        break;
      case 'preview-100':
        $suffix = '.p100';
        break;
      default:
        throw new Exception('Unsupported transformation type!');
    }

    $path = celerity_get_resource_uri(
      "rsrc/image/icon/fatcow/thumbnails/{$prefix}{$suffix}.png");

    return id(new AphrontRedirectResponse())
      ->setURI($path);
  }

  private function buildTransformedFileResponse(
    PhabricatorTransformedFile $xform) {

    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    // TODO: We could just delegate to the file view controller instead,
    // which would save the client a roundtrip, but is slightly more complex.
    $uri = $file->getBestURI();
    return id(new AphrontRedirectResponse())->setURI($uri);
  }

  private function executePreviewTransform(PhabricatorFile $file, $size) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executePreviewTransform($file, $size);
  }

  private function executeThumbTransform(PhabricatorFile $file, $x, $y) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executeThumbTransform($file, $x, $y);
  }

}

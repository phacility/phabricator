<?php

final class PhabricatorFileTransformController
  extends PhabricatorFileController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    // NOTE: This is a public/CDN endpoint, and permission to see files is
    // controlled by knowing the secret key, not by authentication.

    $is_regenerate = $request->getBool('regenerate');

    $source_phid = $request->getURIData('phid');
    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($source_phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $secret_key = $request->getURIData('key');
    if (!$file->validateSecretKey($secret_key)) {
      return new Aphront403Response();
    }

    $transform = $request->getURIData('transform');
    $xform = id(new PhabricatorTransformedFile())
      ->loadOneWhere(
        'originalPHID = %s AND transform = %s',
        $source_phid,
        $transform);

    if ($xform) {
      if ($is_regenerate) {
        $this->destroyTransform($xform);
      } else {
        return $this->buildTransformedFileResponse($xform);
      }
    }

    $type = $file->getMimeType();

    if (!$file->isViewableInBrowser() || !$file->isTransformableImage()) {
      return $this->buildDefaultTransformation($file, $transform);
    }

    // We're essentially just building a cache here and don't need CSRF
    // protection.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $xformed_file = null;

    $xforms = PhabricatorFileTransform::getAllTransforms();
    if (isset($xforms[$transform])) {
      $xform = $xforms[$transform];
      if ($xform->canApplyTransform($file)) {
        try {
          $xformed_file = $xforms[$transform]->applyTransform($file);
        } catch (Exception $ex) {
          // In normal transform mode, we ignore failures and generate a
          // default transform below. If we're explicitly regenerating the
          // thumbnail, rethrow the exception.
          if ($is_regenerate) {
            throw $ex;
          }
        }
      }

      if (!$xformed_file) {
        $xformed_file = $xform->getDefaultTransform($file);
      }
    }

    if (!$xformed_file) {
      switch ($transform) {
        case 'thumb-profile':
          $xformed_file = $this->executeThumbTransform($file, 50, 50);
          break;
        case 'thumb-280x210':
          $xformed_file = $this->executeThumbTransform($file, 280, 210);
          break;
        case 'preview-100':
          $xformed_file = $this->executePreviewTransform($file, 100);
          break;
        case 'preview-220':
          $xformed_file = $this->executePreviewTransform($file, 220);
          break;
        default:
          return new Aphront400Response();
      }
    }

    if (!$xformed_file) {
      return new Aphront400Response();
    }

    $xform = id(new PhabricatorTransformedFile())
      ->setOriginalPHID($source_phid)
      ->setTransform($transform)
      ->setTransformedPHID($xformed_file->getPHID())
      ->save();

    return $this->buildTransformedFileResponse($xform);
  }

  private function buildDefaultTransformation(
    PhabricatorFile $file,
    $transform) {
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

    switch ($transform) {
      case 'thumb-280x210':
        $suffix = '280x210';
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

    return $file->getRedirectResponse();
  }

  private function executePreviewTransform(PhabricatorFile $file, $size) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executePreviewTransform($file, $size);
  }

  private function executeThumbTransform(PhabricatorFile $file, $x, $y) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executeThumbTransform($file, $x, $y);
  }

  private function destroyTransform(PhabricatorTransformedFile $xform) {
    $file = id(new PhabricatorFileQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    if (!$file) {
      $xform->delete();
    } else {
      $engine = new PhabricatorDestructionEngine();
      $engine->destroyObject($file);
    }

    unset($unguarded);
  }

}

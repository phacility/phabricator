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
    $xform = $this->loadTransform($source_phid, $transform);

    if ($xform) {
      if ($is_regenerate) {
        $this->destroyTransform($xform);
      } else {
        return $this->buildTransformedFileResponse($xform);
      }
    }

    $xforms = PhabricatorFileTransform::getAllTransforms();
    if (!isset($xforms[$transform])) {
      return new Aphront404Response();
    }

    $xform = $xforms[$transform];

    // We're essentially just building a cache here and don't need CSRF
    // protection.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $xformed_file = null;
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

    if (!$xformed_file) {
      return new Aphront400Response();
    }

    $xform = id(new PhabricatorTransformedFile())
      ->setOriginalPHID($source_phid)
      ->setTransform($transform)
      ->setTransformedPHID($xformed_file->getPHID());

    try {
      $xform->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      // If we collide when saving, we've raced another endpoint which was
      // transforming the same file. Just throw our work away and use that
      // transform instead.
      $this->destroyTransform($xform);
      $xform = $this->loadTransform($source_phid, $transform);
      if (!$xform) {
        return new Aphront404Response();
      }
    }

    return $this->buildTransformedFileResponse($xform);
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

  private function destroyTransform(PhabricatorTransformedFile $xform) {
    $engine = new PhabricatorDestructionEngine();
    $file = id(new PhabricatorFileQuery())
      ->setViewer($engine->getViewer())
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    if (!$file) {
      if ($xform->getID()) {
        $xform->delete();
      }
    } else {
      $engine->destroyObject($file);
    }

    unset($unguarded);
  }

  private function loadTransform($source_phid, $transform) {
    return id(new PhabricatorTransformedFile())->loadOneWhere(
      'originalPHID = %s AND transform = %s',
      $source_phid,
      $transform);
  }

}

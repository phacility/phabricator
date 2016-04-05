<?php

final class PhragmentPatchController extends PhragmentController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $aid = $request->getURIData('aid');
    $bid = $request->getURIData('bid');

    // If "aid" is "x", then it means the user wants to generate
    // a patch of an empty file to the version specified by "bid".

    $ids = array($aid, $bid);
    if ($aid === 'x') {
      $ids = array($bid);
    }

    $versions = id(new PhragmentFragmentVersionQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    $version_a = null;
    if ($aid !== 'x') {
      $version_a = idx($versions, $aid, null);
      if ($version_a === null) {
        return new Aphront404Response();
      }
    }

    $version_b = idx($versions, $bid, null);
    if ($version_b === null) {
      return new Aphront404Response();
    }

    $file_phids = array();
    if ($version_a !== null) {
      $file_phids[] = $version_a->getFilePHID();
    }
    $file_phids[] = $version_b->getFilePHID();

    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs($file_phids)
      ->execute();
    $files = mpull($files, null, 'getPHID');

    $file_a = null;
    if ($version_a != null) {
      $file_a = idx($files, $version_a->getFilePHID(), null);
    }
    $file_b = idx($files, $version_b->getFilePHID(), null);

    $patch = PhragmentPatchUtil::calculatePatch($file_a, $file_b);

    if ($patch === null) {
      // There are no differences between the two files, so we output
      // an empty patch.
      $patch = '';
    }

    $a_sequence = 'x';
    if ($version_a !== null) {
      $a_sequence = $version_a->getSequence();
    }

    $name =
      $version_b->getFragment()->getName().'.'.
      $a_sequence.'.'.
      $version_b->getSequence().'.patch';

    $return = $version_b->getURI();
    if ($request->getExists('return')) {
      $return = $request->getStr('return');
    }

    $result = PhabricatorFile::buildFromFileDataOrHash(
      $patch,
      array(
        'name' => $name,
        'mime-type' => 'text/plain',
        'ttl' => time() + 60 * 60 * 24,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $result->attachToObject($version_b->getFragmentPHID());
    unset($unguarded);

    return id(new AphrontRedirectResponse())
      ->setURI($result->getDownloadURI($return));
  }

}

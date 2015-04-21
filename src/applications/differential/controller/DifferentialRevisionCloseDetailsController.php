<?php

final class DifferentialRevisionCloseDetailsController
  extends DifferentialController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = idx($data, 'phid');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $viewer = $request->getUser();
    $xaction_phid = $this->phid;

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($xaction_phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    $obj_phid = $xaction->getObjectPHID();
    $obj_handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($obj_phid))
      ->executeOne();

    $body = $this->getRevisionMatchExplanation(
      $xaction->getMetadataValue('revisionMatchData'),
      $obj_handle);

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Commit Close Explanation'))
      ->appendParagraph($body)
      ->addCancelButton($obj_handle->getURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function getRevisionMatchExplanation(
    $revision_match_data,
    PhabricatorObjectHandle $obj_handle) {

    if (!$revision_match_data) {
      return pht(
        'This commit was made before this feature was built and thus this '.
        'information is unavailable.');
    }

    $body_why = array();
    if ($revision_match_data['usedURI']) {
      return pht(
        'We found a "Differential Revision" field with value "%s" in the '.
        'commit message, and the domain on the URI matches this install, so '.
        'we linked this commit to %s.',
        $revision_match_data['foundURI'],
        phutil_tag(
          'a',
          array(
            'href' => $obj_handle->getURI(),
          ),
          $obj_handle->getName()));
    } else if ($revision_match_data['foundURI']) {
      $body_why[] = pht(
        'We found a "Differential Revision" field with value "%s" in the '.
        'commit message, but the domain on this URI did not match the '.
        'configured domain for this install, "%s", so we ignored it under '.
        'the assumption that it refers to some third-party revision.',
        $revision_match_data['foundURI'],
        $revision_match_data['validDomain']);
    } else {
      $body_why[] = pht(
        'We didn\'t find a "Differential Revision" field in the commit '.
        'message.');
    }

    switch ($revision_match_data['matchHashType']) {
      case ArcanistDifferentialRevisionHash::HASH_GIT_TREE:
        $hash_info = true;
        $hash_type = 'tree';
        break;
      case ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT:
      case ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT:
        $hash_info = true;
        $hash_type = 'commit';
        break;
      default:
        $hash_info = false;
        break;
    }
    if ($hash_info) {
      $diff_link = phutil_tag(
        'a',
        array(
          'href' => $obj_handle->getURI(),
        ),
        $obj_handle->getName());
      $body_why = pht(
        'This commit and the active diff of %s had the same %s hash '.
        '(%s) so we linked this commit to %s.',
        $diff_link,
        $hash_type,
        $revision_match_data['matchHashValue'],
        $diff_link);
    }

    return phutil_implode_html("\n", $body_why);

  }
}

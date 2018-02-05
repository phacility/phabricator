<?php

final class PhabricatorViewerMailStamp
  extends PhabricatorMailStamp {

  const STAMPTYPE = 'viewer';

  public function renderStamps($value) {
    // If we're sending one mail to everyone, we never include viewer-based
    // stamps since they'll only be accurate for one recipient. Recipients
    // can still use the corresponding stamps with their usernames or PHIDs.
    if (!PhabricatorMetaMTAMail::shouldMailEachRecipient()) {
      return null;
    }

    $viewer_phid = $this->getViewer()->getPHID();
    if (!$viewer_phid) {
      return null;
    }

    if (!$value) {
      return null;
    }

    $value = (array)$value;
    $value = array_fuse($value);

    if (!isset($value[$viewer_phid])) {
      return null;
    }

    return $this->renderStamp($this->getKey());
  }

}

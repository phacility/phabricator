<?php

final class ManiphestNameIndexEventListener extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_SEARCH_DIDUPDATEINDEX);
  }

  public function handleEvent(PhutilEvent $event) {
    $phid = $event->getValue('phid');
    $type = phid_get_type($phid);

    // For now, we only index projects.
    if ($type != PhabricatorProjectProjectPHIDType::TYPECONST) {
      return;
    }

    $document = $event->getValue('document');

    ManiphestNameIndex::updateIndex(
      $phid,
      $document->getDocumentTitle());
  }

}

<?php

final class PhabricatorUserSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PhabricatorUser();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $user = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($user->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_USER);
    $doc->setDocumentTitle($user->getUserName().' ('.$user->getRealName().')');
    $doc->setDocumentCreated($user->getDateCreated());
    $doc->setDocumentModified($user->getDateModified());

    // TODO: Index the blurbs from their profile or something? Probably not
    // actually useful...

    if (!$user->getIsDisabled()) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $user->getPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        time());
    }

    return $doc;
  }
}

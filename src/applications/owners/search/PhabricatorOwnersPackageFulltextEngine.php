<?php

final class PhabricatorOwnersPackageFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $package = $object;
    $document->setDocumentTitle($package->getName());

    // TODO: These are bogus, but not currently stored on packages.
    $document->setDocumentCreated(PhabricatorTime::getNow());
    $document->setDocumentModified(PhabricatorTime::getNow());

    $document->addRelationship(
      $package->isArchived()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $package->getPHID(),
      PhabricatorOwnersPackagePHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}

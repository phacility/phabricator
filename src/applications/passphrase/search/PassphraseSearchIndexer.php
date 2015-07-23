<?php

final class PassphraseSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PassphraseCredential();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $credential = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($credential->getPHID());
    $doc->setDocumentType(PassphraseCredentialPHIDType::TYPECONST);
    $doc->setDocumentTitle($credential->getName());
    $doc->setDocumentCreated($credential->getDateCreated());
    $doc->setDocumentModified($credential->getDateModified());

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $credential->getDescription());

    $doc->addRelationship(
      $credential->getIsDestroyed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $credential->getPHID(),
      PassphraseCredentialPHIDType::TYPECONST,
      time());

    $this->indexTransactions(
      $doc,
      new PassphraseCredentialTransactionQuery(),
      array($phid));

    return $doc;
  }

}

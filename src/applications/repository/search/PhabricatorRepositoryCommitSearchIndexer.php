<?php

final class PhabricatorRepositoryCommitSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PhabricatorRepositoryCommit();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $commit = $this->loadDocumentByPHID($phid);

    $commit_data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    $date_created = $commit->getEpoch();
    $commit_message = $commit_data->getCommitMessage();
    $author_phid = $commit_data->getCommitDetail('authorPHID');

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withIDs(array($commit->getRepositoryID()))
      ->executeOne();
    if (!$repository) {
      throw new Exception(pht('No such repository!'));
    }

    $title = 'r'.$repository->getCallsign().$commit->getCommitIdentifier().
      ' '.$commit_data->getSummary();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($commit->getPHID());
    $doc->setDocumentType(PhabricatorRepositoryCommitPHIDType::TYPECONST);
    $doc->setDocumentCreated($date_created);
    $doc->setDocumentModified($date_created);
    $doc->setDocumentTitle($title);

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $commit_message);

    if ($author_phid) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
        $author_phid,
        PhabricatorPeopleUserPHIDType::TYPECONST,
        $date_created);
    }

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $repository->getPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      $date_created);

    $this->indexTransactions(
      $doc,
      new PhabricatorAuditTransactionQuery(),
      array($commit->getPHID()));

    return $doc;
  }
}

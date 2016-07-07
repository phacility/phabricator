<?php

final class DiffusionCommitFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($object->getPHID()))
      ->needCommitData(true)
      ->executeOne();

    $repository = $commit->getRepository();
    $commit_data = $commit->getCommitData();

    $date_created = $commit->getEpoch();
    $commit_message = $commit_data->getCommitMessage();
    $author_phid = $commit_data->getCommitDetail('authorPHID');

    $monogram = $commit->getMonogram();
    $summary = $commit_data->getSummary();

    $title = "{$monogram} {$summary}";

    $document
      ->setDocumentCreated($date_created)
      ->setDocumentModified($date_created)
      ->setDocumentTitle($title);

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $commit_message);

    if ($author_phid) {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
        $author_phid,
        PhabricatorPeopleUserPHIDType::TYPECONST,
        $date_created);
    }

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $repository->getPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      $date_created);

    $document->addRelationship(
      $commit->isUnreachable()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $commit->getPHID(),
      PhabricatorRepositoryCommitPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }
}

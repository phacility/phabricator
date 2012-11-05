<?php

/**
 * @group search
 */
final class PhabricatorSearchCommitIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexCommit(PhabricatorRepositoryCommit $commit) {
    $commit_data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    $date_created = $commit->getEpoch();
    $commit_message = $commit_data->getCommitMessage();
    $author_phid = $commit_data->getCommitDetail('authorPHID');

    $repository = id(new PhabricatorRepository())->loadOneWhere(
      'id = %d',
      $commit->getRepositoryID());

    if (!$repository) {
      return;
    }

    $title = 'r'.$repository->getCallsign().$commit->getCommitIdentifier().
      " ".$commit_data->getSummary();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($commit->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_CMIT);
    $doc->setDocumentCreated($date_created);
    $doc->setDocumentModified($date_created);
    $doc->setDocumentTitle($title);

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $commit_message);

    if ($author_phid) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
        $author_phid,
        PhabricatorPHIDConstants::PHID_TYPE_USER,
        $date_created);
    }

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $commit->getPHID(),
      PhabricatorEdgeConfig::TYPE_COMMIT_HAS_PROJECT
    );
    if ($project_phids) {
      foreach ($project_phids as $project_phid) {
        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_PROJECT,
          $project_phid,
          PhabricatorPHIDConstants::PHID_TYPE_PROJ,
          $date_created);
      }
    }

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $repository->getPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_REPO,
      $date_created);

    $comments = id(new PhabricatorAuditComment())->loadAllWhere(
      'targetPHID = %s',
      $commit->getPHID());
    foreach ($comments as $comment) {
      if (strlen($comment->getContent())) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $comment->getContent());
      }
    }

    self::reindexAbstractDocument($doc);
  }
}


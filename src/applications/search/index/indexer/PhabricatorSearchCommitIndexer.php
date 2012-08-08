<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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


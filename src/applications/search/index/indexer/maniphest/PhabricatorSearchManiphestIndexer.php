<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorSearchManiphestIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexTask(ManiphestTask $task) {
    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($task->getPHID());
    $doc->setDocumentType('TASK');
    $doc->setDocumentTitle($task->getTitle());
    $doc->setDocumentCreated($task->getDateCreated());
    $doc->setDocumentModified($task->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $task->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $task->getAuthorPHID());

    PhabricatorSearchDocument::reindexAbstractDocument($doc);
  }
}

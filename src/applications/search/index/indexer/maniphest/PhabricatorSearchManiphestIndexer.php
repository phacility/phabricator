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
      $task->getAuthorPHID(),
      'USER',
      $task->getDateCreated());

    if ($task->getStatus() == ManiphestTaskStatus::STATUS_OPEN) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $task->getPHID(),
        'TASK',
        time());
    }

    $transactions = id(new ManiphestTransaction())->loadAllWhere(
      'taskID = %d',
      $task->getID());

    $current_ccs = $task->getCCPHIDs();
    $touches = array();
    $owner = null;
    $ccs = array();
    foreach ($transactions as $transaction) {
      if ($transaction->hasComments()) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $transaction->getComments());
      }

      $author = $transaction->getAuthorPHID();

      // Record the most recent time they touched this object.
      $touches[$author] = $transaction->getDateCreated();

      switch ($transaction->getTransactionType()) {
        case ManiphestTransactionType::TYPE_OWNER:
          $owner = $transaction;
          break;
        case ManiphestTransactionType::TYPE_CCS:
          // For users who are still CC'd, record the first time they were
          // added to CC.
          foreach ($transaction->getNewValue() as $added_cc) {
            if (in_array($added_cc, $current_ccs)) {
              if (empty($ccs[$added_cc])) {
                $ccs[$added_cc] = $transaction->getDateCreated();

                // CCs count as touches, even if you didn't technically
                // interact with the object directly.
                $touches[$added_cc] = $transaction->getDateCreated();
              }
            }
          }
          break;
      }
    }

    if ($owner && $owner->getNewValue()) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $owner->getNewValue(),
        'USER',
        $owner->getDateCreated());
    }

    foreach ($touches as $touch => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_TOUCH,
        $touch,
        'USER',
        $time);
    }

    // We need to load handles here since non-users may subscribe (mailing
    // lists, e.g.)
    $handles = id(new PhabricatorObjectHandleData(array_keys($ccs)))
      ->loadHandles();
    foreach ($ccs as $cc => $time) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $handles[$cc]->getPHID(),
        $handles[$cc]->getType(),
        $time);
    }

    PhabricatorSearchDocument::reindexAbstractDocument($doc);
  }
}

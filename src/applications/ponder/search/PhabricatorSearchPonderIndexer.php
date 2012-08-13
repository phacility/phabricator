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

final class PhabricatorSearchPonderIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexQuestion(PonderQuestion $question) {

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($question->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_QUES);
    $doc->setDocumentTitle($question->getTitle());
    $doc->setDocumentCreated($question->getDateCreated());
    $doc->setDocumentModified($question->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $question->getContent());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $question->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $question->getDateCreated());

    $answers = $question->getAnswers();
    $touches = array();
    foreach ($answers as $comment) {
      if (strlen($comment->getContent())) {
        $doc->addField(
          PhabricatorSearchField::FIELD_COMMENT,
          $comment->getContent());
      }

      $author = $comment->getAuthorPHID();
      $touches[$author] = $comment->getDateCreated();
    }

    self::reindexAbstractDocument($doc);
  }
}

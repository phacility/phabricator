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

/**
 * @group search
 */
class PhabricatorSearchPhrictionIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexDocument(PhrictionDocument $document) {
    $content = $document->getContent();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($document->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_WIKI);
    $doc->setDocumentTitle($content->getTitle());

    // TODO: This isn't precisely correct, denormalize into the Document table?
    $doc->setDocumentCreated($content->getDateCreated());
    $doc->setDocumentModified($content->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $content->getContent());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $content->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $content->getDateCreated());

    self::reindexAbstractDocument($doc);
  }
}

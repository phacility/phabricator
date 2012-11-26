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
 * @group pholio
 */
final class PholioIndexer extends PhabricatorSearchDocumentIndexer {

  public static function indexMock(PholioMock $mock) {
    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($mock->getPHID());
    $doc->setDocumentType(phid_get_type($mock->getPHID()));
    $doc->setDocumentTitle($mock->getName());
    $doc->setDocumentCreated($mock->getDateCreated());
    $doc->setDocumentModified($mock->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $mock->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $mock->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $mock->getDateCreated());

    self::reindexAbstractDocument($doc);
  }
}

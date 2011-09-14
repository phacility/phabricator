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
class PhabricatorSearchUserIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexUser(PhabricatorUser $user) {
    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($user->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_USER);
    $doc->setDocumentTitle($user->getUserName().'('.$user->getRealName().')');
    $doc->setDocumentCreated($user->getDateCreated());
    $doc->setDocumentModified($user->getDateModified());

    // TODO: Index the blurbs from their profile or something? Probably not
    // actually useful...

    self::reindexAbstractDocument($doc);
  }
}

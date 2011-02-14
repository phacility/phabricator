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

class PhabricatorSearchDocument extends PhabricatorSearchDAO {

  protected $phid;
  protected $documentType;
  protected $documentTitle;
  protected $documentCreated;
  protected $documentModified;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_IDS        => self::IDS_MANUAL,
    ) + parent::getConfiguration();
  }

  public function getIDKey() {
    return 'phid';
  }

  public static function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    $phid = $doc->getPHID();
    if (!$phid) {
      throw new Exception("Document has no PHID!");
    }

    $store = new PhabricatorSearchDocument();
    $store->setPHID($doc->getPHID());
    $store->setDocumentType($doc->getDocumentType());
    $store->setDocumentTitle($doc->getDocumentTitle());
    $store->setDocumentCreated($doc->getDocumentCreated());
    $store->setDocumentModified($doc->getDocumentModified());
    $store->replace();

    $conn_w = $store->establishConnection('w');

    $field_dao = new PhabricatorSearchDocumentField();
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE phid = %s',
      $field_dao->getTableName(),
      $phid);
    foreach ($doc->getFieldData() as $field) {
      list($ftype, $corpus, $aux_phid) = $field;
      queryfx(
        $conn_w,
        'INSERT INTO %T (phid, field, auxPHId, corpus) '.
        ' VALUES (%s, %s, %ns, %s)',
        $field_dao->getTableName(),
        $phid,
        $ftype,
        $aux_phid,
        $corpus);
    }


    $sql = array();
    foreach ($doc->getRelationshipData() as $relationship) {
      list($rtype, $toPHID) = $relationship;
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s)',
        $phid,
        $toPHID,
        $rtype);
    }

    $rship_dao = new PhabricatorSearchDocumentRelationship();
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE phid = %s',
      $rship_dao->getTableName(),
      $phid);
    if ($sql) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (phid, relatedPHID, relation) '.
        ' VALUES %Q',
        $rship_dao->getTableName(),
        implode(', ', $sql));
    }

  }

}

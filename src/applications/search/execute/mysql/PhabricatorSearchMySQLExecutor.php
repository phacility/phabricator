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

class PhabricatorSearchMySQLExecutor extends PhabricatorSearchExecutor {

  /**
   * Rebuild the PhabricatorSearchAbstractDocument that was used to index
   * an object out of the index itself. This is primarily useful for debugging,
   * as it allows you to inspect the search index representation of a
   * document.
   *
   * @param  phid PHID of a document which exists in the search index.
   * @return null|PhabricatorSearchAbstractDocument Abstract document object
   *           which corresponds to the original abstract document used to
   *           build the document index.
   */
  public function reconstructDocument($phid) {
    $dao_doc = new PhabricatorSearchDocument();
    $dao_field = new PhabricatorSearchDocumentField();
    $dao_relationship = new PhabricatorSearchDocumentRelationship();

    $t_doc = $dao_doc->getTableName();
    $t_field = $dao_field->getTableName();
    $t_relationship = $dao_relationship->getTableName();

    $doc = queryfx_one(
      $dao_doc->establishConnection('r'),
      'SELECT * FROM %T WHERE phid = %s',
      $t_doc,
      $phid);

    if (!$doc) {
      return null;
    }

    $fields = queryfx_all(
      $dao_field->establishConnection('r'),
      'SELECT * FROM %T WHERE phid = %s',
      $t_field,
      $phid);

    $relationships = queryfx_all(
      $dao_relationship->establishConnection('r'),
      'SELECT * FROM %T WHERE phid = %s',
      $t_relationship,
      $phid);

    $adoc = id(new PhabricatorSearchAbstractDocument())
      ->setPHID($phid)
      ->setDocumentType($doc['documentType'])
      ->setDocumentTitle($doc['documentTitle'])
      ->setDocumentCreated($doc['documentCreated'])
      ->setDocumentModified($doc['documentModified']);

    foreach ($fields as $field) {
      $adoc->addField(
        $field['field'],
        $field['corpus'],
        $field['auxPHID']);
    }

    foreach ($relationships as $relationship) {
      $adoc->addRelationship(
        $relationship['relation'],
        $relationship['relatedPHID'],
        $relationship['relatedType'],
        $relationship['relatedTime']);
    }

    return $adoc;
  }

  public function executeSearch(PhabricatorSearchQuery $query) {

    $where = array();
    $join  = array();
    $order = 'ORDER BY documentCreated DESC';

    $dao_doc = new PhabricatorSearchDocument();
    $dao_field = new PhabricatorSearchDocumentField();

    $t_doc   = $dao_doc->getTableName();
    $t_field = $dao_field->getTableName();

    $conn_r = $dao_doc->establishConnection('r');

    $q = $query->getQuery();

    if (strlen($q)) {
     $join[] = qsprintf(
        $conn_r,
        "{$t_field} field ON field.phid = document.phid");
      $where[] = qsprintf(
        $conn_r,
        'MATCH(corpus) AGAINST (%s)',
        $q);

//      if ($query->getParameter('order') == AdjutantQuery::ORDER_RELEVANCE) {
        $order = qsprintf(
          $conn_r,
          'ORDER BY MAX(MATCH(corpus) AGAINST (%s)) DESC',
          $q);
//      }

      $field = $query->getParameter('field');
      if ($field/* && $field != AdjutantQuery::FIELD_ALL*/) {
        $where[] = qsprintf(
          $conn_r,
          'field.field = %s',
          $field);
      }
    }

    if ($query->getParameter('type')) {
      if (strlen($q)) {
        // TODO: verify that this column actually does something useful in query
        // plans once we have nontrivial amounts of data.
        $where[] = qsprintf(
          $conn_r,
          'field.phidType = %s',
          $query->getParameter('type'));
      }
      $where[] = qsprintf(
        $conn_r,
        'document.documentType = %s',
        $query->getParameter('type'));
    }

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'author',
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'open',
      PhabricatorSearchRelationship::RELATIONSHIP_OPEN);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'owner',
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'project',
      PhabricatorSearchRelationship::RELATIONSHIP_PROJECT);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'repository',
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY);

/*
    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'reviewer',
      AdjutantRelationship::RELATIONSHIP_REVIEWER);
    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'subscriber',
      AdjutantRelationship::RELATIONSHIP_SUBSCRIBER);
    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'repository',
      AdjutantRelationship::RELATIONSHIP_REPOSITORY);
*/
    $join = array_filter($join);

    foreach ($join as $key => $clause) {
      $join[$key] = ' JOIN '.$clause;
    }
    $join = implode(' ', $join);

    if ($where) {
      $where = 'WHERE '.implode(' AND ', $where);
    } else {
      $where = '';
    }

    $hits = queryfx_all(
      $conn_r,
      'SELECT
          document.phid,
          document.documentType,
          document.documentTitle,
          document.documentCreated
        FROM %T document
          %Q
          %Q
        GROUP BY document.phid
          %Q
        LIMIT 50',
      $t_doc,
      $join,
      $where,
      $order);

    return $hits;
  }

  protected function joinRelationship($conn, $query, $field, $type) {
    $phids = $query->getParameter($field, array());
    if (!$phids) {
      return null;
    }

    $is_existence = false;
    switch ($type) {
      case PhabricatorSearchRelationship::RELATIONSHIP_OPEN:
        $is_existence = true;
        break;
    }

    $sql = qsprintf(
      $conn,
      '%T AS %C ON %C.phid = document.phid AND %C.relation = %s',
      id(new PhabricatorSearchDocumentRelationship())->getTableName(),
      $field,
      $field,
      $field,
      $type);

    if (!$is_existence) {
      $sql .= qsprintf(
        $conn,
        ' AND %C.relatedPHID in (%Ls)',
        $field,
        $phids);
    }

    return $sql;
  }


}

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
/*
      if ($query->getParameter('order') == AdjutantQuery::ORDER_RELEVANCE) {
        $order = qsprintf(
          $conn_r,
          'ORDER BY MATCH(corpus) AGAINST (%s) DESC',
          $q);
      }
*/
      $field = $query->getParameter('field');
      if ($field/* && $field != AdjutantQuery::FIELD_ALL*/) {
        $where[] = qsprintf(
          $conn_r,
          'field.field = %s',
          $field);
      }
    }

    if ($query->getParameter('type')) {
      $where[] = qsprintf(
        $conn_r,
        'document.documentType = %s',
        $query->getParameter('type'));
    }

/*
    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'author',
      AdjutantRelationship::RELATIONSHIP_AUTHOR);
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
      'SELECT DISTINCT
          document.phid,
          document.documentType,
          document.documentCreated FROM %T document %Q %Q %Q
        LIMIT 50',
      $t_doc,
      $join,
      $where,
      $order);

    return $hits;
  }

  protected function joinRelationship($conn, $query, $field, $type) {
    $fbids = $query->getParameter($field, array());
    if (!$fbids) {
      return null;
    }
    return qsprintf(
      $conn,
      'relationship AS %C ON %C.fbid = data.fbid AND %C.relation = %s
        AND %C.relatedFBID in (%Ld)',
      $field,
      $field,
      $field,
      $type,
      $field,
      $fbids);
  }


}

<?php

final class PhabricatorMySQLFulltextStorageEngine
  extends PhabricatorFulltextStorageEngine {

  public function getEngineIdentifier() {
    return 'mysql';
  }

  public function getEnginePriority() {
    return 100;
  }

  public function isEnabled() {
    return true;
  }

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    $phid = $doc->getPHID();
    if (!$phid) {
      throw new Exception(pht('Document has no PHID!'));
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
        'INSERT INTO %T (phid, phidType, field, auxPHID, corpus) '.
        'VALUES (%s, %s, %s, %ns, %s)',
        $field_dao->getTableName(),
        $phid,
        $doc->getDocumentType(),
        $ftype,
        $aux_phid,
        $corpus);
    }


    $sql = array();
    foreach ($doc->getRelationshipData() as $relationship) {
      list($rtype, $to_phid, $to_type, $time) = $relationship;
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s, %s, %d)',
        $phid,
        $to_phid,
        $rtype,
        $to_type,
        $time);
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
        'INSERT INTO %T '.
        '(phid, relatedPHID, relation, relatedType, relatedTime) '.
        'VALUES %Q',
        $rship_dao->getTableName(),
        implode(', ', $sql));
    }

  }

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

  public function executeSearch(PhabricatorSavedQuery $query) {
    $where = array();
    $join  = array();
    $order = 'ORDER BY documentCreated DESC';

    $dao_doc = new PhabricatorSearchDocument();
    $dao_field = new PhabricatorSearchDocumentField();

    $t_doc   = $dao_doc->getTableName();
    $t_field = $dao_field->getTableName();

    $conn_r = $dao_doc->establishConnection('r');

    $q = $query->getParameter('query');

    if (strlen($q)) {
     $join[] = qsprintf(
        $conn_r,
        '%T field ON field.phid = document.phid',
        $t_field);
      $where[] = qsprintf(
        $conn_r,
        'MATCH(corpus) AGAINST (%s IN BOOLEAN MODE)',
        $q);

      // When searching for a string, promote user listings above other
      // listings.
      $order = qsprintf(
        $conn_r,
        'ORDER BY
          IF(documentType = %s, 0, 1) ASC,
          MAX(MATCH(corpus) AGAINST (%s)) DESC',
        'USER',
        $q);

      $field = $query->getParameter('field');
      if ($field) {
        $where[] = qsprintf(
          $conn_r,
          'field.field = %s',
          $field);
      }
    }

    $exclude = $query->getParameter('exclude');
    if ($exclude) {
      $where[] = qsprintf($conn_r, 'document.phid != %s', $exclude);
    }

    $types = $query->getParameter('types');
    if ($types) {
      if (strlen($q)) {
        $where[] = qsprintf(
          $conn_r,
          'field.phidType IN (%Ls)',
          $types);
      }
      $where[] = qsprintf(
        $conn_r,
        'document.documentType IN (%Ls)',
        $types);
    }

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'authorPHIDs',
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR);

    $statuses = $query->getParameter('statuses', array());
    $statuses = array_fuse($statuses);
    $open_rel = PhabricatorSearchRelationship::RELATIONSHIP_OPEN;
    $closed_rel = PhabricatorSearchRelationship::RELATIONSHIP_CLOSED;
    $include_open = !empty($statuses[$open_rel]);
    $include_closed = !empty($statuses[$closed_rel]);

    if ($include_open && !$include_closed) {
      $join[] = $this->joinRelationship(
        $conn_r,
        $query,
        'statuses',
        $open_rel,
        true);
    } else if ($include_closed && !$include_open) {
      $join[] = $this->joinRelationship(
        $conn_r,
        $query,
        'statuses',
        $closed_rel,
        true);
    }

    if ($query->getParameter('withAnyOwner')) {
      $join[] = $this->joinRelationship(
        $conn_r,
        $query,
        'withAnyOwner',
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        true);
    } else if ($query->getParameter('withUnowned')) {
      $join[] = $this->joinRelationship(
        $conn_r,
        $query,
        'withUnowned',
        PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED,
        true);
    } else {
      $join[] = $this->joinRelationship(
        $conn_r,
        $query,
        'ownerPHIDs',
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER);
    }

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'subscriberPHIDs',
      PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'projectPHIDs',
      PhabricatorSearchRelationship::RELATIONSHIP_PROJECT);

    $join[] = $this->joinRelationship(
      $conn_r,
      $query,
      'repository',
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY);

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

    $offset = (int)$query->getParameter('offset', 0);
    $limit  = (int)$query->getParameter('limit', 25);

    $hits = queryfx_all(
      $conn_r,
      'SELECT
        document.phid
        FROM %T document
          %Q
          %Q
        GROUP BY document.phid
          %Q
        LIMIT %d, %d',
      $t_doc,
      $join,
      $where,
      $order,
      $offset,
      $limit);

    return ipull($hits, 'phid');
  }

  protected function joinRelationship(
    AphrontDatabaseConnection $conn,
    PhabricatorSavedQuery $query,
    $field,
    $type,
    $is_existence = false) {

    $sql = qsprintf(
      $conn,
      '%T AS %C ON %C.phid = document.phid AND %C.relation = %s',
      id(new PhabricatorSearchDocumentRelationship())->getTableName(),
      $field,
      $field,
      $field,
      $type);

    if (!$is_existence) {
      $phids = $query->getParameter($field, array());
      if (!$phids) {
        return null;
      }
      $sql .= qsprintf(
        $conn,
        ' AND %C.relatedPHID in (%Ls)',
        $field,
        $phids);
    }

    return $sql;
  }

  public function indexExists() {
    return true;
  }
}

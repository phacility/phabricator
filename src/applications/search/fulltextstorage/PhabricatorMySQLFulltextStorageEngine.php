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

    $stemmer = new PhutilSearchStemmer();

    $field_dao = new PhabricatorSearchDocumentField();
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE phid = %s',
      $field_dao->getTableName(),
      $phid);
    foreach ($doc->getFieldData() as $field) {
      list($ftype, $corpus, $aux_phid) = $field;

      $stemmed_corpus = $stemmer->stemCorpus($corpus);

      queryfx(
        $conn_w,
        'INSERT INTO %T
          (phid, phidType, field, auxPHID, corpus, stemmedCorpus) '.
        'VALUES (%s, %s, %s, %ns, %s, %s)',
        $field_dao->getTableName(),
        $phid,
        $doc->getDocumentType(),
        $ftype,
        $aux_phid,
        $corpus,
        $stemmed_corpus);
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
    $table = new PhabricatorSearchDocument();
    $document_table = $table->getTableName();
    $conn = $table->establishConnection('r');

    $subquery = $this->newFulltextSubquery($query, $conn);

    $offset = (int)$query->getParameter('offset', 0);
    $limit  = (int)$query->getParameter('limit', 25);

    // NOTE: We must JOIN the subquery in order to apply a limit.
    $results = queryfx_all(
      $conn,
      'SELECT
        documentPHID,
        MAX(fieldScore) AS documentScore
        FROM (%Q) query
        JOIN %T root ON query.documentPHID = root.phid
        GROUP BY documentPHID
        ORDER BY documentScore DESC
        LIMIT %d, %d',
      $subquery,
      $document_table,
      $offset,
      $limit);

    return ipull($results, 'documentPHID');
  }

  private function newFulltextSubquery(
    PhabricatorSavedQuery $query,
    AphrontDatabaseConnection $conn) {

    $field = new PhabricatorSearchDocumentField();
    $field_table = $field->getTableName();

    $document = new PhabricatorSearchDocument();
    $document_table = $document->getTableName();

    $select = array();
    $select[] = 'document.phid AS documentPHID';

    $join = array();
    $where = array();

    $title_field = PhabricatorSearchDocumentFieldType::FIELD_TITLE;
    $title_boost = 1024;

    $raw_query = $query->getParameter('query');
    $compiled_query = $this->compileQuery($raw_query);
    if (strlen($compiled_query)) {
      $select[] = qsprintf(
        $conn,
        'IF(field.field = %s, %d, 0) +
          MATCH(corpus, stemmedCorpus) AGAINST (%s IN BOOLEAN MODE)
            AS fieldScore',
        $title_field,
        $title_boost,
        $compiled_query);

      $join[] = qsprintf(
        $conn,
        '%T field ON field.phid = document.phid',
        $field_table);

      $where[] = qsprintf(
        $conn,
        'MATCH(corpus, stemmedCorpus) AGAINST (%s IN BOOLEAN MODE)',
        $compiled_query);

      if ($query->getParameter('field')) {
        $where[] = qsprintf(
          $conn,
          'field.field = %s',
          $field);
      }
    } else {
      $select[] = qsprintf(
        $conn,
        'document.documentCreated AS fieldScore');
    }

    $exclude = $query->getParameter('exclude');
    if ($exclude) {
      $where[] = qsprintf(
        $conn,
        'document.phid != %s',
        $exclude);
    }

    $types = $query->getParameter('types');
    if ($types) {
      if (strlen($compiled_query)) {
        $where[] = qsprintf(
          $conn,
          'field.phidType IN (%Ls)',
          $types);
      }

      $where[] = qsprintf(
        $conn,
        'document.documentType IN (%Ls)',
        $types);
    }

    $join[] = $this->joinRelationship(
      $conn,
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
        $conn,
        $query,
        'statuses',
        $open_rel,
        true);
    } else if ($include_closed && !$include_open) {
      $join[] = $this->joinRelationship(
        $conn,
        $query,
        'statuses',
        $closed_rel,
        true);
    }

    if ($query->getParameter('withAnyOwner')) {
      $join[] = $this->joinRelationship(
        $conn,
        $query,
        'withAnyOwner',
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        true);
    } else if ($query->getParameter('withUnowned')) {
      $join[] = $this->joinRelationship(
        $conn,
        $query,
        'withUnowned',
        PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED,
        true);
    } else {
      $join[] = $this->joinRelationship(
        $conn,
        $query,
        'ownerPHIDs',
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER);
    }

    $join[] = $this->joinRelationship(
      $conn,
      $query,
      'subscriberPHIDs',
      PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER);

    $join[] = $this->joinRelationship(
      $conn,
      $query,
      'projectPHIDs',
      PhabricatorSearchRelationship::RELATIONSHIP_PROJECT);

    $join[] = $this->joinRelationship(
      $conn,
      $query,
      'repository',
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY);

    $select = implode(', ', $select);

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

    if (strlen($compiled_query)) {
      $order = '';
    } else {
      // When not executing a query, order by document creation date. This
      // is the default view in object browser dialogs, like "Close Duplicate".
      $order = qsprintf(
        $conn,
        'ORDER BY document.documentCreated DESC');
    }

    return qsprintf(
      $conn,
      'SELECT %Q FROM %T document %Q %Q %Q LIMIT 1000',
      $select,
      $document_table,
      $join,
      $where,
      $order);
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

  private function compileQuery($raw_query) {
    $stemmer = new PhutilSearchStemmer();

    $compiler = PhabricatorSearchDocument::newQueryCompiler()
      ->setQuery($raw_query)
      ->setStemmer($stemmer);

    $queries = array();
    $queries[] = $compiler->compileLiteralQuery();
    $queries[] = $compiler->compileStemmedQuery();

    return implode(' ', array_filter($queries));
  }

  public function indexExists() {
    return true;
  }
}

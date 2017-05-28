<?php

final class PhabricatorMySQLFulltextStorageEngine
  extends PhabricatorFulltextStorageEngine {

  private $fulltextTokens = array();
  private $engineLimits;

  public function getEngineIdentifier() {
    return 'mysql';
  }

  public function getHostType() {
    return new PhabricatorMySQLSearchHost($this);
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

    $stemmer = new PhutilSearchStemmer();

    $raw_query = $query->getParameter('query');
    $raw_query = trim($raw_query);
    if (strlen($raw_query)) {
      $compiler = PhabricatorSearchDocument::newQueryCompiler()
        ->setStemmer($stemmer);

      $tokens = $compiler->newTokens($raw_query);

      list($min_length, $stopword_list) = $this->getEngineLimits($conn);

      // Process all the parts of the user's query so we can show them which
      // parts we searched for and which ones we ignored.
      $fulltext_tokens = array();
      foreach ($tokens as $key => $token) {
        $fulltext_token = id(new PhabricatorFulltextToken())
          ->setToken($token);

        $fulltext_tokens[$key] = $fulltext_token;

        $value = $token->getValue();

        // If the value is unquoted, we'll stem it in the query, so stem it
        // here before performing filtering tests. See T12596.
        if (!$token->isQuoted()) {
          $value = $stemmer->stemToken($value);
        }

        if (phutil_utf8_strlen($value) < $min_length) {
          $fulltext_token->setIsShort(true);
          continue;
        }

        if (isset($stopword_list[phutil_utf8_strtolower($value)])) {
          $fulltext_token->setIsStopword(true);
          continue;
        }
      }
      $this->fulltextTokens = $fulltext_tokens;

      // Remove tokens which aren't queryable from the query. This is mostly
      // a workaround for the peculiar behaviors described in T12137.
      foreach ($this->fulltextTokens as $key => $fulltext_token) {
        if (!$fulltext_token->isQueryable()) {
          unset($tokens[$key]);
        }
      }

      if (!$tokens) {
        throw new PhutilSearchQueryCompilerSyntaxException(
          pht(
            'All of your search terms are too short or too common to '.
            'appear in the search index. Search for longer or more '.
            'distinctive terms.'));
      }

      $queries = array();
      $queries[] = $compiler->compileLiteralQuery($tokens);
      $queries[] = $compiler->compileStemmedQuery($tokens);
      $compiled_query = implode(' ', array_filter($queries));
    } else {
      $compiled_query = null;
    }

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

  public function indexExists() {
    return true;
  }

  public function getIndexStats() {
    return false;
  }

  public function getFulltextTokens() {
    return $this->fulltextTokens;
  }

  private function getEngineLimits(AphrontDatabaseConnection $conn) {
    if ($this->engineLimits === null) {
      $this->engineLimits = $this->newEngineLimits($conn);
    }
    return $this->engineLimits;
  }

  private function newEngineLimits(AphrontDatabaseConnection $conn) {
    // First, try InnoDB. Some database may not have both table engines, so
    // selecting variables from missing table engines can fail and throw.

    try {
      $result = queryfx_one(
        $conn,
        'SELECT @@innodb_ft_min_token_size innodb_max,
          @@innodb_ft_server_stopword_table innodb_stopword_config');
    } catch (AphrontQueryException $ex) {
      $result = null;
    }

    if ($result) {
      $min_len = $result['innodb_max'];

      $stopword_config = $result['innodb_stopword_config'];
      if (preg_match('(/)', $stopword_config)) {
        // If the setting is nonempty and contains a slash, query the
        // table the user has configured.
        $parts = explode('/', $stopword_config);
        list($stopword_database, $stopword_table) = $parts;
      } else {
        // Otherwise, query the InnoDB default stopword table.
        $stopword_database = 'INFORMATION_SCHEMA';
        $stopword_table = 'INNODB_FT_DEFAULT_STOPWORD';
      }

      $stopwords = queryfx_all(
        $conn,
        'SELECT * FROM %T.%T',
        $stopword_database,
        $stopword_table);
      $stopwords = ipull($stopwords, 'value');
      $stopwords = array_fuse($stopwords);

      return array($min_len, $stopwords);
    }

    // If InnoDB fails, try MyISAM.
    $result = queryfx_one(
      $conn,
      'SELECT
        @@ft_min_word_len myisam_max,
        @@ft_stopword_file myisam_stopwords');

    $min_len = $result['myisam_max'];

    $file = $result['myisam_stopwords'];
    if (preg_match('(/resources/sql/stopwords\.txt\z)', $file)) {
      // If this is set to something that looks like the Phabricator
      // stopword file, read that.
      $file = 'stopwords.txt';
    } else {
      // Otherwise, just use the default stopwords. This might be wrong
      // but we can't read the actual value dynamically and reading
      // whatever file the variable is set to could be a big headache
      // to get right from a security perspective.
      $file = 'stopwords_myisam.txt';
    }

    $root = dirname(phutil_get_library_root('phabricator'));
    $data = Filesystem::readFile($root.'/resources/sql/'.$file);
    $stopwords = explode("\n", $data);
    $stopwords = array_filter($stopwords);
    $stopwords = array_fuse($stopwords);

    return array($min_len, $stopwords);
  }

}

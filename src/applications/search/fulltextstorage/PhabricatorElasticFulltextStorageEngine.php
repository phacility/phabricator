<?php

class PhabricatorElasticFulltextStorageEngine
  extends PhabricatorFulltextStorageEngine {

  private $index;
  private $timeout;
  private $version;

  public function setService(PhabricatorSearchService $service) {
    $this->service = $service;
    $config = $service->getConfig();
    $index = idx($config, 'path', '/phabricator');
    $this->index = str_replace('/', '', $index);
    $this->timeout = idx($config, 'timeout', 15);
    $this->version = (int)idx($config, 'version', 5);
    return $this;
  }

  public function getEngineIdentifier() {
    return 'elasticsearch';
  }

  public function getTimestampField() {
    return $this->version < 2 ?
      '_timestamp' : 'lastModified';
  }

  public function getTextFieldType() {
    return $this->version >= 5
      ? 'text' : 'string';
  }

  public function getHostType() {
    return new PhabricatorElasticsearchHost($this);
  }

  public function getHostForRead() {
    return $this->getService()->getAnyHostForRole('read');
  }

  public function getHostForWrite() {
    return $this->getService()->getAnyHostForRole('write');
  }

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function getTypeConstants($class) {
    $relationship_class = new ReflectionClass($class);
    $typeconstants = $relationship_class->getConstants();
    return array_unique(array_values($typeconstants));
  }

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    $host = $this->getHostForWrite();

    $type = $doc->getDocumentType();
    $phid = $doc->getPHID();
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();

    $timestamp_key = $this->getTimestampField();

    $spec = array(
      'title'         => $doc->getDocumentTitle(),
      'dateCreated'   => $doc->getDocumentCreated(),
      $timestamp_key  => $doc->getDocumentModified(),
    );

    foreach ($doc->getFieldData() as $field) {
      list($field_name, $corpus, $aux) = $field;
      if (!isset($spec[$field_name])) {
        $spec[$field_name] = array($corpus);
      } else {
        $spec[$field_name][] = $corpus;
      }
      if ($aux != null) {
        $spec[$field_name][] = $aux;
      }
    }

    foreach ($doc->getRelationshipData() as $field) {
      list($field_name, $related_phid, $rtype, $time) = $field;
      if (!isset($spec[$field_name])) {
        $spec[$field_name] = array($related_phid);
      } else {
        $spec[$field_name][] = $related_phid;
      }
      if ($time) {
        $spec[$field_name.'_ts'] = $time;
      }
    }

    $this->executeRequest($host, "/{$type}/{$phid}/", $spec, 'PUT');
  }

  private function buildSpec(PhabricatorSavedQuery $query) {
    $q = new PhabricatorElasticsearchQueryBuilder('bool');
    $query_string = $query->getParameter('query');
    if (strlen($query_string)) {
      $fields = $this->getTypeConstants('PhabricatorSearchDocumentFieldType');

      // Build a simple_query_string query over all fields that must match all
      // of the words in the search string.
      $q->addMustClause(array(
        'simple_query_string' => array(
          'query'  => $query_string,
          'fields' => array(
            PhabricatorSearchDocumentFieldType::FIELD_TITLE.'.*',
            PhabricatorSearchDocumentFieldType::FIELD_BODY.'.*',
            PhabricatorSearchDocumentFieldType::FIELD_COMMENT.'.*',
          ),
          'default_operator' => 'AND',
        ),
      ));

      // This second query clause is "SHOULD' so it only affects ranking of
      // documents which already matched the Must clause. This amplifies the
      // score of documents which have an exact match on title, body
      // or comments.
      $q->addShouldClause(array(
        'simple_query_string' => array(
          'query'  => $query_string,
          'fields' => array(
            '*.raw',
            PhabricatorSearchDocumentFieldType::FIELD_TITLE.'^4',
            PhabricatorSearchDocumentFieldType::FIELD_BODY.'^3',
            PhabricatorSearchDocumentFieldType::FIELD_COMMENT.'^1.2',
          ),
          'analyzer' => 'english_exact',
          'default_operator' => 'and',
        ),
      ));

    }

    $exclude = $query->getParameter('exclude');
    if ($exclude) {
      $q->addFilterClause(array(
        'not' => array(
          'ids' => array(
            'values' => array($exclude),
          ),
        ),
      ));
    }

    $relationship_map = array(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR =>
        $query->getParameter('authorPHIDs', array()),
      PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER =>
        $query->getParameter('subscriberPHIDs', array()),
      PhabricatorSearchRelationship::RELATIONSHIP_PROJECT =>
        $query->getParameter('projectPHIDs', array()),
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY =>
        $query->getParameter('repositoryPHIDs', array()),
    );

    $statuses = $query->getParameter('statuses', array());
    $statuses = array_fuse($statuses);

    $rel_open = PhabricatorSearchRelationship::RELATIONSHIP_OPEN;
    $rel_closed = PhabricatorSearchRelationship::RELATIONSHIP_CLOSED;
    $rel_unowned = PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED;

    $include_open = !empty($statuses[$rel_open]);
    $include_closed = !empty($statuses[$rel_closed]);

    if ($include_open && !$include_closed) {
      $q->addExistsClause($rel_open);
    } else if (!$include_open && $include_closed) {
      $q->addExistsClause($rel_closed);
    }

    if ($query->getParameter('withUnowned')) {
      $q->addExistsClause($rel_unowned);
    }

    $rel_owner = PhabricatorSearchRelationship::RELATIONSHIP_OWNER;
    if ($query->getParameter('withAnyOwner')) {
      $q->addExistsClause($rel_owner);
    } else {
      $owner_phids = $query->getParameter('ownerPHIDs', array());
      if (count($owner_phids)) {
        $q->addTermsClause($rel_owner, $owner_phids);
      }
    }

    foreach ($relationship_map as $field => $phids) {
      if (is_array($phids) && !empty($phids)) {
        $q->addTermsClause($field, $phids);
      }
    }

    if (!$q->getClauseCount('must')) {
      $q->addMustClause(array('match_all' => array('boost' => 1 )));
    }

    $spec = array(
      '_source' => false,
      'query' => array(
        'bool' => $q->toArray(),
      ),
    );


    if (!$query->getParameter('query')) {
      $spec['sort'] = array(
        array('dateCreated' => 'desc'),
      );
    }

    $offset = (int)$query->getParameter('offset', 0);
    $limit =  (int)$query->getParameter('limit', 101);
    if ($offset + $limit > 10000) {
      throw new Exception(pht(
        'Query offset is too large. offset+limit=%s (max=%s)',
        $offset + $limit,
        10000));
    }
    $spec['from'] = $offset;
    $spec['size'] = $limit;

    return $spec;
  }

  public function executeSearch(PhabricatorSavedQuery $query) {
    $types = $query->getParameter('types');
    if (!$types) {
      $types = array_keys(
        PhabricatorSearchApplicationSearchEngine::getIndexableDocumentTypes());
    }

    // Don't use '/_search' for the case that there is something
    // else in the index (for example if 'phabricator' is only an alias to
    // some bigger index). Use '/$types/_search' instead.
    $uri = '/'.implode(',', $types).'/_search';

    $spec = $this->buildSpec($query);
    $exceptions = array();

    foreach ($this->service->getAllHostsForRole('read') as $host) {
      try {
        $response = $this->executeRequest($host, $uri, $spec);
        $phids = ipull($response['hits']['hits'], '_id');
        return $phids;
      } catch (Exception $e) {
        $exceptions[] = $e;
      }
    }
    throw new PhutilAggregateException(pht('All Fulltext Search hosts failed:'),
      $exceptions);
  }

  public function indexExists(PhabricatorElasticsearchHost $host = null) {
    if (!$host) {
      $host = $this->getHostForRead();
    }
    try {
      if ($this->version >= 5) {
        $uri = '/_stats/';
        $res = $this->executeRequest($host, $uri, array());
        return isset($res['indices']['phabricator']);
      } else if ($this->version >= 2) {
        $uri = '';
      } else {
        $uri = '/_status/';
      }
      return (bool)$this->executeRequest($host, $uri, array());
    } catch (HTTPFutureHTTPResponseStatus $e) {
      if ($e->getStatusCode() == 404) {
        return false;
      }
      throw $e;
    }
  }

  private function getIndexConfiguration() {
    $data = array();
    $data['settings'] = array(
      'index' => array(
        'auto_expand_replicas' => '0-2',
        'analysis' => array(
          'filter' => array(
            'english_stop' => array(
              'type' => 'stop',
              'stopwords' => '_english_',
            ),
            'english_stemmer' => array(
              'type' =>       'stemmer',
              'language' =>   'english',
            ),
            'english_possessive_stemmer' => array(
              'type' =>       'stemmer',
              'language' =>   'possessive_english',
            ),
          ),
          'analyzer' => array(
            'english_exact' => array(
              'tokenizer' => 'standard',
              'filter'    => array('lowercase'),
            ),
            'letter_stop' => array(
              'tokenizer' => 'letter',
              'filter'    => array('lowercase', 'english_stop'),
            ),
            'english_stem' => array(
              'tokenizer' => 'standard',
              'filter'    => array(
                'english_possessive_stemmer',
                'lowercase',
                'english_stop',
                'english_stemmer',
              ),
            ),
          ),
        ),
      ),
    );

    $fields = $this->getTypeConstants('PhabricatorSearchDocumentFieldType');
    $relationships = $this->getTypeConstants('PhabricatorSearchRelationship');

    $doc_types = array_keys(
      PhabricatorSearchApplicationSearchEngine::getIndexableDocumentTypes());

    $text_type = $this->getTextFieldType();

    foreach ($doc_types as $type) {
      $properties = array();
      foreach ($fields as $field) {
        // Use the custom analyzer for the corpus of text
        $properties[$field] = array(
          'type'                  => $text_type,
          'fields' => array(
            'raw' => array(
              'type'                  => $text_type,
              'analyzer'              => 'english_exact',
              'search_analyzer'       => 'english',
              'search_quote_analyzer' => 'english_exact',
            ),
            'keywords' => array(
              'type'                  => $text_type,
              'analyzer'              => 'letter_stop',
            ),
            'stems' => array(
              'type'                  => $text_type,
              'analyzer'              => 'english_stem',
            ),
          ),
        );
      }

      if ($this->version < 5) {
        foreach ($relationships as $rel) {
          $properties[$rel] = array(
            'type'  => 'string',
            'index' => 'not_analyzed',
            'include_in_all' => false,
          );
          $properties[$rel.'_ts'] = array(
            'type'  => 'date',
            'include_in_all' => false,
          );
        }
      } else {
        foreach ($relationships as $rel) {
          $properties[$rel] = array(
            'type'  => 'keyword',
            'include_in_all' => false,
            'doc_values' => false,
          );
          $properties[$rel.'_ts'] = array(
            'type'  => 'date',
            'include_in_all' => false,
          );
        }
      }

      // Ensure we have dateCreated since the default query requires it
      $properties['dateCreated']['type'] = 'date';
      $properties['lastModified']['type'] = 'date';

      $data['mappings'][$type]['properties'] = $properties;
    }
    return $data;
  }

  public function indexIsSane(PhabricatorElasticsearchHost $host = null) {
    if (!$host) {
      $host = $this->getHostForRead();
    }
    if (!$this->indexExists($host)) {
      return false;
    }
    $cur_mapping = $this->executeRequest($host, '/_mapping/', array());
    $cur_settings = $this->executeRequest($host, '/_settings/', array());
    $actual = array_merge($cur_settings[$this->index],
      $cur_mapping[$this->index]);

    $res = $this->check($actual, $this->getIndexConfiguration());
    return $res;
  }

  /**
   * Recursively check if two Elasticsearch configuration arrays are equal
   *
   * @param $actual
   * @param $required array
   * @return bool
   */
  private function check($actual, $required, $path = '') {
    foreach ($required as $key => $value) {
      if (!array_key_exists($key, $actual)) {
        if ($key === '_all') {
          // The _all field never comes back so we just have to assume it
          // is set correctly.
          continue;
        }
        return false;
      }
      if (is_array($value)) {
        if (!is_array($actual[$key])) {
          return false;
        }
        if (!$this->check($actual[$key], $value, $path.'.'.$key)) {
          return false;
        }
        continue;
      }

      $actual[$key] = self::normalizeConfigValue($actual[$key]);
      $value = self::normalizeConfigValue($value);
      if ($actual[$key] != $value) {
        return false;
      }
    }
    return true;
  }

  /**
   * Normalize a config value for comparison. Elasticsearch accepts all kinds
   * of config values but it tends to throw back 'true' for true and 'false' for
   * false so we normalize everything. Sometimes, oddly, it'll throw back false
   * for false....
   *
   * @param mixed $value config value
   * @return mixed value normalized
   */
  private static function normalizeConfigValue($value) {
    if ($value === true) {
      return 'true';
    } else if ($value === false) {
      return 'false';
    }
    return $value;
  }

  public function initIndex() {
    $host = $this->getHostForWrite();
    if ($this->indexExists()) {
      $this->executeRequest($host, '/', array(), 'DELETE');
    }
    $data = $this->getIndexConfiguration();
    $this->executeRequest($host, '/', $data, 'PUT');
  }

  public function getIndexStats(PhabricatorElasticsearchHost $host = null) {
    if ($this->version < 2) {
      return false;
    }
    if (!$host) {
      $host = $this->getHostForRead();
    }
    $uri = '/_stats/';

    $res = $this->executeRequest($host, $uri, array());
    $stats = $res['indices'][$this->index];
    return array(
      pht('Queries') =>
        idxv($stats, array('primaries', 'search', 'query_total')),
      pht('Documents') =>
        idxv($stats, array('total', 'docs', 'count')),
      pht('Deleted') =>
        idxv($stats, array('total', 'docs', 'deleted')),
      pht('Storage Used') =>
        phutil_format_bytes(idxv($stats,
          array('total', 'store', 'size_in_bytes'))),
    );
  }

  private function executeRequest(PhabricatorElasticsearchHost $host, $path,
    array $data, $method = 'GET') {

    $uri = $host->getURI($path);
    $data = phutil_json_encode($data);
    $future = new HTTPSFuture($uri, $data);
    $future->addHeader('Content-Type', 'application/json');

    if ($method != 'GET') {
      $future->setMethod($method);
    }
    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }
    try {
      list($body) = $future->resolvex();
    } catch (HTTPFutureResponseStatus $ex) {
      if ($ex->isTimeout() || (int)$ex->getStatusCode() > 499) {
        $host->didHealthCheck(false);
      }
      throw $ex;
    }

    if ($method != 'GET') {
      return null;
    }

    try {
      $data = phutil_json_decode($body);
      $host->didHealthCheck(true);
      return $data;
    } catch (PhutilJSONParserException $ex) {
      $host->didHealthCheck(false);
      throw new PhutilProxyException(
        pht('Elasticsearch server returned invalid JSON!'),
        $ex);
    }

  }

}

<?php

final class PhabricatorSearchEngineElastic extends PhabricatorSearchEngine {
  private $uri;
  private $timeout;

  public function __construct($uri) {
    $this->uri = $uri;
  }

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $doc) {

    $type = $doc->getDocumentType();
    $phid = $doc->getPHID();
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs(array($phid))
      ->executeOne();

    // URL is not used internally but it can be useful externally.
    $spec = array(
      'title'         => $doc->getDocumentTitle(),
      'url'           => PhabricatorEnv::getProductionURI($handle->getURI()),
      'dateCreated'   => $doc->getDocumentCreated(),
      '_timestamp'    => $doc->getDocumentModified(),
      'field'         => array(),
      'relationship'  => array(),
    );

    foreach ($doc->getFieldData() as $field) {
      $spec['field'][] = array_combine(array('type', 'corpus', 'aux'), $field);
    }

    foreach ($doc->getRelationshipData() as $relationship) {
      list($rtype, $to_phid, $to_type, $time) = $relationship;
      $spec['relationship'][$rtype][] = array(
        'phid'      => $to_phid,
        'phidType'  => $to_type,
        'when'      => $time,
      );
    }

    $this->executeRequest(
      "/phabricator/{$type}/{$phid}/",
      $spec,
      $is_write = true);
  }

  public function reconstructDocument($phid) {
    $type = phid_get_type($phid);

    $response = $this->executeRequest("/phabricator/{$type}/{$phid}", array());

    if (empty($response['exists'])) {
      return null;
    }

    $hit = $response['_source'];

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($phid);
    $doc->setDocumentType($response['_type']);
    $doc->setDocumentTitle($hit['title']);
    $doc->setDocumentCreated($hit['dateCreated']);
    $doc->setDocumentModified($hit['_timestamp']);

    foreach ($hit['field'] as $fdef) {
      $doc->addField($fdef['type'], $fdef['corpus'], $fdef['aux']);
    }

    foreach ($hit['relationship'] as $rtype => $rships) {
      foreach ($rships as $rship) {
        $doc->addRelationship(
          $rtype,
          $rship['phid'],
          $rship['phidType'],
          $rship['when']);
      }
    }

    return $doc;
  }

  private function buildSpec(PhabricatorSavedQuery $query) {
    $spec = array();
    $filter = array();

    if (strlen($query->getParameter('query'))) {
      $spec[] = array(
        'term' => array(
          'field.corpus' => $query->getParameter('query'),
        ),
      );
    }

    $exclude = $query->getParameter('exclude');
    if ($exclude) {
      $filter[] = array(
        'not' => array(
          'ids' => array(
            'values' => array($exclude),
          ),
        ),
      );
    }

    $relationship_map = array(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR =>
        $query->getParameter('authorPHIDs', array()),
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER =>
        $query->getParameter('ownerPHIDs', array()),
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
      $relationship_map[$rel_open] = true;
    } else if (!$include_open && $include_closed) {
      $relationship_map[$rel_closed] = true;
    }

    if ($query->getParameter('withUnowned')) {
      $relationship_map[$rel_unowned] = true;
    }

    foreach ($relationship_map as $field => $param) {
      if (is_array($param) && $param) {
        $should = array();
        foreach ($param as $val) {
          $should[] = array(
            'text' => array(
              "relationship.{$field}.phid" => array(
                'query' => $val,
                'type' => 'phrase',
              ),
            ),
          );
        }
        // We couldn't solve it by minimum_number_should_match because it can
        // match multiple owners without matching author.
        $spec[] = array('bool' => array('should' => $should));
      } else if ($param) {
        $filter[] = array(
          'exists' => array(
            'field' => "relationship.{$field}.phid",
          ),
        );
      }
    }

    if ($spec) {
      $spec = array('query' => array('bool' => array('must' => $spec)));
    }

    if ($filter) {
      $filter = array('filter' => array('and' => $filter));
      if (!$spec) {
        $spec = array('query' => array('match_all' => new stdClass()));
      }
      $spec = array(
        'query' => array(
          'filtered' => $spec + $filter,
        ),
      );
    }

    if (!$query->getParameter('query')) {
      $spec['sort'] = array(
        array('dateCreated' => 'desc'),
      );
    }

    $spec['from'] = (int)$query->getParameter('offset', 0);
    $spec['size'] = (int)$query->getParameter('limit', 25);

    return $spec;
  }

  public function executeSearch(PhabricatorSavedQuery $query) {
    $types = $query->getParameter('types');
    if (!$types) {
      $types = array_keys(
        PhabricatorSearchApplicationSearchEngine::getIndexableDocumentTypes());
    }

    // Don't use '/phabricator/_search' for the case that there is something
    // else in the index (for example if 'phabricator' is only an alias to
    // some bigger index).
    $uri = '/phabricator/'.implode(',', $types).'/_search';

    try {
      $response = $this->executeRequest($uri, $this->buildSpec($query));
    } catch (HTTPFutureResponseStatusHTTP $ex) {
      // elasticsearch probably uses Lucene query syntax:
      // http://lucene.apache.org/core/3_6_1/queryparsersyntax.html
      // Try literal search if operator search fails.
      if (!strlen($query->getParameter('query'))) {
        throw $ex;
      }
      $query = clone $query;
      $query->setParameter(
        'query',
        addcslashes(
          $query->getParameter('query'), '+-&|!(){}[]^"~*?:\\'));
      $response = $this->executeRequest($uri, $this->buildSpec($query));
    }

    $phids = ipull($response['hits']['hits'], '_id');
    return $phids;
  }

  private function executeRequest($path, array $data, $is_write = false) {
    $uri = new PhutilURI($this->uri);
    $data = json_encode($data);

    $uri->setPath($path);

    $future = new HTTPSFuture($uri, $data);
    if ($is_write) {
      $future->setMethod('PUT');
    }
    if ($this->getTimeout()) {
      $future->setTimeout($this->getTimeout());
    }
    list($body) = $future->resolvex();

    if ($is_write) {
      return null;
    }

    $body = json_decode($body, true);
    if (!is_array($body)) {
      throw new Exception("elasticsearch server returned invalid JSON!");
    }

    return $body;
  }

}

<?php

final class PhabricatorFerretFulltextStorageEngine
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

    // NOTE: The Ferret engine indexes are rebuilt by an extension rather than
    // by the main fulltext engine, and are always built regardless of
    // configuration.

    return;
  }

  public function executeSearch(PhabricatorSavedQuery $query) {
    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    $type_map = array();
    foreach ($all_objects as $object) {
      $phid_type = phid_get_type($object->generatePHID());

      $type_map[$phid_type] = array(
        'object' => $object,
        'engine' => $object->newFerretEngine(),
      );
    }

    $types = $query->getParameter('types');
    if ($types) {
      $type_map = array_select_keys($type_map, $types);
    }

    $offset = (int)$query->getParameter('offset', 0);
    $limit  = (int)$query->getParameter('limit', 25);

    // NOTE: For now, it's okay to query with the omnipotent viewer here
    // because we're just returning PHIDs which we'll filter later.
    $viewer = PhabricatorUser::getOmnipotentUser();

    $type_results = array();
    $metadata = array();
    foreach ($type_map as $type => $spec) {
      $engine = $spec['engine'];
      $object = $spec['object'];

      $local_query = new PhabricatorSavedQuery();
      $local_query->setParameter('query', $query->getParameter('query'));

      $project_phids = $query->getParameter('projectPHIDs');
      if ($project_phids) {
        $local_query->setParameter('projectPHIDs', $project_phids);
      }

      $subscriber_phids = $query->getParameter('subscriberPHIDs');
      if ($subscriber_phids) {
        $local_query->setParameter('subscriberPHIDs', $subscriber_phids);
      }

      $search_engine = $engine->newSearchEngine()
        ->setViewer($viewer);

      $engine_query = $search_engine->buildQueryFromSavedQuery($local_query)
        ->setViewer($viewer);

      $engine_query
        ->withFerretQuery($engine, $query)
        ->setOrder('relevance')
        ->setLimit($offset + $limit);

      $results = $engine_query->execute();
      $results = mpull($results, null, 'getPHID');
      $type_results[$type] = $results;

      $metadata += $engine_query->getFerretMetadata();

      if (!$this->fulltextTokens) {
        $this->fulltextTokens = $engine_query->getFerretTokens();
      }
    }

    $list = array();
    foreach ($type_results as $type => $results) {
      $list += $results;
    }

    // Currently, the list is grouped by object type. For example, all the
    // tasks might be first, then all the revisions, and so on. In each group,
    // the results are ordered properly.

    // Reorder the results so that the highest-ranking results come first,
    // no matter which object types they belong to.

    $metadata = msort($metadata, 'getRelevanceSortVector');
    $list = array_select_keys($list, array_keys($metadata)) + $list;

    $result_slice = array_slice($list, $offset, $limit, true);
    return array_keys($result_slice);
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


}

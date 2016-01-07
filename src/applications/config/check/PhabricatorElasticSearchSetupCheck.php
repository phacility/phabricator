<?php

final class PhabricatorElasticSearchSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    if (!$this->shouldUseElasticSearchEngine()) {
      return;
    }

    $engine = new PhabricatorElasticFulltextStorageEngine();

    $index_exists = null;
    $index_sane = null;
    try {
      $index_exists = $engine->indexExists();
      if ($index_exists) {
        $index_sane = $engine->indexIsSane();
      }
    } catch (Exception $ex) {
      $summary = pht('Elasticsearch is not reachable as configured.');
      $message = pht(
        'Elasticsearch is configured (with the %s setting) but Phabricator '.
        'encountered an exception when trying to test the index.'.
        "\n\n".
        '%s',
        phutil_tag('tt', array(), 'search.elastic.host'),
        phutil_tag('pre', array(), $ex->getMessage()));

      $this->newIssue('elastic.misconfigured')
        ->setName(pht('Elasticsearch Misconfigured'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('search.elastic.host');
      return;
    }

    if (!$index_exists) {
      $summary = pht(
        'You enabled Elasticsearch but the index does not exist.');

      $message = pht(
        'You likely enabled search.elastic.host without creating the '.
        'index. Run `./bin/search init` to correct the index.');

      $this
        ->newIssue('elastic.missing-index')
        ->setName(pht('Elasticsearch index Not Found'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('search.elastic.host');
    } else if (!$index_sane) {
      $summary = pht(
        'Elasticsearch index exists but needs correction.');

      $message = pht(
        'Either the Phabricator schema for Elasticsearch has changed '.
        'or Elasticsearch created the index automatically. Run '.
        '`./bin/search init` to correct the index.');

      $this
        ->newIssue('elastic.broken-index')
        ->setName(pht('Elasticsearch index Incorrect'))
        ->setSummary($summary)
        ->setMessage($message);
    }
  }

  protected function shouldUseElasticSearchEngine() {
    $search_engine = PhabricatorFulltextStorageEngine::loadEngine();
    return ($search_engine instanceof PhabricatorElasticFulltextStorageEngine);
  }

}

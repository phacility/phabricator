<?php

final class PhabricatorElasticSearchSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    if ($this->shouldUseElasticSearchEngine()) {
      $engine = new PhabricatorElasticSearchEngine();

      if (!$engine->indexExists()) {
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
      } else if (!$engine->indexIsSane()) {
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
  }

  protected function shouldUseElasticSearchEngine() {
    $search_engine = PhabricatorSearchEngine::loadEngine();
    return $search_engine instanceof PhabricatorElasticSearchEngine;
  }

}

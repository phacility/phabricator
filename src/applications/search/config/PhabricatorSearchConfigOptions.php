<?php

final class PhabricatorSearchConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Search");
  }

  public function getDescription() {
    return pht("Options relating to Search.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'search.engine-selector',
        'class',
        'PhabricatorDefaultSearchEngineSelector')
        ->setBaseClass('PhabricatorSearchEngineSelector')
        ->setSummary(pht("Search engine selector."))
        ->setDescription(
          pht(
            "Phabricator uses a search engine selector to choose which ".
            "search engine to use when indexing and reconstructing ".
            "documents, and when executing queries. You can override the ".
            "engine selector to provide a new selector class which can ".
            "select some custom engine you implement, if you want to store ".
            "your documents in some search engine which does not have ".
            "default support.")),
      $this->newOption('search.elastic.host', 'string', null)
        ->setDescription(pht("Elastic Search host."))
        ->addExample('http://elastic.example.com:9200/', pht('Valid Setting')),
    );
  }

}

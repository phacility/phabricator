<?php

final class PhabricatorDefaultSearchEngineSelector
  extends PhabricatorSearchEngineSelector {

  public function newEngine() {
    $elastic_host = PhabricatorEnv::getEnvConfig('search.elastic.host');
    if ($elastic_host) {
      $elastic_index = PhabricatorEnv::getEnvConfig('search.elastic.namespace');
      return new PhabricatorSearchEngineElastic($elastic_host, $elastic_index);
    }
    return new PhabricatorSearchEngineMySQL();
  }
}

<?php

final class PhabricatorDefaultSearchEngineSelector
  extends PhabricatorSearchEngineSelector {

  public function newEngine() {
    if (self::shouldUseElasticSearch()) {
      $elastic_host = PhabricatorEnv::getEnvConfig('search.elastic.host');
      $elastic_index = PhabricatorEnv::getEnvConfig('search.elastic.namespace');
      return new PhabricatorSearchEngineElastic($elastic_host, $elastic_index);
    }
    return new PhabricatorSearchEngineMySQL();
  }

  public static function shouldUseElasticSearch() {
    return (bool)PhabricatorEnv::getEnvConfig('search.elastic.host');
  }

}

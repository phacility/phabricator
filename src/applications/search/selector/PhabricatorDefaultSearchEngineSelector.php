<?php

/**
 * @group search
 */
final class PhabricatorDefaultSearchEngineSelector
  extends PhabricatorSearchEngineSelector {

  public function newEngine() {
    $elastic_host = PhabricatorEnv::getEnvConfig('search.elastic.host');
    if ($elastic_host) {
      return new PhabricatorSearchEngineElastic($elastic_host);
    }
    return new PhabricatorSearchEngineMySQL();
  }
}

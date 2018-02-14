<?php

final class PhabricatorDatasourceEngine extends Phobject {

  public function getAllQuickSearchDatasources() {
    return PhabricatorDatasourceEngineExtension::getAllQuickSearchDatasources();
  }
}

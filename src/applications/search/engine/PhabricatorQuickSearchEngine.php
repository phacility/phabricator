<?php

final class PhabricatorQuickSearchEngine extends Phobject {

  public function getAllDatasources() {
    return PhabricatorQuickSearchEngineExtension::getAllDatasources();
  }
}

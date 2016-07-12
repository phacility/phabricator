<?php

final class HarbormasterBuildPlanNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'buildplanname';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'harbormaster';
  }

}

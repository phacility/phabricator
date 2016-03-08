<?php

final class NuanceSourceNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'sourcename';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'nuance';
  }

}

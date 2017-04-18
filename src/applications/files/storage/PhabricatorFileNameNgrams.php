<?php

final class PhabricatorFileNameNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'filename';
  }

  public function getColumnName() {
    return 'name';
  }

  public function getApplicationName() {
    return 'file';
  }

}

<?php

final class ConpherenceThreadTitleNgrams
  extends PhabricatorSearchNgrams {

  public function getNgramKey() {
    return 'threadtitle';
  }

  public function getColumnName() {
    return 'title';
  }

  public function getApplicationName() {
    return 'conpherence';
  }
}

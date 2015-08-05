<?php

final class PhrictionDocumentHeraldFieldGroup extends HeraldFieldGroup {

  const FIELDGROUPKEY = 'phriction.document';

  public function getGroupLabel() {
    return pht('Document Fields');
  }

  protected function getGroupOrder() {
    return 1000;
  }

}

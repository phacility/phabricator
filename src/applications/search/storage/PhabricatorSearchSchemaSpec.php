<?php

final class PhabricatorSearchSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildEdgeSchemata(new PhabricatorProfilePanelConfiguration());

    $this->buildRawSchema(
      'search',
      PhabricatorSearchDocument::STOPWORDS_TABLE,
      array(
        'value' => 'sort32',
      ),
      array());
  }

}

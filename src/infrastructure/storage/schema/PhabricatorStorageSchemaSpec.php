<?php

final class PhabricatorStorageSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildRawSchema(
      'meta_data',
      'patch_status',
      array(
        'patch' => 'text128',
        'applied' => 'uint32',
      ),
      array(
        'PRIMARY' => array(
          'columns' => array('patch'),
          'unique' => true,
        ),
      ));
  }

}

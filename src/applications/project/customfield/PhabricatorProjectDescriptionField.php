<?php

final class PhabricatorProjectDescriptionField
  extends PhabricatorProjectStandardCustomField {

  public function createFields() {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      array(
        'description' => array(
          'name'        => pht('Description'),
          'type'        => 'remarkup',
          'description' => pht('Short project description.'),
        ),
      ));
  }

}

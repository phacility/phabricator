<?php

final class PhabricatorProjectDescriptionField
  extends PhabricatorProjectStandardCustomField {

  public function createFields($object) {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      array(
        'description' => array(
          'name'        => pht('Description'),
          'type'        => 'remarkup',
          'description' => pht('Short project description.'),
          'fulltext'    => PhabricatorSearchDocumentFieldType::FIELD_BODY,
        ),
      ));
  }

}

<?php

final class PhabricatorRoleDescriptionField
  extends PhabricatorRoleStandardCustomField {

  public function createFields($object) {
    return PhabricatorStandardCustomField::buildStandardFields(
      $this,
      array(
        'description' => array(
          'name'        => pht('Description'),
          'type'        => 'remarkup',
          'description' => pht('Short role description.'),
          'fulltext'    => PhabricatorSearchDocumentFieldType::FIELD_BODY,
        ),
      ),
      $internal = true);
  }

}

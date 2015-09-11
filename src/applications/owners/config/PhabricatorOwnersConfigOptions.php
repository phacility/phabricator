<?php

final class PhabricatorOwnersConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Owners');
  }

  public function getDescription() {
    return pht('Configure Owners.');
  }

  public function getFontIcon() {
    return 'fa-gift';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';
    $default_fields = array();

    $field_base_class = id(new PhabricatorOwnersPackage())
      ->getCustomFieldBaseClass();

    $fields_example = array(
      'mycompany:lore' => array(
        'name' => pht('Package Lore'),
        'type' => 'remarkup',
        'caption' => pht('Tales of adventure for this package.'),
      ),
    );
    $fields_example = id(new PhutilJSON())->encodeFormatted($fields_example);

    return array(
      $this->newOption('metamta.package.subject-prefix', 'string', '[Package]')
        ->setDescription(pht('Subject prefix for Owners email.')),
      $this->newOption('owners.fields', $custom_field_type, $default_fields)
        ->setCustomData($field_base_class)
        ->setDescription(pht('Select and reorder package fields.')),
      $this->newOption('owners.custom-field-definitions', 'wild', array())
        ->setSummary(pht('Custom Owners fields.'))
        ->setDescription(
          pht(
            'Map of custom fields for Owners packages. For details on '.
            'adding custom fields to Owners, see "Configuring Custom '.
            'Fields" in the documentation.'))
        ->addExample($fields_example, pht('Valid Setting')),
    );
  }

}

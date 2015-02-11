<?php

final class PhabricatorProjectConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Projects');
  }

  public function getDescription() {
    return pht('Configure Projects.');
  }

  public function getFontIcon() {
    return 'fa-briefcase';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $default_fields = array(
      'std:project:internal:description' => true,
    );

    foreach ($default_fields as $key => $enabled) {
      $default_fields[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';

    return array(
      $this->newOption('projects.custom-field-definitions', 'wild', array())
        ->setSummary(pht('Custom Projects fields.'))
        ->setDescription(
          pht(
            'Array of custom fields for Projects.'))
        ->addExample(
          '{"mycompany:motto": {"name": "Project Motto", '.
          '"type": "text"}}',
          pht('Valid Setting')),
      $this->newOption('projects.fields', $custom_field_type, $default_fields)
        ->setCustomData(id(new PhabricatorProject())->getCustomFieldBaseClass())
        ->setDescription(pht('Select and reorder project fields.')),
    );
  }

}

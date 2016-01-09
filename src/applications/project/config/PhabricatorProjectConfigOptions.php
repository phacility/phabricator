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
    $default_icons = PhabricatorProjectIconSet::getDefaultConfiguration();
    $icons_type = 'custom:PhabricatorProjectTypeConfigOptionType';

    $icons_description = $this->deformat(pht(<<<EOTEXT
Allows you to change and customize the available project icons.

You can find a list of available icons in {nav UIExamples > Icons and Images}.

Configure a list of icon specifications. Each icon specification should be
a dictionary, which may contain these keys:

  - `key` //Required string.// Internal key identifying the icon.
  - `name` //Required string.// Human-readable icon name.
  - `icon` //Required string.// Specifies which actual icon image to use.
  - `default` //Optional bool.// Selects a default icon. Exactly one icon must
    be selected as the default.
  - `disabled` //Optional bool.// If true, this icon will no longer be
    available for selection when creating or editing projects.
  - `special` //Optional string.// Marks an icon as a special icon:
    - `milestone` This is the icon for milestones. Exactly one icon must be
      selected as the milestone icon.

You can look at the default configuration below for an example of a valid
configuration.
EOTEXT
      ));


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
      $this->newOption('projects.icons', $icons_type, $default_icons)
        ->setSummary(pht('Adjust project icons.'))
        ->setDescription($icons_description),
    );
  }

}

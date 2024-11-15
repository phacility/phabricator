<?php

final class PhabricatorRoleConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Roles');
  }

  public function getDescription() {
    return pht('Configure Roles.');
  }

  public function getIcon() {
    return 'fa-briefcase';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $default_icons = PhabricatorRoleIconSet::getDefaultConfiguration();
    $icons_type = 'role.icons';

    $icons_description = $this->deformat(pht(<<<EOTEXT
Allows you to change and customize the available role icons.

You can find a list of available icons in {nav UIExamples > Icons and Images}.

Configure a list of icon specifications. Each icon specification should be
a dictionary, which may contain these keys:

  - `key` //Required string.// Internal key identifying the icon.
  - `name` //Required string.// Human-readable icon name.
  - `icon` //Required string.// Specifies which actual icon image to use.
  - `image` //Optional string.// Selects a default image. Select an image from
    `resources/builtins/roles/`.
  - `default` //Optional bool.// Selects a default icon. Exactly one icon must
    be selected as the default.
  - `disabled` //Optional bool.// If true, this icon will no longer be
    available for selection when creating or editing roles.
  - `special` //Optional string.// Marks an icon as a special icon:
    - `milestone` This is the icon for milestones. Exactly one icon must be
      selected as the milestone icon.

You can look at the default configuration below for an example of a valid
configuration.
EOTEXT
      ));

    $default_colors = PhabricatorRoleIconSet::getDefaultColorMap();
    $colors_type = 'role.colors';

    $colors_description = $this->deformat(pht(<<<EOTEXT
Allows you to relabel role colors.

The list of available colors can not be expanded, but the existing colors may
be given labels.

Configure a list of color specifications. Each color specification should be a
dictionary, which may contain these keys:

  - `key` //Required string.// The internal key identifying the color.
  - `name` //Required string.// Human-readable label for the color.
  - `default` //Optional bool.// Selects the default color used when creating
    new roles. Exactly one color must be selected as the default.

You can look at the default configuration below for an example of a valid
configuration.
EOTEXT
      ));

    $default_fields = array(
      'std:role:internal:description' => true,
    );

    foreach ($default_fields as $key => $enabled) {
      $default_fields[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';


    $subtype_type = 'roles.subtypes';
    $subtype_default_key = PhabricatorEditEngineSubtype::SUBTYPE_DEFAULT;
    $subtype_example = array(
      array(
        'key' => $subtype_default_key,
        'name' => pht('Role'),
      ),
      array(
        'key' => 'team',
        'name' => pht('Team'),
      ),
    );
    $subtype_example = id(new PhutilJSON())->encodeAsList($subtype_example);

    $subtype_default = array(
      array(
        'key' => $subtype_default_key,
        'name' => pht('Role'),
      ),
    );

    $subtype_description = $this->deformat(pht(<<<EOTEXT
Allows you to define role subtypes. For a more detailed description of
subtype configuration, see @{config:maniphest.subtypes}.
EOTEXT
      ));

    return array(
      $this->newOption('roles.custom-field-definitions', 'wild', array())
        ->setSummary(pht('Custom Roles fields.'))
        ->setDescription(
          pht(
            'Array of custom fields for Roles.'))
        ->addExample(
          '{"mycompany:motto": {"name": "Role Motto", '.
          '"type": "text"}}',
          pht('Valid Setting')),
      $this->newOption('roles.fields', $custom_field_type, $default_fields)
        ->setCustomData(id(new PhabricatorRole())->getCustomFieldBaseClass())
        ->setDescription(pht('Select and reorder role fields.')),
      $this->newOption('roles.icons', $icons_type, $default_icons)
        ->setSummary(pht('Adjust role icons.'))
        ->setDescription($icons_description),
      $this->newOption('roles.colors', $colors_type, $default_colors)
        ->setSummary(pht('Adjust role colors.'))
        ->setDescription($colors_description),
      $this->newOption('roles.subtypes', $subtype_type, $subtype_default)
        ->setSummary(pht('Define role subtypes.'))
        ->setDescription($subtype_description)
        ->addExample($subtype_example, pht('Simple Subtypes')),

    );
  }

}

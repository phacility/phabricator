<?php

final class PhabricatorManiphestConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Maniphest");
  }

  public function getDescription() {
    return pht("Configure Maniphest.");
  }

  public function getOptions() {
    return array(
      $this->newOption('maniphest.enabled', 'bool', true)
        ->setDescription(pht("Enable Maniphest")),
      $this->newOption('maniphest.custom-fields', 'wild', array())
        ->setSummary(pht("Custom Maniphest fields."))
        ->setDescription(
          pht(
            "Array of custom fields for Maniphest tasks. For details on ".
            "adding custom fields to Maniphest, see 'Maniphest User Guide: ".
            "Adding Custom Fields'."))
        ->addExample(
          '{"mycompany:estimated-hours": {"label": "Estimated Hours", '.
          '"type": "int", "caption": "Estimated number of hours this will '.
          'take.", "required": false}}',
          pht('Valid Setting')),
      $this->newOption(
        'maniphest.custom-task-extensions-class',
        'class',
        'ManiphestDefaultTaskExtensions')
        ->setBaseClass('ManiphestTaskExtensions')
        ->setSummary(pht("Class which drives custom field construction."))
        ->setDescription(
          pht(
            "Class which drives custom field construction. See 'Maniphest ".
            "User Guide: Adding Custom Fields' in the documentation for more ".
            "information.")),
      $this->newOption('maniphest.default-priority', 'int', 90)
        ->setSummary(pht("Default task priority for create flows."))
        ->setDescription(
          pht(
            "What should the default task priority be in create flows? See ".
            "the constants in @{class:ManiphestTaskPriority} for valid ".
            "values. Defaults to 'needs triage'.")),
    );
  }

}

<?php

final class PhabricatorApplicationReleephConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Releeph");
  }

  public function getDescription() {
    return pht("Options for configuring Releeph, the release branch tool.");
  }

  public function getOptions() {

    $default_fields = array(
      new ReleephCommitMessageFieldSpecification(),
      new ReleephSummaryFieldSpecification(),
      new ReleephReasonFieldSpecification(),
      new ReleephAuthorFieldSpecification(),
      new ReleephRevisionFieldSpecification(),
      new ReleephRequestorFieldSpecification(),
      new ReleephSeverityFieldSpecification(),
      new ReleephOriginalCommitFieldSpecification(),
      new ReleephDiffMessageFieldSpecification(),
      new ReleephStatusFieldSpecification(),
      new ReleephIntentFieldSpecification(),
      new ReleephBranchCommitFieldSpecification(),
      new ReleephDiffSizeFieldSpecification(),
      new ReleephDiffChurnFieldSpecification(),
    );

    $default = array();
    foreach ($default_fields as $default_field) {
      $default[$default_field->getFieldKey()] = true;
    }

    foreach ($default as $key => $enabled) {
      $default[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';

    return array(
      $this->newOption('releeph.installed', 'bool', false)
        ->setSummary(pht('Enable the Releeph application.'))
        ->setDescription(
          pht(
            "Releeph, a tool for managing release branches, will eventually ".
            "fit in to the Phabricator suite as a general purpose tool. ".
            "However Releeph is currently unstable in multiple ways that may ".
            "not migrate properly for you: the code is still in alpha stage ".
            "of design, the storage format is likely to change in unexpected ".
            "ways, and the workflows presented are very specific to a core ".
            "set of alpha testers at Facebook.  For the time being you are ".
            "strongly discouraged from relying on Releeph being at all ".
            "stable.")),
      $this->newOption('releeph.fields', $custom_field_type, $default)
        ->setCustomData('ReleephFieldSpecification'),
      $this->newOption(
        'releeph.user-view',
        'class',
        'ReleephDefaultUserView')
        ->setBaseClass('ReleephUserView')
        ->setSummary(pht('Extra markup when rendering usernames'))
        ->setDescription(
          pht(
            "A wrapper to render Phabricator users in Releeph, with custom ".
            "markup.  For example, Facebook extends this to render additional ".
            "information about requestors, to each Releeph project's ".
            "pushers.")),
      $this->newOption(
        'releeph.default-branch-template',
        'string',
        'releases/%P/%p-%Y%m%d-%v')
        ->setDescription(
          pht(
            "The default branch template for new branches in unconfigured ".
            "Releeph projects.  This is also configurable on a per-project ".
            "basis.")),
    );
  }


}

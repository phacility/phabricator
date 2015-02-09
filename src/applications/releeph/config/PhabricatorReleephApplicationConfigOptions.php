<?php

final class PhabricatorReleephApplicationConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Releeph');
  }

  public function getDescription() {
    return pht('Options for configuring Releeph, the release branch tool.');
  }

  public function getFontIcon() {
    return 'fa-flag-checkered';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    $default_fields = array(
      new ReleephSummaryFieldSpecification(),
      new ReleephRequestorFieldSpecification(),
      new ReleephSeverityFieldSpecification(),
      new ReleephIntentFieldSpecification(),
      new ReleephReasonFieldSpecification(),
      new ReleephAuthorFieldSpecification(),
      new ReleephRevisionFieldSpecification(),
      new ReleephOriginalCommitFieldSpecification(),
      new ReleephBranchCommitFieldSpecification(),
      new ReleephDiffSizeFieldSpecification(),
      new ReleephDiffChurnFieldSpecification(),
      new ReleephDiffMessageFieldSpecification(),
      new ReleephCommitMessageFieldSpecification(),
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
      $this->newOption('releeph.fields', $custom_field_type, $default)
        ->setCustomData('ReleephFieldSpecification'),
      $this->newOption(
        'releeph.default-branch-template',
        'string',
        'releases/%P/%p-%Y%m%d-%v')
        ->setDescription(
          pht(
            'The default branch template for new branches in unconfigured '.
            'Releeph projects. This is also configurable on a per-project '.
            'basis.')),
    );
  }

}

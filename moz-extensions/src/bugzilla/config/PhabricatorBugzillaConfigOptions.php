<?php

final class PhabricatorBugzillaConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Bugzilla');
  }

  public function getDescription() {
    return pht('Configure Bugzilla Settings.');
  }

  public function getIcon() {
    return 'fa-cog';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'bugzilla.url',
        'string',
        'https://bugzilla.allizom.org')
        ->setDescription(pht('Full URL for the Bugzilla server.')),
      $this->newOption(
        'bugzilla.automation_user',
        'string',
        'phab-bot@bmo.tld')
        ->setDescription(pht('Automation Username on Bugzilla.')),
      $this->newOption(
        'bugzilla.automation_api_key',
        'string',
        false)
        ->setDescription(pht('Automation User API Key on Bugzilla.')),
      $this->newOption(
        'bugzilla.timeout',
        'int',
        15)
        ->setDescription(pht('Bugzilla timeout in seconds.')),
      $this->newOption(
        'bugzilla.require_bugs',
        'bool',
        false)
        ->setDescription(pht('Require existing Bugzilla bug numbers for revisions.')),
      $this->newOption(
        'bugzilla.require_mfa',
        'bool',
        true)
        ->setDescription(pht('Require Bugzilla members to have multi-factor authentication enabled on their Bugzilla account.')),
    );
  }
}

<?php

final class PhabricatorGitHubConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Integration with GitHub");
  }

  public function getDescription() {
    return pht("GitHub authentication and integration options.");
  }

  public function getOptions() {
    return array(
      $this->newOption('github.auth-enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable GitHub Authentication"),
            pht("Disable GitHub Authentication"),
          ))
        ->setDescription(
          pht(
            'Allow users to login to Phabricator using GitHub credentials.')),
      $this->newOption('github.registration-enabled', 'bool', true)
        ->setBoolOptions(
          array(
            pht("Enable GitHub Registration"),
            pht("Disable GitHub Registration"),
          ))
        ->setDescription(
          pht(
            'Allow users to create new Phabricator accounts using GitHub '.
            'credentials.')),
      $this->newOption('github.auth-permanent', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Permanently Bind GitHub Accounts"),
            pht("Allow GitHub Account Unlinking"),
          ))
        ->setDescription(
          pht(
            'Are Phabricator accounts permanently bound to GitHub '.
            'accounts?')),
      $this->newOption('github.application-id', 'string', null)
        ->setDescription(
          pht(
            'GitHub "Client ID" to use for GitHub API access.')),
      $this->newOption('github.application-secret', 'string', null)
        ->setMasked(true)
        ->setDescription(
          pht(
            'GitHub "Secret" to use for GitHub API access.')),
    );
  }

}

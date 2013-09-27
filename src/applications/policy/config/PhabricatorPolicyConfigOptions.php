<?php

final class PhabricatorPolicyConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Policy");
  }

  public function getDescription() {
    return pht("Options relating to object visibility.");
  }

  public function getOptions() {
    return array(
      $this->newOption('policy.allow-public', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Allow Public Visibility'),
            pht('Require Login')))
        ->setSummary(pht("Allow users to set object visibility to public."))
        ->setDescription(
          pht(
            "Phabricator allows you to set the visibility of objects (like ".
            "repositories and tasks) to 'Public', which means **anyone ".
            "on the internet can see them, without needing to log in or ".
            "have an account**.".
            "\n\n".
            "This is intended for open source projects. Many installs will ".
            "never want to make anything public, so this policy is disabled ".
            "by default. You can enable it here, which will let you set the ".
            "policy for objects to 'Public'.".
            "\n\n".
            "Enabling this setting will immediately open up some features, ".
            "like the user directory. Anyone on the internet will be able to ".
            "access these features.".
            "\n\n".
            "With this setting disabled, the 'Public' policy is not ".
            "available, and the most open policy is 'All Users' (which means ".
            "users must have accounts and be logged in to view things).")),
    );
  }

}

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
            "repositories and source code) to 'Public', which means anyone ".
            "on the internet can see them, even without being logged in. ".
            "This is great for open source, but some installs may never want ".
            "to make anything public, so this policy is disabled by default. ".
            "You can enable it here, which will let you set the policy for ".
            "objects to 'Public'. With this option disabled, the most open".
            "policy is 'All Users', which means users must be logged in to ".
            "view things.")),
    );
  }

}

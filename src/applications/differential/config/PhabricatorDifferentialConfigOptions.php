<?php

final class PhabricatorDifferentialConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Differential');
  }

  public function getDescription() {
    return pht('Configure Differential code review.');
  }

  public function getOptions() {
    return array(
      $this->newOption('differential.show-host-field', 'bool', false)
        ->setOptions(
          array(
            pht('Disable "Host" Fields'),
            pht('Show "Host" Fields'),
          ))
        ->setSummary(pht('Show or hide the "Host" and "Path" fields.'))
        ->setDescription(
          pht(
            'Differential can show "Host" and "Path" fields on revisions, '.
            'with information about the machine and working directory where '.
            'the change came from. These fields are disabled by default '.
            'because they may occasionally have sensitive information, but '.
            'they can be useful if you work in an environment with shared '.
            'development machines. You can set this option to true to enable '.
            'these fields.')),
      $this->newOption('differential.show-test-plan-field', 'bool', true)
        ->setOptions(
          array(
            pht('Hide "Test Plan" Field'),
            pht('Show "Test Plan" Field'),
          ))
        ->setSummary(pht('Show or hide the "Test Plan" field.'))
        ->setDescription(
          pht(
            'Differential has a required "Test Plan" field by default, which '.
            'requires authors to fill out information about how they verified '.
            'the correctness of their changes when they send code for review. '.
            'If you would prefer not to use this field, you can disable it '.
            'here. You can also make it optional (instead of required) by '.
            'setting {{differential.require-test-plan-field}}.')),
      $this->newOption('differential.enable-email-accept', 'bool', false)
        ->setOptions(
          array(
            pht('Disable Email "!accept" Action'),
            pht('Enable Email "!accept" Action'),
          ))
        ->setSummary(pht('Enable or disable "!accept" action via email.'))
        ->setDescription(
          pht(
            'If inbound email is configured, users can interact with '.
            'revisions by using "!actions" in email replies (for example, '.
            '"!resign" or "!rethink"). However, by default, users may not '.
            '"!accept" revisions via email: email authentication can be '.
            'configured to be very weak, and email "!accept" is kind of '.
            'sketchy and implies the revision may not actually be receiving '.
            'thorough review. You can enable "!accept" by setting this '.
            'option to true.')),
    );
  }

}

<?php

final class PhabricatorTranslationsConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Translations');
  }

  public function getDescription() {
    return pht('Options relating to translations.');
  }

  public function getFontIcon() {
    return 'fa-globe';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('translation.override', 'wild', array())
        ->setSummary(pht('Override translations.'))
        ->setDescription(
          pht(
            "You can use '%s' if you don't want to create a full translation ".
            "to give users an option for switching to it and you just want to ".
            "override some strings in the default translation.",
            'translation.override'))
        ->addExample(
          '{"some string": "my alternative"}',
          pht('Valid Setting')),
    );
  }

}

<?php

final class PhabricatorTranslationsConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Translations");
  }

  public function getDescription() {
    return pht("Options relating to translations.");
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'translation.provider',
        'class',
        'PhabricatorEnglishTranslation')
        ->setBaseClass('PhabricatorTranslation')
        ->setSummary(pht("Translation class that should be used for strings."))
        ->setDescription(
          pht(
            "This allows customizing texts used in Phabricator. The class ".
            "must extend PhabricatorTranslation."))
        ->addExample('PhabricatorEnglishTranslation', pht('Valid Setting')),
      // TODO: This should be dict<string,string> I think, but that doesn't
      // exist yet.
      $this->newOption('translation.override', 'wild', array())
        ->setSummary(pht("Override translations."))
        ->setDescription(
          pht(
            "You can use 'translation.override' if you don't want to create ".
            "a full translation to give users an option for switching to it ".
            "and you just want to override some strings in the default ".
            "translation."))
        ->addExample(
          '{"some string": "my alternative"}',
          pht('Valid Setting')),
    );
  }

}

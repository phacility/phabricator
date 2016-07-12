<?php

final class PhabricatorPhurlConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Phurl');
  }

  public function getDescription() {
    return pht('Options for Phurl.');
  }

  public function getIcon() {
    return 'fa-link';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption('phurl.short-uri', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('URI that Phurl will use to shorten URLs.'))
        ->setDescription(
          pht(
            'Set the URI that Phurl will use to share shortened URLs.'))
        ->addExample(
          'https://some-very-short-domain.museum/',
          pht('Valid Setting')),
    );
  }
}

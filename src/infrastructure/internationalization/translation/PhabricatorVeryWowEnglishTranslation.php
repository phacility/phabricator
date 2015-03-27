<?php

final class PhabricatorVeryWowEnglishTranslation
  extends PhutilTranslation {

  public function getLocaleCode() {
    return 'en_W*';
  }

  protected function getTranslations() {
    return array(
      'Search' => 'Search! Wow!',
      'Review Code' => 'Wow! Code Review! Wow!',
      'Tasks and Bugs' => 'Much Bug! Very Bad!',
      'Cancel' => 'Nope!',
      'Advanced Search' => 'Much Search!',
      'No search results.' => 'No results! Wow!',
      'Send' => 'Bark Bark!',
      'Partial' => 'Pawtial',
      'Partial Upload' => 'Pawtial Upload',
    );
  }

}

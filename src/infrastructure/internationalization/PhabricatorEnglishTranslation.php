<?php

final class PhabricatorEnglishTranslation
  extends PhabricatorBaseEnglishTranslation {

  public function getName() {
    return 'English';
  }

  public function getTranslations() {
    return
      PhabricatorEnv::getEnvConfig('translation.override') +
      parent::getTranslations();
  }

}

<?php

final class PhabricatorChineseTranslation
  extends PhabricatorBaseChineseTranslation {

  public function getName() {
    return 'Chinese';
  }

  public function getTranslations() {
    return
      PhabricatorEnv::getEnvConfig('translation.override') +
      parent::getTranslations();
  }

}

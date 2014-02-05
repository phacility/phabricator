<?php

abstract class PhabricatorTranslation {

  abstract public function getLanguage();
  abstract public function getName();
  abstract public function getTranslations();

}

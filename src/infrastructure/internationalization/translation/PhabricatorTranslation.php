<?php

abstract class PhabricatorTranslation {

  abstract public function getLanguage();
  abstract public function getName();
  abstract public function getTranslations();


  /**
   * Return the cleaned translation array.
   *
   * @return dict<string, wild> Translation map with empty translations removed.
   */
  public function getCleanTranslations() {
    return $this->clean($this->getTranslations());
  }


  /**
   * Removes NULL-valued translation keys from the translation map, to prevent
   * echoing out empty strings.
   *
   * @param dict<string, wild> Translation map, with empty translations.
   * @return dict<string, wild> Map with empty translations removed.
   */
  protected function clean(array $translation_array) {
    foreach ($translation_array as $key => $translation_string) {
      if ($translation_string === null) {
        unset($translation_array[$key]);
      }
    }

    return $translation_array;
  }

}

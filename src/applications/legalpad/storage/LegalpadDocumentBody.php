<?php

final class LegalpadDocumentBody extends LegalpadDAO
  implements
    PhabricatorMarkupInterface {

  const MARKUP_FIELD_TEXT = 'markup:text ';

  protected $phid;
  protected $creatorPHID;
  protected $documentPHID;
  protected $version;
  protected $title;
  protected $text;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'version' => 'uint32',
        'title' => 'text255',
        'text' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_document' => array(
          'columns' => array('documentPHID', 'version'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_LEGB);
  }

/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    return 'LEGB:'.$hash;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    switch ($field) {
      case self::MARKUP_FIELD_TEXT:
        $text = $this->getText();
        break;
      case self::MARKUP_FIELD_TITLE:
        $text = $this->getTitle();
        break;
      default:
        throw new Exception(pht('Unknown field: %s', $field));
        break;
    }

    return $text;
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    require_celerity_resource('phabricator-remarkup-css');
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

}

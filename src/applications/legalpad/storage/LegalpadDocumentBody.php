<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentBody extends LegalpadDAO
  implements
    PhabricatorMarkupInterface {

  const MARKUP_FIELD_TITLE = 'markup:title';
  const MARKUP_FIELD_TEXT = 'markup:text ';

  protected $phid;
  protected $creatorPHID;
  protected $documentPHID;
  protected $version;
  protected $title;
  protected $text;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
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
        throw new Exception('Unknown field: '.$field);
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

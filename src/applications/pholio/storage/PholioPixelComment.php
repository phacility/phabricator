<?php

/**
 * @group pholio
 */
final class PholioPixelComment extends PholioDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_COMMENT  = 'markup:comment';

  protected $mockID;
  protected $transactionID;
  protected $authorPHID;
  protected $imageID;

  protected $x;
  protected $y;
  protected $width;
  protected $height;
  protected $comment;


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    return 'MP:'.$this->getID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(array());
  }

  public function getMarkupText($field) {
    return $this->getComment();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return ($this->getID() && $this->getTransactionID());
  }

}

<?php

/**
 * @group pholio
 */
final class PholioTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $imageID;
  protected $x;
  protected $y;
  protected $width;
  protected $height;

  public function getApplicationTransactionObject() {
    return new PholioTransaction();
  }

}

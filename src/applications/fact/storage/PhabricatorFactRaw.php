<?php

/**
 * Raw fact about an object.
 */
final class PhabricatorFactRaw extends PhabricatorFactDAO {

  protected $id;
  protected $factType;
  protected $objectPHID;
  protected $objectA;
  protected $valueX;
  protected $valueY;
  protected $epoch;

}

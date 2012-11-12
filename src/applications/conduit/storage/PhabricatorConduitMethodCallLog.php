<?php

/**
 * @group conduit
 */
final class PhabricatorConduitMethodCallLog extends PhabricatorConduitDAO {

  protected $connectionID;
  protected $method;
  protected $error;
  protected $duration;

}

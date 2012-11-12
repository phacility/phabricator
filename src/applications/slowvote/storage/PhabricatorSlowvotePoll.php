<?php

/**
 * @group slowvote
 */
final class PhabricatorSlowvotePoll extends PhabricatorSlowvoteDAO {

  const RESPONSES_VISIBLE = 0;
  const RESPONSES_VOTERS  = 1;
  const RESPONSES_OWNER   = 2;

  const METHOD_PLURALITY  = 0;
  const METHOD_APPROVAL   = 1;

  protected $question;
  protected $phid;
  protected $authorPHID;
  protected $responseVisibility;
  protected $shuffle;
  protected $method;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_POLL);
  }

}

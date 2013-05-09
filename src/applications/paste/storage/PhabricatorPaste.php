<?php

final class PhabricatorPaste extends PhabricatorPasteDAO
  implements PhabricatorTokenReceiverInterface, PhabricatorPolicyInterface {

  protected $phid;
  protected $title;
  protected $authorPHID;
  protected $filePHID;
  protected $language;
  protected $parentPHID;
  protected $viewPolicy;

  private $content;
  private $rawContent;

  public function getURI() {
    return '/P'.$this->getID();
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PSTE);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      return $this->viewPolicy;
    }
    return PhabricatorPolicies::POLICY_NOONE;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    return ($user->getPHID() == $this->getAuthorPHID());
  }

  public function getFullName() {
    $title = $this->getTitle();
    if (!$title) {
      $title = pht('(An Untitled Masterwork)');
    }
    return 'P'.$this->getID().' '.$title;
  }

  public function getContent() {
    if ($this->content === null) {
      throw new Exception("Call attachContent() before getContent()!");
    }
    return $this->content;
  }

  public function attachContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getRawContent() {
    if ($this->rawContent === null) {
      throw new Exception("Call attachRawContent() before getRawContent()!");
    }
    return $this->rawContent;
  }

  public function attachRawContent($raw_content) {
    $this->rawContent = $raw_content;
    return $this;
  }

/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */

  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getAuthorPHID(),
    );
  }

}

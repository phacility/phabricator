<?php

final class PhrictionDocumentHeraldAdapter extends HeraldAdapter {

  private $document;
  private $ccPHIDs = array();

  public function getAdapterApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

  public function getAdapterContentDescription() {
    return pht('React to wiki documents being created or updated.');
  }

  protected function newObject() {
    return new PhrictionDocument();
  }

  public function getObject() {
    return $this->document;
  }

  public function setDocument(PhrictionDocument $document) {
    $this->document = $document;
    return $this;
  }
  public function getDocument() {
    return $this->document;
  }

  private function setCcPHIDs(array $cc_phids) {
    $this->ccPHIDs = $cc_phids;
    return $this;
  }

  public function getCcPHIDs() {
    return $this->ccPHIDs;
  }

  public function getAdapterContentName() {
    return pht('Phriction Documents');
  }

  public function supportsRuleType($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return true;
      case HeraldRuleTypeConfig::RULE_TYPE_OBJECT:
      default:
        return false;
    }
  }

  public function getFields() {
    return array_merge(
      array(
        self::FIELD_TITLE,
        self::FIELD_BODY,
        self::FIELD_AUTHOR,
        self::FIELD_IS_NEW_OBJECT,
        self::FIELD_CC,
        self::FIELD_PATH,
      ),
      parent::getFields());
  }

  public function getActions($rule_type) {
    switch ($rule_type) {
      case HeraldRuleTypeConfig::RULE_TYPE_GLOBAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
      case HeraldRuleTypeConfig::RULE_TYPE_PERSONAL:
        return array_merge(
          array(
            self::ACTION_ADD_CC,
            self::ACTION_EMAIL,
            self::ACTION_FLAG,
            self::ACTION_NOTHING,
          ),
          parent::getActions($rule_type));
    }
  }

  public function getPHID() {
    return $this->getDocument()->getPHID();
  }

  public function getHeraldName() {
    return pht('Wiki Document %d', $this->getDocument()->getID());
  }

  public function getHeraldField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
        return $this->getDocument()->getContent()->getTitle();
      case self::FIELD_BODY:
        return $this->getDocument()->getContent()->getContent();
      case self::FIELD_AUTHOR:
        return $this->getDocument()->getContent()->getAuthorPHID();
      case self::FIELD_CC:
        return PhabricatorSubscribersQuery::loadSubscribersForPHID(
          $this->getDocument()->getPHID());
      case self::FIELD_PATH:
        return $this->getDocument()->getContent()->getSlug();
    }

    return parent::getHeraldField($field);
  }

  public function applyHeraldEffects(array $effects) {
    assert_instances_of($effects, 'HeraldEffect');

    $result = array();
    foreach ($effects as $effect) {
      $action = $effect->getAction();
      switch ($action) {
        case self::ACTION_NOTHING:
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Great success at doing nothing.'));
          break;
        case self::ACTION_ADD_CC:
          foreach ($effect->getTarget() as $phid) {
            $this->ccPHIDs[] = $phid;
          }
          $result[] = new HeraldApplyTranscript(
            $effect,
            true,
            pht('Added address to cc list.'));
          break;
        default:
          $result[] = $this->applyStandardEffect($effect);
          break;
      }
    }
    return $result;
  }

}

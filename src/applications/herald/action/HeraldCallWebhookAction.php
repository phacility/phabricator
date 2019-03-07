<?php

final class HeraldCallWebhookAction extends HeraldAction {

  const ACTIONCONST = 'webhook';
  const DO_WEBHOOK = 'do.call-webhook';

  public function getHeraldActionName() {
    return pht('Call webhooks');
  }

  public function getActionGroupKey() {
    return HeraldUtilityActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    if (!$this->getAdapter()->supportsWebhooks()) {
      return false;
    }

    return true;
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type !== HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $adapter = $this->getAdapter();
    $rule = $effect->getRule();
    $target = $effect->getTarget();

    foreach ($target as $webhook_phid) {
      $adapter->queueWebhook($webhook_phid, $rule->getPHID());
    }

    $this->logEffect(self::DO_WEBHOOK, $target);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_WEBHOOK => array(
        'icon' => 'fa-cloud-upload',
        'color' => 'green',
        'name' => pht('Called Webhooks'),
      ),
    );
  }

  public function renderActionDescription($value) {
    return pht('Call webhooks: %s.', $this->renderHandleList($value));
  }

  protected function renderActionEffectDescription($type, $data) {
    return pht('Called webhooks: %s.', $this->renderHandleList($data));
  }

  protected function getDatasource() {
    return new HeraldWebhookDatasource();
  }

  public function getPHIDsAffectedByAction(HeraldActionRecord $record) {
    return $record->getTarget();
  }

}

<?php

final class PhabricatorProjectTriggerManiphestStatusRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'task.status';

  public function getSelectControlName() {
    return pht('Change status to');
  }

  protected function assertValidRuleValue($value) {
    if (!is_string($value)) {
      throw new Exception(
        pht(
          'Status rule value should be a string, but is not (value is "%s").',
          phutil_describe_type($value)));
    }

    $map = ManiphestTaskStatus::getTaskStatusMap();
    if (!isset($map[$value])) {
      throw new Exception(
        pht(
          'Rule value ("%s") is not a valid task status.',
          $value));
    }
  }

  protected function newDropTransactions($object, $value) {
    return array(
      $this->newTransaction()
        ->setTransactionType(ManiphestTaskStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($value),
    );
  }

  protected function newDropEffects($value) {
    $status_name = ManiphestTaskStatus::getTaskStatusName($value);
    $status_icon = ManiphestTaskStatus::getStatusIcon($value);
    $status_color = ManiphestTaskStatus::getStatusColor($value);

    $content = pht(
      'Change status to %s.',
      phutil_tag('strong', array(), $status_name));

    return array(
      $this->newEffect()
        ->setIcon($status_icon)
        ->setColor($status_color)
        ->addCondition('status', '!=', $value)
        ->setContent($content),
    );
  }

  protected function getDefaultValue() {
    return head_key(ManiphestTaskStatus::getTaskStatusMap());
  }

  protected function getPHUIXControlType() {
    return 'select';
  }

  protected function getPHUIXControlSpecification() {
    $map = ManiphestTaskStatus::getTaskStatusMap();

    return array(
      'options' => $map,
      'order' => array_keys($map),
    );
  }

  public function getRuleViewLabel() {
    return pht('Change Status');
  }

  public function getRuleViewDescription($value) {
    $status_name = ManiphestTaskStatus::getTaskStatusName($value);

    return pht(
      'Change task status to %s.',
      phutil_tag('strong', array(), $status_name));
  }

  public function getRuleViewIcon($value) {
    $status_icon = ManiphestTaskStatus::getStatusIcon($value);
    $status_color = ManiphestTaskStatus::getStatusColor($value);

    return id(new PHUIIconView())
      ->setIcon($status_icon, $status_color);
  }


}

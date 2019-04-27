<?php

final class PhabricatorProjectTriggerManiphestPriorityRule
  extends PhabricatorProjectTriggerRule {

  const TRIGGERTYPE = 'task.priority';

  public function getSelectControlName() {
    return pht('Change priority to');
  }

  protected function assertValidRuleRecordFormat($value) {
    if (!is_string($value)) {
      throw new Exception(
        pht(
          'Priority rule value should be a string, but is not (value is "%s").',
          phutil_describe_type($value)));
    }
  }

  protected function assertValidRuleRecordValue($value) {
    $map = ManiphestTaskPriority::getTaskPriorityMap();
    if (!isset($map[$value])) {
      throw new Exception(
        pht(
          'Task priority value ("%s") is not a valid task priority. '.
          'Valid priorities are: %s.',
          $value,
          implode(', ', array_keys($map))));
    }
  }

  protected function newDropTransactions($object, $value) {
    $value = ManiphestTaskPriority::getKeywordForTaskPriority($value);
    return array(
      $this->newTransaction()
        ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
        ->setNewValue($value),
    );
  }

  protected function newDropEffects($value) {
    $priority_name = ManiphestTaskPriority::getTaskPriorityName($value);
    $priority_icon = ManiphestTaskPriority::getTaskPriorityIcon($value);
    $priority_color = ManiphestTaskPriority::getTaskPriorityColor($value);

    $content = pht(
      'Change priority to %s.',
      phutil_tag('strong', array(), $priority_name));

    return array(
      $this->newEffect()
        ->setIcon($priority_icon)
        ->setColor($priority_color)
        ->addCondition('priority', '!=', $value)
        ->setContent($content),
    );
  }

  protected function getDefaultValue() {
    return ManiphestTaskPriority::getDefaultPriority();
  }

  protected function getPHUIXControlType() {
    return 'select';
  }

  protected function getPHUIXControlSpecification() {
    $map = ManiphestTaskPriority::getTaskPriorityMap();

    return array(
      'options' => $map,
      'order' => array_keys($map),
    );
  }

  public function getRuleViewLabel() {
    return pht('Change Priority');
  }

  public function getRuleViewDescription($value) {
    $priority_name = ManiphestTaskPriority::getTaskPriorityName($value);

    return pht(
      'Change task priority to %s.',
      phutil_tag('strong', array(), $priority_name));
  }

  public function getRuleViewIcon($value) {
    $priority_icon = ManiphestTaskPriority::getTaskPriorityIcon($value);
    $priority_color = ManiphestTaskPriority::getTaskPriorityColor($value);

    return id(new PHUIIconView())
      ->setIcon($priority_icon, $priority_color);
  }


}

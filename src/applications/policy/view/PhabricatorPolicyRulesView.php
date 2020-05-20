<?php

final class PhabricatorPolicyRulesView
  extends AphrontView {

  private $policy;

  public function setPolicy(PhabricatorPolicy $policy) {
    $this->policy = $policy;
    return $this;
  }

  public function getPolicy() {
    return $this->policy;
  }

  public function render() {
    $policy = $this->getPolicy();

    require_celerity_resource('policy-transaction-detail-css');

    $rule_objects = array();
    foreach ($policy->getCustomRuleClasses() as $class) {
      $rule_objects[$class] = newv($class, array());
    }

    $policy = clone $policy;
    $policy->attachRuleObjects($rule_objects);

    $details = array();
    $details[] = phutil_tag(
      'p',
      array(
        'class' => 'policy-transaction-detail-intro',
      ),
      pht('These rules are processed in order:'));

    foreach ($policy->getRules() as $index => $rule) {
      $rule_object = $rule_objects[$rule['rule']];
      if ($rule['action'] == 'allow') {
        $icon = 'fa-check-circle green';
      } else {
        $icon = 'fa-minus-circle red';
      }
      $icon = id(new PHUIIconView())
        ->setIcon($icon)
        ->setText(
          ucfirst($rule['action']).' '.$rule_object->getRuleDescription());

      $handle_phids = $rule_object->getRequiredHandlePHIDsForSummary(
        $rule['value']);
      if ($handle_phids) {
        $value = $this->getViewer()
          ->renderHandleList($handle_phids)
          ->setAsInline(true);
      } else {
        $value = $rule['value'];
      }

      $details[] = phutil_tag('div',
        array(
          'class' => 'policy-transaction-detail-row',
        ),
        array(
          $icon,
          $value,
        ));
    }

    $details[] = phutil_tag(
      'p',
      array(
        'class' => 'policy-transaction-detail-end',
      ),
      pht(
        'If no rules match, %s all other users.',
        phutil_tag('b',
        array(),
        $policy->getDefaultAction())));

    return $details;
  }

}

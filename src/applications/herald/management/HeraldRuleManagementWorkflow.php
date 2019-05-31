<?php

final class HeraldRuleManagementWorkflow
  extends HeraldManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rule')
      ->setExamples('**rule** --rule __rule__ --disable')
      ->setSynopsis(
        pht(
          'Modify a rule, bypassing policies. This workflow can disable '.
          'problematic personal rules.'))
      ->setArguments(
        array(
          array(
            'name' => 'rule',
            'param' => 'rule',
            'help' => pht('Apply changes to this rule.'),
          ),
          array(
            'name' => 'disable',
            'help' => pht('Disable the rule.'),
          ),
          array(
            'name' => 'enable',
            'help' => pht('Enable the rule.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $rule_name = $args->getArg('rule');
    if (!strlen($rule_name)) {
      throw new PhutilArgumentUsageException(
        pht('Specify a rule to edit with "--rule <id|monogram>".'));
    }

    if (preg_match('/^H\d+/', $rule_name)) {
      $rule_id = substr($rule_name, 1);
    } else {
      $rule_id = $rule_name;
    }

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($rule_id))
      ->executeOne();
    if (!$rule) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load Herald rule with ID or monogram "%s".',
          $rule_name));
    }

    $is_disable = $args->getArg('disable');
    $is_enable = $args->getArg('enable');

    $xactions = array();

    if ($is_disable && $is_enable) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify "--enable" or "--disable", but not both.'));
    } else if ($is_disable || $is_enable) {
      $xactions[] = $rule->getApplicationTransactionTemplate()
        ->setTransactionType(HeraldRuleDisableTransaction::TRANSACTIONTYPE)
        ->setNewValue($is_disable);
    }

    if (!$xactions) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use flags to specify at least one edit to apply to the '.
          'rule (for example, use "--disable" to disable a rule).'));
    }

    $herald_phid = id(new PhabricatorHeraldApplication())->getPHID();

    $editor = $rule->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setActingAsPHID($herald_phid)
      ->setContentSource($this->newContentSource())
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true);

    echo tsprintf(
      "%s\n",
      pht(
        'Applying changes to %s: %s...',
        $rule->getMonogram(),
        $rule->getName()));

    $editor->applyTransactions($rule, $xactions);

    echo tsprintf(
      "%s\n",
      pht('Done.'));


    return 0;
  }

}

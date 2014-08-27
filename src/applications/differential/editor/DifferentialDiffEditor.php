<?php

final class DifferentialDiffEditor extends PhabricatorEditor {

  private $contentSource;

  public function setContentSource($content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function getContentSource() {
    return $this->contentSource;
  }

  public function saveDiff(DifferentialDiff $diff) {
    $actor = $this->requireActor();

    // Generate a PHID first, so the transcript will point at the object if
    // we deicde to preserve it.
    $phid = $diff->generatePHID();
    $diff->setPHID($phid);

    $adapter = id(new HeraldDifferentialDiffAdapter())
      ->setDiff($diff);

    $adapter->setContentSource($this->getContentSource());
    $adapter->setIsNewObject(true);

    $engine = new HeraldEngine();

    $rules = $engine->loadRulesForAdapter($adapter);
    $rules = mpull($rules, null, 'getID');

    $effects = $engine->applyRules($rules, $adapter);

    $blocking_effect = null;
    foreach ($effects as $effect) {
      if ($effect->getAction() == HeraldAdapter::ACTION_BLOCK) {
        $blocking_effect = $effect;
        break;
      }
    }

    if ($blocking_effect) {
      $rule = idx($rules, $effect->getRuleID());
      if ($rule && strlen($rule->getName())) {
        $rule_name = $rule->getName();
      } else {
        $rule_name = pht('Unnamed Herald Rule');
      }

      $message = $effect->getTarget();
      if (!strlen($message)) {
        $message = pht('(None.)');
      }

      throw new DifferentialDiffCreationRejectException(
        pht(
          "Creation of this diff was rejected by Herald rule %s.\n".
          "  Rule: %s\n".
          "Reason: %s",
          'H'.$effect->getRuleID(),
          $rule_name,
          $message));
    }

    $diff->save();

    // NOTE: We only save the transcript if we didn't block the diff.
    // Otherwise, we might save some of the diff's content in the transcript
    // table, which would defeat the purpose of allowing rules to block
    // storage of key material.

    $engine->applyEffects($effects, $adapter, $rules);
    $xscript = $engine->getTranscript();

  }

}

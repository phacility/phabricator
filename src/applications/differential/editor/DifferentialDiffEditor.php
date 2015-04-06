<?php

final class DifferentialDiffEditor
  extends PhabricatorApplicationTransactionEditor {

  private $diffDataDict;
  private $lookupRepository = true;

  public function setLookupRepository($bool) {
    $this->lookupRepository = $bool;
    return $this;
  }

  public function getEditorApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Differential Diffs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = DifferentialDiffTransaction::TYPE_DIFF_CREATE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialDiffTransaction::TYPE_DIFF_CREATE:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialDiffTransaction::TYPE_DIFF_CREATE:
        $this->diffDataDict = $xaction->getNewValue();
        return true;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialDiffTransaction::TYPE_DIFF_CREATE:
        $dict = $this->diffDataDict;
        $this->updateDiffFromDict($object, $dict);
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialDiffTransaction::TYPE_DIFF_CREATE:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
    }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // If we didn't get an explicit `repositoryPHID` (which means the client
    // is old, or couldn't figure out which repository the working copy
    // belongs to), apply heuristics to try to figure it out.

    if ($this->lookupRepository && !$object->getRepositoryPHID()) {
      $repository = id(new DifferentialRepositoryLookup())
        ->setDiff($object)
        ->setViewer($this->getActor())
        ->lookupRepository();
      if ($repository) {
        $object->setRepositoryPHID($repository->getPHID());
        $object->setRepositoryUUID($repository->getUUID());
        $object->save();
      }
    }

    return $xactions;
  }

  /**
   * We run Herald as part of transaction validation because Herald can
   * block diff creation for Differential diffs. Its important to do this
   * separately so no Herald logs are saved; these logs could expose
   * information the Herald rules are inteneded to block.
   */
  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    foreach ($xactions as $xaction) {
      switch ($type) {
        case DifferentialDiffTransaction::TYPE_DIFF_CREATE:
          $diff = clone $object;
          $diff = $this->updateDiffFromDict($diff, $xaction->getNewValue());

          $adapter = $this->buildHeraldAdapter($diff, $xactions);
          $adapter->setContentSource($this->getContentSource());
          $adapter->setIsNewObject($this->getIsNewObject());

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
            $rule = $blocking_effect->getRule();

            $message = $effect->getTarget();
            if (!strlen($message)) {
              $message = pht('(None.)');
            }

            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Rejected by Herald'),
              pht(
                "Creation of this diff was rejected by Herald rule %s.\n".
                "  Rule: %s\n".
                "Reason: %s",
                $rule->getMonogram(),
                $rule->getName(),
                $message));
          }
          break;
      }
    }

    return $errors;
  }


  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function supportsSearch() {
    return false;
  }

/* -(  Herald Integration  )------------------------------------------------- */

  /**
   * See @{method:validateTransaction}. The only Herald action is to block
   * the creation of Diffs. We thus have to be careful not to save any
   * data and do this validation very early.
   */
  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return false;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $adapter = id(new HeraldDifferentialDiffAdapter())
      ->setDiff($object);

    return $adapter;
  }

  protected function didApplyHeraldRules(
    PhabricatorLiskDAO $object,
    HeraldAdapter $adapter,
    HeraldTranscript $transcript) {

    $xactions = array();
    return $xactions;
  }

  private function updateDiffFromDict(DifferentialDiff $diff, $dict) {
    $diff
      ->setSourcePath(idx($dict, 'sourcePath'))
      ->setSourceMachine(idx($dict, 'sourceMachine'))
      ->setBranch(idx($dict, 'branch'))
      ->setCreationMethod(idx($dict, 'creationMethod'))
      ->setAuthorPHID(idx($dict, 'authorPHID', $this->getActor()))
      ->setBookmark(idx($dict, 'bookmark'))
      ->setRepositoryPHID(idx($dict, 'repositoryPHID'))
      ->setRepositoryUUID(idx($dict, 'repositoryUUID'))
      ->setSourceControlSystem(idx($dict, 'sourceControlSystem'))
      ->setSourceControlPath(idx($dict, 'sourceControlPath'))
      ->setSourceControlBaseRevision(idx($dict, 'sourceControlBaseRevision'))
      ->setLintStatus(idx($dict, 'lintStatus'))
      ->setUnitStatus(idx($dict, 'unitStatus'))
      ->setArcanistProjectPHID(idx($dict, 'arcanistProjectPHID'));

    return $diff;
  }
}

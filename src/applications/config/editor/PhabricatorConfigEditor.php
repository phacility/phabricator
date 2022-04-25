<?php

final class PhabricatorConfigEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorConfigApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Configuration');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorConfigTransaction::TYPE_EDIT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return array(
          'deleted' => (int)$object->getIsDeleted(),
          'value'   => $object->getValue(),
        );
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        $v = $xaction->getNewValue();

        // If this is a defined configuration option (vs a straggler from an
        // old version of Phabricator or a configuration file misspelling)
        // submit it to the validation gauntlet.
        $key = $object->getConfigKey();
        $all_options = PhabricatorApplicationConfigOptions::loadAllOptions();
        $option = idx($all_options, $key);
        if ($option) {
          $option->getGroup()->validateOption(
            $option,
            $v['value']);
        }

        $object->setIsDeleted((int)$v['deleted']);
        $object->setValue($v['value']);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    return;
  }

  protected function mergeTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $type = $u->getTransactionType();
    switch ($type) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        return $v;
    }

    return parent::mergeTransactions($u, $v);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorConfigTransaction::TYPE_EDIT:
        // If an edit deletes an already-deleted entry, no-op it.
        if (idx($old, 'deleted') && idx($new, 'deleted')) {
          return false;
        }
        break;
    }

    return parent::transactionHasEffect($object, $xaction);
  }

  protected function didApplyTransactions($object, array $xactions) {
    // Force all the setup checks to run on the next page load.
    PhabricatorSetupCheck::deleteSetupCheckCache();

    return $xactions;
  }

  public static function storeNewValue(
    PhabricatorUser $user,
    PhabricatorConfigEntry $config_entry,
    $value,
    PhabricatorContentSource $source,
    $acting_as_phid = null) {

    $xaction = id(new PhabricatorConfigTransaction())
      ->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT)
      ->setNewValue(
        array(
           'deleted' => false,
           'value' => $value,
        ));

    $editor = id(new PhabricatorConfigEditor())
      ->setActor($user)
      ->setContinueOnNoEffect(true)
      ->setContentSource($source);

    if ($acting_as_phid) {
      $editor->setActingAsPHID($acting_as_phid);
    }

    $editor->applyTransactions($config_entry, array($xaction));
  }

  public static function deleteConfig(
    PhabricatorUser $user,
    PhabricatorConfigEntry $config_entry,
    PhabricatorContentSource $source) {

    $xaction = id(new PhabricatorConfigTransaction())
      ->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT)
      ->setNewValue(
        array(
          'deleted' => true,
          'value' => null,
        ));

    $editor = id(new PhabricatorConfigEditor())
      ->setActor($user)
      ->setContinueOnNoEffect(true)
      ->setContentSource($source);

    $editor->applyTransactions($config_entry, array($xaction));
  }

}

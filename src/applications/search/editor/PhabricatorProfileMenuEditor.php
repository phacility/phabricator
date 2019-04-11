<?php

final class PhabricatorProfileMenuEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Profile Menu Items');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] =
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY;
    $types[] =
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER;
    $types[] =
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue('property.key');
        return $object->getMenuItemProperty($key, null);
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER:
        return $object->getMenuItemOrder();
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY:
        return $object->getVisibility();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY:
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY:
        return $xaction->getNewValue();
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER:
        return (int)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY:
        $key = $xaction->getMetadataValue('property.key');
        $value = $xaction->getNewValue();
        $object->setMenuItemProperty($key, $value);
        return;
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER:
        $object->setMenuItemOrder($xaction->getNewValue());
        return;
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY:
        $object->setVisibility($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY:
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER:
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    $actor = $this->getActor();
    $menu_item = $object->getMenuItem();
    $menu_item->setViewer($actor);

    switch ($type) {
      case PhabricatorProfileMenuItemConfigurationTransaction::TYPE_PROPERTY:
        $key_map = array();
        foreach ($xactions as $xaction) {
          $xaction_key = $xaction->getMetadataValue('property.key');
          $old = $this->getCustomTransactionOldValue($object, $xaction);
          $new = $xaction->getNewValue();
          $key_map[$xaction_key][] = array(
            'xaction' => $xaction,
            'old' => $old,
            'new' => $new,
          );
        }

        foreach ($object->validateTransactions($key_map) as $error) {
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function supportsSearch() {
    return true;
  }

}

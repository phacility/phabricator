<?php

final class PhabricatorSearchOrderField
  extends PhabricatorSearchField {

  private $options;
  private $orderAliases;

  public function setOrderAliases(array $order_aliases) {
    $this->orderAliases = $order_aliases;
    return $this;
  }

  public function getOrderAliases() {
    return $this->orderAliases;
  }

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function getDefaultValue() {
    return null;
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    return $request->getStr($key);
  }

  protected function getValueForControl() {
    // If the SavedQuery has an alias for an order, map it to the canonical
    // name for the order so the correct option is selected in the dropdown.
    $value = parent::getValueForControl();
    if (isset($this->orderAliases[$value])) {
      $value = $this->orderAliases[$value];
    }
    return $value;
  }

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions($this->getOptions());
  }

}

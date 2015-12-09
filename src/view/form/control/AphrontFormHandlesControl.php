<?php

final class AphrontFormHandlesControl extends AphrontFormControl {

  private $isInvisible;

  protected function getCustomControlClass() {
    return 'aphront-form-control-handles';
  }

  public function setIsInvisible($is_invisible) {
    $this->isInvisible = $is_invisible;
    return $this;
  }

  public function getIsInvisible() {
    return $this->isInvisible;
  }

  protected function shouldRender() {
    return (bool)$this->getValue();
  }

  public function getLabel() {
    // TODO: This is a bit funky and still rendering a few pixels of padding
    // on the form, but there's currently no way to get a control to only emit
    // hidden inputs. Clean this up eventually.

    if ($this->getIsInvisible()) {
      return null;
    }

    return parent::getLabel();
  }

  protected function renderInput() {
    $value = $this->getValue();
    $viewer = $this->getUser();

    $out = array();

    if (!$this->getIsInvisible()) {
      $list = $viewer->renderHandleList($value);
      $list = id(new PHUIBoxView())
        ->addPadding(PHUI::PADDING_SMALL_TOP)
        ->appendChild($list);
      $out[] = $list;
    }

    $inputs = array();
    foreach ($value as $phid) {
      $inputs[] = phutil_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $this->getName().'[]',
          'value' => $phid,
        ));
    }
    $out[] = $inputs;

    return $out;
  }

}

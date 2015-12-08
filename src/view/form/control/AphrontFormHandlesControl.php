<?php

final class AphrontFormHandlesControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-control-handles';
  }

  protected function shouldRender() {
    return (bool)$this->getValue();
  }

  protected function renderInput() {
    $value = $this->getValue();
    $viewer = $this->getUser();

    $list = $viewer->renderHandleList($value);
    $list = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_SMALL_TOP)
      ->appendChild($list);

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

    return array($list, $inputs);
  }

}

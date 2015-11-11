<?php

final class PhabricatorInstructionsEditField
  extends PhabricatorEditField {

  public function appendToForm(AphrontFormView $form) {
    return $form->appendRemarkupInstructions($this->getValue());
  }

}

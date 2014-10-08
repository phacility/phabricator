<?php

final class AphrontDialogResponse extends AphrontResponse {

  private $dialog;

  public function setDialog(AphrontDialogView $dialog) {
    $this->dialog = $dialog;
    return $this;
  }

  public function getDialog() {
    return $this->dialog;
  }

  public function buildResponseString() {
    return $this->dialog->render();
  }

}

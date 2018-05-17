<?php

final class PhabricatorCountFact extends PhabricatorFact {

  protected function newTemplateDatapoint() {
    return new PhabricatorFactIntDatapoint();
  }

}

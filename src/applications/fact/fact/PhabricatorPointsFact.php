<?php

final class PhabricatorPointsFact extends PhabricatorFact {

  protected function newTemplateDatapoint() {
    return new PhabricatorFactIntDatapoint();
  }

}

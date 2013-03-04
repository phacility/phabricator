<?php

final class PonderRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return 'Q';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');
    return id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();
  }

  protected function shouldMarkupObject(array $params) {
    // NOTE: Q1, Q2, Q3 and Q4 are often used to refer to quarters of the year;
    // mark them up only in the {Q1} format.
    if ($params['type'] == 'ref') {
      if ($params['id'] <= 4) {
        return false;
      }
    }

    return true;
  }

}


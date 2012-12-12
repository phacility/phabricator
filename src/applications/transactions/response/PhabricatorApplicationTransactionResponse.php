<?php

final class PhabricatorApplicationTransactionResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $transactions;
  private $anchorOffset;

  protected function buildProxy() {
    return new AphrontAjaxResponse();
  }

  public function setAnchorOffset($anchor_offset) {
    $this->anchorOffset = $anchor_offset;
    return $this;
  }

  public function getAnchorOffset() {
    return $this->anchorOffset;
  }

  public function setTransactions($transactions) {
    assert_instances_of($transactions, 'PhabricatorApplicationTransaction');

    $this->transactions = $transactions;
    return $this;
  }

  public function getTransactions() {
    return $this->transactions;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function reduceProxyResponse() {
    $view = id(new PhabricatorApplicationTransactionView())
      ->setViewer($this->getViewer())
      ->setTransactions($this->getTransactions());

    if ($this->getAnchorOffset()) {
      $view->setAnchorOffset($this->getAnchorOffset());
    }

    $xactions = mpull($view->buildEvents(), 'render', 'getTransactionPHID');

    $content = array(
      'xactions' => $xactions,
      'spacer'   => PhabricatorTimelineView::renderSpacer(),
    );

    return $this->getProxy()->setContent($content);
  }

}

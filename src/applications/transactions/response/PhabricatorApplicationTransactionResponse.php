<?php

final class PhabricatorApplicationTransactionResponse
  extends AphrontProxyResponse {

  private $viewer;
  private $transactions;
  private $isPreview;
  private $previewContent;
  private $object;
  private $viewData = array();

  protected function buildProxy() {
    return new AphrontAjaxResponse();
  }

  public function setTransactions($transactions) {
    assert_instances_of($transactions, 'PhabricatorApplicationTransaction');

    $this->transactions = $transactions;
    return $this;
  }

  public function getTransactions() {
    return $this->transactions;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function setPreviewContent($preview_content) {
    $this->previewContent = $preview_content;
    return $this;
  }

  public function getPreviewContent() {
    return $this->previewContent;
  }

  public function setViewData(array $view_data) {
    $this->viewData = $view_data;
    return $this;
  }

  public function getViewData() {
    return $this->viewData;
  }

  public function reduceProxyResponse() {
    $object = $this->getObject();
    $viewer = $this->getViewer();
    $xactions = $this->getTransactions();

    $timeline_engine = PhabricatorTimelineEngine::newForObject($object)
      ->setViewer($viewer)
      ->setTransactions($xactions)
      ->setViewData($this->viewData);

    $view = $timeline_engine->buildTimelineView();

    $view->setIsPreview($this->isPreview);

    if ($this->isPreview) {
      $xactions = mpull($view->buildEvents(), 'render');
    } else {
      $xactions = mpull($view->buildEvents(), 'render', 'getTransactionPHID');
    }

    // Force whatever the underlying views built to render into HTML for
    // the Javascript.
    foreach ($xactions as $key => $xaction) {
      $xactions[$key] = hsprintf('%s', $xaction);
    }

    $aural = phutil_tag(
      'h3',
      array(
        'class' => 'aural-only',
      ),
      pht('Comment Preview'));

    $content = array(
      'header' => hsprintf('%s', $aural),
      'xactions' => $xactions,
      'spacer' => PHUITimelineView::renderSpacer(),
      'previewContent' => hsprintf('%s', $this->getPreviewContent()),
    );

    return $this->getProxy()->setContent($content);
  }


}

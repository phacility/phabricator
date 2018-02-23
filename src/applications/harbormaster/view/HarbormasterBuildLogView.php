<?php

final class HarbormasterBuildLogView extends AphrontView {

  private $log;
  private $highlightedLineRange;

  public function setBuildLog(HarbormasterBuildLog $log) {
    $this->log = $log;
    return $this;
  }

  public function getBuildLog() {
    return $this->log;
  }

  public function setHighlightedLineRange($range) {
    $this->highlightedLineRange = $range;
    return $this;
  }

  public function getHighlightedLineRange() {
    return $this->highlightedLineRange;
  }

  public function render() {
    $viewer = $this->getViewer();
    $log = $this->getBuildLog();
    $id = $log->getID();

    $header = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader(pht('Build Log %d', $id));

    $download_uri = "/harbormaster/log/download/{$id}/";

    $download_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($download_uri)
      ->setIcon('fa-download')
      ->setDisabled(!$log->getFilePHID())
      ->setWorkflow(true)
      ->setText(pht('Download Log'));

    $header->addActionLink($download_button);

    $content_id = celerity_generate_unique_node_id();
    $content_div = javelin_tag(
      'div',
      array(
        'id' => $content_id,
        'class' => 'harbormaster-log-view-loading',
      ),
      pht('Loading...'));

    require_celerity_resource('harbormaster-css');

    Javelin::initBehavior(
      'harbormaster-log',
      array(
        'contentNodeID' => $content_id,
        'renderURI' => $log->getRenderURI($this->getHighlightedLineRange()),
      ));

    $box_view = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header)
      ->appendChild($content_div);

    return $box_view;
  }

}

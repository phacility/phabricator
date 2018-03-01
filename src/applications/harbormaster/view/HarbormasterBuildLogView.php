<?php

final class HarbormasterBuildLogView extends AphrontView {

  private $log;
  private $highlightedLineRange;
  private $enableHighlighter;

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

  public function setEnableHighlighter($enable) {
    $this->enableHighlighter = $enable;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();
    $log = $this->getBuildLog();
    $id = $log->getID();

    $header = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader(pht('Build Log %d', $id));

    $download_uri = "/harbormaster/log/download/{$id}/";

    $can_download = (bool)$log->getFilePHID();

    $download_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($download_uri)
      ->setIcon('fa-download')
      ->setDisabled(!$can_download)
      ->setWorkflow(!$can_download)
      ->setText(pht('Download Log'));

    $header->addActionLink($download_button);

    $box_view = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header);

    if ($this->enableHighlighter) {
      Javelin::initBehavior('phabricator-line-linker');
    }

    $has_linemap = $log->getLineMap();
    if ($has_linemap) {
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
          'initialURI' => $log->getRenderURI($this->getHighlightedLineRange()),
          'renderURI' => $log->getRenderURI(null),
        ));

      $box_view->appendChild($content_div);
    } else {
      $box_view->setFormErrors(
        array(
          pht(
            'This older log is missing required rendering data. To rebuild '.
            'rendering data, run: %s',
            phutil_tag(
              'tt',
              array(),
              '$ bin/harbormaster rebuild-log --force --id '.$log->getID())),
        ));
    }

    return $box_view;
  }

}

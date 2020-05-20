<?php

final class DifferentialChangeDetailMailView
  extends DifferentialMailView {

  private $viewer;
  private $diff;
  private $patch;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  public function setPatch($patch) {
    $this->patch = $patch;
    return $this;
  }

  public function getPatch() {
    return $this->patch;
  }

  public function buildMailSection() {
    $viewer = $this->getViewer();

    $diff = $this->getDiff();

    $engine = new PhabricatorMarkupEngine();
    $viewstate = new PhabricatorChangesetViewState();

    $out = array();
    foreach ($diff->getChangesets() as $changeset) {
      $parser = id(new DifferentialChangesetParser())
        ->setViewer($viewer)
        ->setViewState($viewstate)
        ->setChangeset($changeset)
        ->setLinesOfContext(2)
        ->setMarkupEngine($engine);

      $parser->setRenderer(new DifferentialChangesetOneUpMailRenderer());
      $block = $parser->render();

      $filename = $changeset->getFilename();
      $filename = $this->renderHeaderBold($filename);
      $header = $this->renderHeaderBlock($filename);

      $out[] = $this->renderContentBox(
        array(
          $header,
          $this->renderCodeBlock($block),
        ));
    }

    $out = phutil_implode_html(phutil_tag('br'), $out);

    $patch_html = $out;

    $patch_text = $this->getPatch();

    return id(new PhabricatorMetaMTAMailSection())
      ->addPlaintextFragment($patch_text)
      ->addHTMLFragment($patch_html);
  }

}

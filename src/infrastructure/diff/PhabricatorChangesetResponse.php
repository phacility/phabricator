<?php

final class PhabricatorChangesetResponse extends AphrontProxyResponse {

  private $renderedChangeset;
  private $coverage;

  public function setRenderedChangeset($rendered_changeset) {
    $this->renderedChangeset = $rendered_changeset;
    return $this;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontAjaxResponse();
  }

  public function buildResponseString() {
    $content = array(
      'changeset' => $this->renderedChangeset,
    );

    if ($this->coverage) {
      $content['coverage'] = $this->coverage;
    }

    return $this->getProxy()->setContent($content)->buildResponseString();
  }

}

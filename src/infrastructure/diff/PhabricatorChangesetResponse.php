<?php

final class PhabricatorChangesetResponse extends AphrontProxyResponse {

  private $renderedChangeset;
  private $coverage;
  private $changesetState;

  public function setRenderedChangeset($rendered_changeset) {
    $this->renderedChangeset = $rendered_changeset;
    return $this;
  }

  public function getRenderedChangeset() {
    return $this->renderedChangeset;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontAjaxResponse();
  }

  public function reduceProxyResponse() {
    $content = array(
      'changeset' => $this->getRenderedChangeset(),
    ) + $this->getChangesetState();

    if ($this->coverage) {
      $content['coverage'] = $this->coverage;
    }

    return $this->getProxy()->setContent($content);
  }

  public function setChangesetState(array $state) {
    $this->changesetState = $state;
    return $this;
  }

  public function getChangesetState() {
    return $this->changesetState;
  }

}

<?php

final class PhabricatorChangesetResponse extends AphrontProxyResponse {

  private $renderedChangeset;
  private $coverage;
  private $undoTemplates;

  public function setRenderedChangeset($rendered_changeset) {
    $this->renderedChangeset = $rendered_changeset;
    return $this;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  public function setUndoTemplates($undo_templates) {
    $this->undoTemplates = $undo_templates;
    return $this;
  }

  protected function buildProxy() {
    return new AphrontAjaxResponse();
  }

  public function reduceProxyResponse() {
    $content = array(
      'changeset' => $this->renderedChangeset,
    );

    if ($this->coverage) {
      $content['coverage'] = $this->coverage;
    }

    if ($this->undoTemplates) {
      $content['undoTemplates'] = $this->undoTemplates;
    }

    return $this->getProxy()->setContent($content);
  }

}

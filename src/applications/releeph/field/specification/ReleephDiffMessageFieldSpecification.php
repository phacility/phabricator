<?php

final class ReleephDiffMessageFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit:message';
  }

  public function getName() {
    return 'Message';
  }

  public function renderLabelForHeaderView() {
    return null;
  }

  public function getStyleForPropertyView() {
    return 'block';
  }

  public function renderValueForHeaderView() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $this->getMarkupEngineOutput());
  }

  public function shouldMarkup() {
    return true;
  }

  public function getMarkupText($field) {
    $commit_data = $this
      ->getReleephRequest()
      ->loadPhabricatorRepositoryCommitData();
    if ($commit_data) {
      return $commit_data->getCommitMessage();
    } else {
      return '';
    }
  }

}

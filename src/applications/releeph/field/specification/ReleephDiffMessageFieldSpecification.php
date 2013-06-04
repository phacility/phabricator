<?php

final class ReleephDiffMessageFieldSpecification
  extends ReleephFieldSpecification {

  public function getName() {
    return 'Message';
  }

  public function renderLabelForHeaderView() {
    return null;
  }

  public function renderValueForHeaderView() {
    $markup = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $this->getMarkupEngineOutput());

    return id(new AphrontNoteView())
      ->setTitle('Commit Message')
      ->appendChild($markup)
      ->render();
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

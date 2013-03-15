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
    $commit_data = $this
      ->getReleephRequest()
      ->loadPhabricatorRepositoryCommitData();
    if (!$commit_data) {
      return '';
    }

    $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();
    $engine->setConfig('viewer', $this->getUser());
    $markup = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $engine->markupText($commit_data->getCommitMessage()));

    return id(new AphrontNoteView())
      ->setTitle('Commit Message')
      ->appendChild($markup)
      ->render();
  }

}

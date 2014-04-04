<?php

final class PhabricatorFeedStoryAudit extends PhabricatorFeedStory {

  public function getPrimaryObjectPHID() {
    return $this->getStoryData()->getValue('commitPHID');
  }

  public function renderView() {
    $author_phid = $this->getAuthorPHID();
    $commit_phid = $this->getPrimaryObjectPHID();

    $view = $this->newStoryView();
    $view->setAppIcon('audit-dark');

    $action = $this->getValue('action');
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $view->setTitle(hsprintf(
      '%s %s commit %s.',
      $this->linkTo($author_phid),
      $verb,
      $this->linkTo($commit_phid)));

    $comments = $this->getValue('content');
    $view->setImage($this->getHandle($author_phid)->getImageURI());

    if ($comments) {
      $content = $this->renderSummary($this->getValue('content'));
      $view->appendChild($content);
    }

    return $view;
  }

  public function renderText() {
    $author_name = $this->getHandle($this->getAuthorPHID())->getLinkName();

    $commit_path = $this->getHandle($this->getPrimaryObjectPHID())->getURI();
    $commit_uri = PhabricatorEnv::getURI($commit_path);

    $action = $this->getValue('action');
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $text = "{$author_name} {$verb} commit {$commit_uri}";

    return $text;
  }


  // TODO: At some point, make feed rendering not terrible and remove this
  // hacky mess.
  public function renderForAsanaBridge($implied_context = false) {
    $data = $this->getStoryData();
    $comment = $data->getValue('content');

    $author_name = $this->getHandle($this->getAuthorPHID())->getName();
    $action = $this->getValue('action');
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $commit_phid = $this->getPrimaryObjectPHID();
    $commit_name = $this->getHandle($commit_phid)->getFullName();

    if ($implied_context) {
      $title = "{$author_name} {$verb} this commit.";
    } else {
      $title = "{$author_name} {$verb} commit {$commit_name}.";
    }

    if (strlen($comment)) {
      $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
        ->setConfig('viewer', new PhabricatorUser())
        ->setMode(PhutilRemarkupEngine::MODE_TEXT);

      $comment = $engine->markupText($comment);

      $title .= "\n\n";
      $title .= $comment;
    }

    return $title;
  }

}

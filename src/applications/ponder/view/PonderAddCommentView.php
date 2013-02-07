<?php

final class PonderAddCommentView extends AphrontView {

  private $target;
  private $actionURI;
  private $questionID;

  public function setTarget($target) {
    $this->target = $target;
    return $this;
  }

  public function setQuestionID($id) {
    $this->questionID = $id;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-comment-table-css');

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $questionID = $this->questionID;
    $target = $this->target;

    $form = new AphrontFormView();
    $form
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->setWorkflow(true)
      ->addHiddenInput('target', $target)
      ->addHiddenInput('question_id', $questionID)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('content'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Editorialize'));

    $view = id(new AphrontMoreView())
      ->setSome('')
      ->setMore($form->render())
      ->setExpandText('Add Comment');

    return $view->render();
  }
}

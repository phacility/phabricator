<?php

final class PonderQuestionViewController extends PonderController {

  private $questionID;

  public function willProcessRequest(array $data) {
    $this->questionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = PonderQuestionQuery::loadSingle($user, $this->questionID);
    if (!$question) {
      return new Aphront404Response();
    }
    $question->attachRelated();
    $question->attachVotes($user->getPHID());
    $object_phids = array($user->getPHID(), $question->getAuthorPHID());

    $answers = $question->getAnswers();
    $comments = $question->getComments();
    foreach ($comments as $comment) {
      $object_phids[] = $comment->getAuthorPHID();
    }

    foreach ($answers as $answer) {
      $object_phids[] = $answer->getAuthorPHID();

      $comments = $answer->getComments();
      foreach ($comments as $comment) {
        $object_phids[] = $comment->getAuthorPHID();
      }
    }

    $object_phids = array_merge($object_phids);

    $this->loadHandles($object_phids);
    $handles = $this->getLoadedHandles();

    $detail_panel = new PonderQuestionDetailView();
    $detail_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setHandles($handles);

    $responses_panel = new PonderAnswerListView();
    $responses_panel
      ->setQuestion($question)
      ->setHandles($handles)
      ->setUser($user)
      ->setAnswers($answers);

    $answer_add_panel = new PonderAddAnswerView();
    $answer_add_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setActionURI("/ponder/answer/add/");

    $header = id(new PhabricatorHeaderView())
      ->setHeader($question->getTitle());

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName('Q'.$this->questionID)
          ->setHref('/Q'.$this->questionID));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $detail_panel,
        $responses_panel,
        $answer_add_panel
      ),
      array(
        'device' => true,
        'title' => 'Q'.$question->getID().' '.$question->getTitle(),
        'dust' => true,
      ));
  }

  private function buildActionListView(PonderQuestion $question) {
    $request = $this->getRequest();
    return id(new PhabricatorActionListView())
      ->setUser($request->getUser())
      ->setObject($question)
      ->setObjectURI($request->getRequestURI());
  }

  private function buildPropertyListView(
    PonderQuestion $question) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($question);
    $view->addProperty(
      pht('Author'),
      $this->getHandle($question->getAuthorPHID())->renderLink());

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($question->getDateCreated(), $viewer));

    return $view;
  }
}

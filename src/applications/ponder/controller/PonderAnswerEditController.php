<?php

final class PonderAnswerEditController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$answer) {
      return new Aphront404Response();
    }

    $v_content = $answer->getContent();
    $e_content = true;


    $question = $answer->getQuestion();
    $qid = $question->getID();
    $aid = $answer->getID();

    $question_uri = "/Q{$qid}#A{$aid}";

    $errors = array();
    if ($request->isFormPost()) {
      $v_content = $request->getStr('content');

      if (!strlen($v_content)) {
        $errors[] = pht('You must provide some substance in your answer.');
        $e_content = pht('Required');
      }

      if (!$errors) {
        $xactions = array();
        $xactions[] = id(new PonderAnswerTransaction())
          ->setTransactionType(PonderAnswerTransaction::TYPE_CONTENT)
          ->setNewValue($v_content);

        $editor = id(new PonderAnswerEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($answer, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($question_uri);
      }
    }

    if ($errors) {
      $errors = id(new AphrontErrorView())->setErrors($errors);
    }

    $answer_content_id = celerity_generate_unique_node_id();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Question'))
          ->setValue($question->getTitle()))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Answer'))
          ->setName('content')
          ->setID($answer_content_id)
          ->setValue($v_content)
          ->setError($e_content))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Update Answer'))
          ->addCancelButton($question_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName("Q{$qid}")
        ->setHref($question_uri));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Edit Answer')));

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Answer Preview'))
      ->setControlID($answer_content_id)
      ->setPreviewURI($this->getApplicationURI('preview/'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $errors,
        $form,
        $preview,
      ),
      array(
        'title' => pht('Edit Answer'),
        'dust' => true,
        'device' => true,
      ));

  }
}

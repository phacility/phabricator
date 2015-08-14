<?php

final class PonderAnswerEditController extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $v_status = $answer->getStatus();
    $e_content = true;


    $question = $answer->getQuestion();
    $qid = $question->getID();

    $answer_uri = $answer->getURI();

    $errors = array();
    if ($request->isFormPost()) {
      $v_content = $request->getStr('content');
      $v_status = $request->getStr('status');

      if (!strlen($v_content)) {
        $errors[] = pht('You must provide some substance in your answer.');
        $e_content = pht('Required');
      }

      if (!$errors) {
        $xactions = array();
        $xactions[] = id(new PonderAnswerTransaction())
          ->setTransactionType(PonderAnswerTransaction::TYPE_CONTENT)
          ->setNewValue($v_content);

        $xactions[] = id(new PonderAnswerTransaction())
          ->setTransactionType(PonderAnswerTransaction::TYPE_STATUS)
          ->setNewValue($v_status);

        $editor = id(new PonderAnswerEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($answer, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($answer_uri);
      }
    }

    $answer_content_id = celerity_generate_unique_node_id();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Question'))
          ->setValue($question->getTitle()))
      ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Status'))
            ->setName('status')
            ->setValue($v_status)
            ->setOptions(PonderAnswerStatus::getAnswerStatusMap()))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setLabel(pht('Answer'))
          ->setName('content')
          ->setID($answer_content_id)
          ->setValue($v_content)
          ->setError($e_content))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Submit'))
          ->addCancelButton($answer_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("Q{$qid}", $answer_uri);
    $crumbs->addTextCrumb(pht('Edit Answer'));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Answer'))
      ->setFormErrors($errors)
      ->setForm($form);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Answer Preview'))
      ->setControlID($answer_content_id)
      ->setPreviewURI($this->getApplicationURI('preview/'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $preview,
      ),
      array(
        'title' => pht('Edit Answer'),
      ));

  }
}

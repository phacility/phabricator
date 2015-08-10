<?php

final class PonderHelpfulSaveController extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needViewerVotes(true)
      ->executeOne();

    if (!$answer) {
      return new Aphront404Response();
    }

    $edit_uri = '/Q'.$answer->getQuestionID();

    switch ($action) {
      case 'add':
        $newvote = PonderVote::VOTE_UP;
      break;
      case 'remove':
        $newvote = PonderVote::VOTE_NONE;
      break;
    }

    if ($request->isFormPost()) {

      $editor = id(new PonderVoteEditor())
        ->setVotable($answer)
        ->setActor($viewer)
        ->setVote($newvote)
        ->saveVote();

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    if ($action == 'add') {
      $title = pht('Mark Answer as Helpful?');
      $body = pht('This answer will be marked as helpful.');
      $button = pht('Mark Helpful');
    } else {
      $title = pht('Remove Helpful From Answer?');
      $body = pht('This answer will no longer be marked as helpful.');
      $button = pht('Remove Helpful');
    }

    $dialog = $this->newDialog();
    $dialog->setTitle($title);
    $dialog->appendChild($body);
    $dialog->addCancelButton($edit_uri);
    $dialog->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}

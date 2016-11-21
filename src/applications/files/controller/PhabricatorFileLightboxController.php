<?php

final class PhabricatorFileLightboxController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $phid = $request->getURIData('phid');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$file) {
      return new Aphront404Response();
    }

    $transactions = id(new PhabricatorFileTransactionQuery())
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT));
    $timeline = $this->buildTransactionTimeline($file, $transactions);

    if ($timeline->isTimelineEmpty()) {
      $timeline = phutil_tag(
        'div',
        array(
          'class' => 'phui-comment-panel-empty',
        ),
        pht('No comments.'));
    }

    require_celerity_resource('phui-comment-panel-css');
    $content = phutil_tag(
      'div',
      array(
        'class' => 'phui-comment-panel',
      ),
      $timeline);

    return id(new AphrontAjaxResponse())
      ->setContent($content);
  }

}

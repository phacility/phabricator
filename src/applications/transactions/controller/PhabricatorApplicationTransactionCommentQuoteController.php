<?php

final class PhabricatorApplicationTransactionCommentQuoteController
  extends PhabricatorApplicationTransactionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $phid = $request->getURIData('phid');

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    if (!$xaction->getComment()) {
      return new Aphront404Response();
    }

    if ($xaction->getComment()->getIsRemoved()) {
      return new Aphront400Response();
    }

    if (!$xaction->hasComment()) {
      return new Aphront404Response();
    }

    $content = $xaction->getComment()->getContent();
    $content = rtrim($content, "\r\n");
    $content = phutil_split_lines($content, true);
    foreach ($content as $key => $line) {
      if (strlen($line) && ($line[0] != '>')) {
        $content[$key] = '> '.$line;
      } else {
        $content[$key] = '>'.$line;
      }
    }
    $content = implode('', $content);

    $author = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xaction->getComment()->getAuthorPHID()))
      ->executeOne();

    $ref = $request->getStr('ref');
    if (strlen($ref)) {
      $quote = pht('In %s, %s wrote:', $ref, '@'.$author->getName());
    } else {
      $quote = pht('%s wrote:', '@'.$author->getName());
    }

    $content = ">>! {$quote}\n{$content}";

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'quoteText' => $content,
      ));
  }

}

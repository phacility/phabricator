<?php

final class PholioTransactionView
  extends PhabricatorApplicationTransactionView {

  protected function shouldGroupTransactions(
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    if ($u->getAuthorPHID() != $v->getAuthorPHID()) {
      // Don't group transactions by different authors.
      return false;
    }

    if (($v->getDateCreated() - $u->getDateCreated()) > 60) {
      // Don't group if transactions happend more than 60s apart.
      return false;
    }

    switch ($u->getTransactionType()) {
      case PhabricatorTransactions::TYPE_COMMENT:
      case PholioTransactionType::TYPE_INLINE:
        break;
      default:
        return false;
    }

    switch ($v->getTransactionType()) {
      case PholioTransactionType::TYPE_INLINE:
        return true;
    }

    return parent::shouldGroupTransactions($u, $v);
  }

  protected function renderTransactionContent(
    PhabricatorApplicationTransaction $xaction) {

    $out = array();

    $group = $xaction->getTransactionGroup();
    if ($xaction->getTransactionType() == PholioTransactionType::TYPE_INLINE) {
      array_unshift($group, $xaction);
    } else {
      $out[] = parent::renderTransactionContent($xaction);
    }

    if (!$group) {
      return $out;
    }

    $inlines = array();
    foreach ($group as $xaction) {
      switch ($xaction->getTransactionType()) {
        case PholioTransactionType::TYPE_INLINE:
          $inlines[] = $xaction;
          break;
        default:
          throw new Exception("Unknown grouped transaction type!");
      }
    }

    if ($inlines) {
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-transaction-subheader',
        ),
        pht('Inline Comments'));

      $out[] = $header;
      foreach ($inlines as $inline) {
        if (!$inline->getComment()) {
          continue;
        }
        $out[] = $this->renderInlineContent($inline);
      }
    }

    return $out;
  }

  private function renderInlineContent(PholioTransaction $inline) {
    $comment = $inline->getComment();

    $thumb = phutil_tag(
      'img',
      array(
        'src' => '/pholio/inline/thumb/'.$comment->getImageID(),
        ));

    $link = phutil_tag(
      'a',
      array(
        'href' => '#'
      ),
      $thumb);

    $inline_comment = hsprintf('<p>%s</p>', $comment->getContent());

    return phutil_tag(
      'div',
      array('class' => 'pholio-transaction-inline-comment'),
      array($link, $inline_comment));
  }

}

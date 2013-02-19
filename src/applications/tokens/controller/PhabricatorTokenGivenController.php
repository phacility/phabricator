<?php

final class PhabricatorTokenGivenController extends PhabricatorTokenController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = id(new AphrontCursorPagerView())
      ->setURI(new PhutilURI($this->getApplicationURI('/given/')));

    $tokens_given = id(new PhabricatorTokenGivenQuery())
      ->setViewer($user)
      ->setLimit(100)
      ->executeWithCursorPager($pager);

    $handles = array();
    if ($tokens_given) {
      $object_phids = mpull($tokens_given, 'getObjectPHID');
      $user_phids = mpull($tokens_given, 'getAuthorPHID');
      $handle_phids = array_merge($object_phids, $user_phids);
      $handles = id(new PhabricatorObjectHandleData($handle_phids))
        ->setViewer($user)
        ->loadHandles();
    }

    $tokens = array();
    if ($tokens_given) {
      $token_phids = mpull($tokens_given, 'getTokenPHID');
      $tokens = id(new PhabricatorTokenQuery())
        ->setViewer($user)
        ->withPHIDs($token_phids)
        ->execute();
      $tokens = mpull($tokens, null, 'getPHID');
    }

    $list = new PhabricatorObjectItemListView();
    foreach ($tokens_given as $token_given) {
      $handle = $handles[$token_given->getObjectPHID()];
      $token = idx($tokens, $token_given->getTokenPHID());

      $item = id(new PhabricatorObjectItemView());
      $item->setHeader($handle->getFullName());
      $item->setHref($handle->getURI());

      $item->addAttribute($token->renderIcon());

      $item->addAttribute(
        pht(
          'Given by %s on %s',
          $handles[$token_given->getAuthorPHID()]->renderLink(),
          phabricator_date($token_given->getDateCreated(), $user)));

      $list->addItem($item);
    }

    $title = pht('Tokens Given');

    $nav = $this->buildSideNav();
    $nav->setCrumbs(
      $this->buildApplicationCrumbs()
        ->addCrumb(
          id(new PhabricatorCrumbView())
            ->setName($title)));
    $nav->selectFilter('given/');

    $nav->appendChild($list);
    $nav->appendChild($pager);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }


}

<?php

final class PhamePostListController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $filter = $request->getURIData('filter');
    $bloggername = $request->getURIData('bloggername');

    $query = id(new PhamePostQuery())
      ->setViewer($viewer);

    $nav = $this->renderSideNavFilterView();
    $nodata = null;

    switch ($filter) {
      case 'draft':
        $query->withBloggerPHIDs(array($viewer->getPHID()));
        $query->withVisibility(PhamePost::VISIBILITY_DRAFT);
        $nodata = pht('You have no unpublished drafts.');
        $title = pht('Unpublished Drafts');
        $nav->selectFilter('post/draft');
        break;
      case 'blogger':
        if ($bloggername) {
          $blogger = id(new PhabricatorUser())->loadOneWhere(
            'username = %s',
            $bloggername);
          if (!$blogger) {
            return new Aphront404Response();
          }
        } else {
          $blogger = $viewer;
        }

        $query->withBloggerPHIDs(array($blogger->getPHID()));
        if ($blogger->getPHID() == $viewer->getPHID()) {
          $nav->selectFilter('post');
          $nodata = pht('You have not written any posts.');
        } else {
          $nodata = pht('%s has not written any posts.', $blogger);
        }
        $title = pht('Posts by %s', $blogger);
        break;
      default:
      case 'all':
        $nodata = pht('There are no visible posts.');
        $title = pht('Posts');
        $nav->selectFilter('post/all');
        break;
    }

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $posts = $query->executeWithCursorPager($pager);

    $post_list = $this->renderPostList($posts, $viewer, $nodata);
    $post_list = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($post_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $this->getApplicationURI());

    $nav->appendChild(
      array(
        $crumbs,
        $post_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $title,
      ));
  }

}

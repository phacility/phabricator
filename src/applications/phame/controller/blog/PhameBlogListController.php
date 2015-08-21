<?php

final class PhameBlogListController extends PhameController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $nav = $this->renderSideNavFilterView(null);
    $filter = $request->getURIData('filter');
    $filter = $nav->selectFilter('blog/'.$filter, 'blog/user');

    $query = id(new PhameBlogQuery())
      ->setViewer($user);

    switch ($filter) {
      case 'blog/all':
        $title = pht('All Blogs');
        $nodata = pht('No blogs have been created.');
        break;
      case 'blog/user':
        $title = pht('Joinable Blogs');
        $nodata = pht('There are no blogs you can contribute to.');
        $query->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_JOIN,
          ));
        break;
      default:
        throw new Exception(pht("Unknown filter '%s'!", $filter));
    }

    $pager = id(new PHUIPagerView())
      ->setURI($request->getRequestURI(), 'offset')
      ->setOffset($request->getInt('offset'));

    $blogs = $query->executeWithOffsetPager($pager);

    $blog_list = $this->renderBlogList($blogs, $user, $nodata);
    $blog_list->setPager($pager);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setObjectList($blog_list);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title, $this->getApplicationURI());

    $nav->appendChild(
      array(
        $crumbs,
        $box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function renderBlogList(
    array $blogs,
    PhabricatorUser $viewer,
    $nodata) {

    $view = new PHUIObjectItemListView();
    $view->setNoDataString($nodata);
    $view->setUser($viewer);
    foreach ($blogs as $blog) {

      $id = $blog->getID();
      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($blog)
        ->setHeader($blog->getName())
        ->setStatusIcon('fa-star')
        ->setHref($this->getApplicationURI("/blog/view/{$id}/"))
        ->addAttribute($blog->getSkin())
        ->addAttribute($blog->getDomain());

      $view->addItem($item);
    }

    return $view;
  }

}

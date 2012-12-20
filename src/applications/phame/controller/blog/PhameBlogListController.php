<?php

/**
 * @group phame
 */
final class PhameBlogListController extends PhameController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->renderSideNavFilterView(null);
    $filter = $nav->selectFilter('blog/'.$this->filter, 'blog/user');

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
        throw new Exception("Unknown filter '{$filter}'!");
    }

    $pager = id(new AphrontPagerView())
      ->setURI($request->getRequestURI(), 'offset')
      ->setOffset($request->getInt('offset'));

    $blogs = $query->executeWithOffsetPager($pager);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $blog_list = $this->renderBlogList($blogs, $user, $nodata);
    $blog_list->setPager($pager);

    $nav->appendChild(
      array(
        $header,
        $blog_list,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $title,
        'device'  => true,
      ));
  }

  private function renderBlogList(
    array $blogs,
    PhabricatorUser $user,
    $nodata) {

    $view = new PhabricatorObjectItemListView();
    $view->setNoDataString($nodata);
    $view->setUser($user);
    foreach ($blogs as $blog) {

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($blog->getName())
        ->setHref($this->getApplicationURI('blog/view/'.$blog->getID().'/'))
        ->setObject($blog);

      $view->addItem($item);
    }

    return $view;
  }

}

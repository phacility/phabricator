<?php

final class PhabricatorMacroListController
  extends PhabricatorMacroController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter', 'active');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = new PhabricatorMacroQuery();
    $query->setViewer($viewer);

    $filter = $request->getStr('name');
    if (strlen($filter)) {
      $query->withNameLike($filter);
    }

    $authors = $request->getArr('authors');

    if ($authors) {
      $query->withAuthorPHIDs($authors);
    }

    $has_search = $filter || $authors;

    if ($this->filter == 'my') {
      $query->withAuthorPHIDs(array($viewer->getPHID()));
      // For pre-filling the tokenizer
      $authors = array($viewer->getPHID());
    }

    if ($this->filter == 'active') {
      $query->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE);
    }

    $macros = $query->executeWithCursorPager($pager);
    if ($has_search) {
      $nodata = pht('There are no macros matching the filter.');
    } else {
      $nodata = pht('There are no image macros yet.');
    }

    if ($authors) {
      $author_phids = array_fuse($authors);
    } else {
      $author_phids = array();
    }

    $files = mpull($macros, 'getFile');
    if ($files) {
      $author_phids += mpull($files, 'getAuthorPHID', 'getAuthorPHID');
    }

    $this->loadHandles($author_phids);
    $author_handles = array_select_keys($this->getLoadedHandles(), $authors);

    $filter_form = id(new AphrontFormView())
      ->setMethod('GET')
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($filter))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setDatasource('/typeahead/common/users/')
          ->setValue(mpull($author_handles, 'getFullName')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Filter Image Macros')));

    $filter_view = new AphrontListFilterView();
    $filter_view->appendChild($filter_form);

    $nav = $this->buildSideNavView(
      $for_app = false,
      $has_search);
    $nav->selectFilter($has_search ? 'search' : $this->filter);

    $nav->appendChild($filter_view);

    $pinboard = new PhabricatorPinboardView();
    $pinboard->setNoDataString($nodata);
    foreach ($macros as $macro) {
      $file = $macro->getFile();

      $item = new PhabricatorPinboardItemView();
      if ($file) {
        $item->setImageURI($file->getThumb280x210URI());
        $item->setImageSize(280, 210);
        if ($file->getAuthorPHID()) {
          $author_handle = $this->getHandle($file->getAuthorPHID());
          $item->appendChild(
            pht('Created by %s', $author_handle->renderLink()));
        }
        $datetime = phabricator_date($file->getDateCreated(), $viewer);
        $item->appendChild(
          phutil_tag(
            'div',
            array(),
            pht('Created on %s', $datetime)));
      }
      $item->setURI($this->getApplicationURI('/view/'.$macro->getID().'/'));
      $item->setHeader($macro->getName());

      $pinboard->addItem($item);
    }
    $nav->appendChild($pinboard);

    if (!$has_search) {
      $nav->appendChild($pager);
      switch ($this->filter) {
        case 'all':
          $name = pht('All Macros');
          break;
        case 'my':
          $name = pht('My Macros');
          break;
        case 'active':
          $name = pht('Active Macros');
          break;
        default:
          throw new Exception("Unknown filter $this->filter");
          break;
      }
    } else {
      $name = pht('Search');
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($name)
        ->setHref($request->getRequestURI()));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'device' => true,
        'title' => pht('Image Macros'),
        'dust' => true,
      ));
  }
}

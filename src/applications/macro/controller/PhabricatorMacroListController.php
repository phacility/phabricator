<?php

final class PhabricatorMacroListController
  extends PhabricatorMacroController {

  public function processRequest() {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $macro_table = new PhabricatorFileImageMacro();
    $file_table = new PhabricatorFile();
    $conn = $macro_table->establishConnection('r');

    $where = array();

    $join = array();
    $join[] = qsprintf($conn, '%T m', $macro_table->getTableName());

    $filter = $request->getStr('name');
    if (strlen($filter)) {
      $where[] = qsprintf($conn, 'm.name LIKE %~', $filter);
    }

    $authors = $request->getArr('authors');
    if ($authors) {
      $join[] = qsprintf(
        $conn,
        '%T f ON m.filePHID = f.phid',
        $file_table->getTableName());
      $where[] = qsprintf($conn, 'f.authorPHID IN (%Ls)', $authors);
    }

    $has_search = $where;

    if ($has_search) {
      $macros = queryfx_all(
        $conn,
        'SELECT m.* FROM  %Q WHERE %Q',
        implode(' JOIN ', $join),
        implode(' AND ', $where));
      $macros = $macro_table->loadAllFromArray($macros);
      $nodata = pht('There are no macros matching the filter.');
    } else {
      $pager = new AphrontPagerView();
      $pager->setOffset($request->getInt('page'));

      $macros = $macro_table->loadAllWhere(
        '1 = 1 ORDER BY id DESC LIMIT %d, %d',
        $pager->getOffset(),
        $pager->getPageSize());

      // Get an exact count since the size here is reasonably going to be a few
      // thousand at most in any reasonable case.
      $count = queryfx_one(
        $conn,
        'SELECT COUNT(*) N FROM %T',
        $macro_table->getTableName());
      $count = $count['N'];

      $pager->setCount($count);
      $pager->setURI($request->getRequestURI(), 'page');

      $nodata = pht('There are no image macros yet.');
    }

    if ($authors) {
      $author_phids = array_fuse($authors);
    } else {
      $author_phids = array();
    }

    $file_phids = mpull($macros, 'getFilePHID');

    $files = array();
    if ($file_phids) {
      $files = $file_table->loadAllWhere(
        "phid IN (%Ls)",
        $file_phids);
      $author_phids += mpull($files, 'getAuthorPHID', 'getAuthorPHID');
    }
    $files_map = mpull($files, null, 'getPHID');

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
    $nav->selectFilter($has_search ? 'search' : '/');

    $nav->appendChild($filter_view);

    $pinboard = new PhabricatorPinboardView();
    $pinboard->setNoDataString($nodata);
    foreach ($macros as $macro) {
      $file_phid = $macro->getFilePHID();
      $file = idx($files_map, $file_phid);

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
      $name = pht('All Macros');
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

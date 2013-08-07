<?php

/**
 * @group paste
 */
final class PhabricatorPasteListController extends PhabricatorPasteController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorPasteSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $pastes,
    PhabricatorSavedQuery $query) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $user = $this->getRequest()->getUser();

    $this->loadHandles(mpull($pastes, 'getAuthorPHID'));

    $lang_map = PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);
    foreach ($pastes as $paste) {
      $created = phabricator_date($paste->getDateCreated(), $user);
      $author = $this->getHandle($paste->getAuthorPHID())->renderLink();
      $source_code = $this->buildSourceCodeView($paste, 5)->render();

      $source_code = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-source-code-summary',
        ),
        $source_code);

      $line_count = count(explode("\n", $paste->getContent()));
      $line_count = pht(
        '%s Line(s)',
        new PhutilNumber($line_count));

      $title = nonempty($paste->getTitle(), pht('(An Untitled Masterwork)'));

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName('P'.$paste->getID())
        ->setHeader($title)
        ->setHref('/P'.$paste->getID())
        ->setObject($paste)
        ->addByline(pht('Author: %s', $author))
        ->addIcon('none', $line_count)
        ->appendChild($source_code);

      $lang_name = $paste->getLanguage();
      if ($lang_name) {
        $lang_name = idx($lang_map, $lang_name, $lang_name);
        $item->addIcon('none', $lang_name);
      }

      $list->addItem($item);
    }

    return $list;
  }

}

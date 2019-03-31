<?php

final class PhabricatorProfileMenuItemViewList
  extends Phobject {

  private $engine;
  private $views = array();
  private $selectedView;

  public function setProfileMenuEngine(PhabricatorProfileMenuEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getProfileMenuEngine() {
    return $this->engine;
  }

  public function addItemView(PhabricatorProfileMenuItemView $view) {
    $this->views[] = $view;
    return $this;
  }

  public function getItemViews() {
    return $this->views;
  }

  public function setSelectedView(PhabricatorProfileMenuItemView $view) {
    $found = false;
    foreach ($this->getItemViews() as $item_view) {
      if ($view === $item_view) {
        $found = true;
        break;
      }
    }

    if (!$found) {
      throw new Exception(
        pht(
          'Provided view is not one of the views in the list: you can only '.
          'select a view which appears in the list.'));
    }

    $this->selectedView = $view;

    return $this;
  }

  public function setSelectedViewWithItemIdentifier($identifier) {
    $views = $this->getViewsWithItemIdentifier($identifier);

    if (!$views) {
      throw new Exception(
        pht(
          'No views match identifier "%s"!',
          $identifier));
    }

    return $this->setSelectedView(head($views));
  }

  public function getViewsWithItemIdentifier($identifier) {
    $views = $this->getItemViews();

    $results = array();
    foreach ($views as $view) {
      $config = $view->getMenuItemConfiguration();

      if (!$config->matchesIdentifier($identifier)) {
        continue;
      }

      $results[] = $view;
    }

    return $results;
  }

  public function getDefaultViews() {
    $engine = $this->getProfileMenuEngine();
    $can_pin = $engine->isMenuEnginePinnable();

    $views = $this->getItemViews();

    // Remove all the views which were built by an item that can not be the
    // default item.
    foreach ($views as $key => $view) {
      $config = $view->getMenuItemConfiguration();

      if (!$config->canMakeDefault()) {
        unset($views[$key]);
        continue;
      }
    }

    // Remove disabled views.
    foreach ($views as $key => $view) {
      if ($view->getDisabled()) {
        unset($views[$key]);
      }
    }

    // If this engine supports pinning items and we have candidate views from a
    // valid pinned item, they are the default views.
    if ($can_pin) {
      $pinned = array();

      foreach ($views as $key => $view) {
        $config = $view->getMenuItemConfiguration();

        if ($config->isDefault()) {
          $pinned[] = $view;
          continue;
        }
      }

      if ($pinned) {
        return $pinned;
      }
    }

    // Return whatever remains that's still valid.
    return $views;
  }

  public function newNavigationView() {
    $engine = $this->getProfileMenuEngine();

    $base_uri = $engine->getItemURI('');
    $base_uri = new PhutilURI($base_uri);

    $navigation = id(new AphrontSideNavFilterView())
      ->setIsProfileMenu(true)
      ->setBaseURI($base_uri);

    $views = $this->getItemViews();
    $selected_item = null;
    $item_key = 0;
    $items = array();
    foreach ($views as $view) {
      $list_item = $view->newListItemView();

      // Assign unique keys to the list items. These keys are purely internal.
      $list_item->setKey(sprintf('item(%d)', $item_key++));

      if ($this->selectedView) {
        if ($this->selectedView === $view) {
          $selected_item = $list_item;
        }
      }

      $navigation->addMenuItem($list_item);
      $items[] = $list_item;
    }

    if (!$views) {
      // If the navigation menu has no items, add an empty label item to
      // force it to render something.
      $empty_item = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LABEL);
      $navigation->addMenuItem($empty_item);
    }

    $highlight_key = $this->getHighlightedItemKey(
      $items,
      $selected_item);
    $navigation->selectFilter($highlight_key);

    return $navigation;
  }

  private function getHighlightedItemKey(
    array $items,
    PHUIListItemView $selected_item = null) {

    assert_instances_of($items, 'PHUIListItemView');

    $default_key = null;
    if ($selected_item) {
      $default_key = $selected_item->getKey();
    }

    $engine = $this->getProfileMenuEngine();
    $controller = $engine->getController();

    // In some rare cases, when like building the "Favorites" menu on a
    // 404 page, we may not have a controller. Just accept whatever default
    // behavior we'd otherwise end up with.
    if (!$controller) {
      return $default_key;
    }

    $request = $controller->getRequest();

    // See T12949. If one of the menu items is a link to the same URI that
    // the page was accessed with, we want to highlight that item. For example,
    // this allows you to add links to a menu that apply filters to a
    // workboard.

    $matches = array();
    foreach ($items as $item) {
      $href = $item->getHref();
      if ($this->isMatchForRequestURI($request, $href)) {
        $matches[] = $item;
      }
    }

    foreach ($matches as $match) {
      if ($match->getKey() === $default_key) {
        return $default_key;
      }
    }

    if ($matches) {
      return head($matches)->getKey();
    }

    return $default_key;
  }

  private function isMatchForRequestURI(AphrontRequest $request, $item_uri) {
    $request_uri = $request->getAbsoluteRequestURI();
    $item_uri = new PhutilURI($item_uri);

    // If the request URI and item URI don't have matching paths, they
    // do not match.
    if ($request_uri->getPath() !== $item_uri->getPath()) {
      return false;
    }

    // If the request URI and item URI don't have matching parameters, they
    // also do not match. We're specifically trying to let "?filter=X" work
    // on Workboards, among other use cases, so this is important.
    $request_params = $request_uri->getQueryParamsAsPairList();
    $item_params = $item_uri->getQueryParamsAsPairList();
    if ($request_params !== $item_params) {
      return false;
    }

    // If the paths and parameters match, the item domain must be: empty; or
    // match the request domain; or match the production domain.

    $request_domain = $request_uri->getDomain();

    $production_uri = PhabricatorEnv::getProductionURI('/');
    $production_domain = id(new PhutilURI($production_uri))
      ->getDomain();

    $allowed_domains = array(
      '',
      $request_domain,
      $production_domain,
    );
    $allowed_domains = array_fuse($allowed_domains);

    $item_domain = $item_uri->getDomain();
    $item_domain = (string)$item_domain;

    if (isset($allowed_domains[$item_domain])) {
      return true;
    }

    return false;
  }

}

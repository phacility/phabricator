<?php

abstract class PhabricatorDirectoryController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setBaseURI('/');
    $page->setTitle(idx($data, 'title'));

    $page->setGlyph("\xE2\x9A\x92");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildNav() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/'));

    $applications = PhabricatorApplication::getAllInstalledApplications();

    foreach ($applications as $key => $application) {
      if (!$application->shouldAppearInLaunchView()) {
        // Remove hidden applications (usually internal stuff).
        unset($applications[$key]);
      }
      $invisible = PhabricatorApplication::TILE_INVISIBLE;
      if ($application->getDefaultTileDisplay($user) == $invisible) {
        // Remove invisible applications (e.g., admin apps for non-admins).
        unset($applications[$key]);
      }
    }

    $status = array();
    foreach ($applications as $key => $application) {
      $status[get_class($application)] = $application->loadStatus($user);
    }

    $tile_groups = array();
    $prefs = $user->loadPreferences()->getPreference(
      PhabricatorUserPreferences::PREFERENCE_APP_TILES,
      array());
    foreach ($applications as $key => $application) {
      $display = idx(
        $prefs,
        get_class($application),
        $application->getDefaultTileDisplay($user));
      $tile_groups[$display][] = $application;
    }

    $tile_groups = array_select_keys(
      $tile_groups,
      array(
        PhabricatorApplication::TILE_FULL,
        PhabricatorApplication::TILE_SHOW,
        PhabricatorApplication::TILE_HIDE,
      ));

    foreach ($tile_groups as $tile_display => $tile_group) {
      if (!$tile_group) {
        continue;
      }

      $tile_group = msort($tile_group, 'getApplicationOrder');

      $is_small_tiles = ($tile_display == PhabricatorApplication::TILE_SHOW) ||
                        ($tile_display == PhabricatorApplication::TILE_HIDE);

      if ($is_small_tiles) {
        $groups = PhabricatorApplication::getApplicationGroups();
        $tile_group = mgroup($tile_group, 'getApplicationGroup');
        $tile_group = array_select_keys($tile_group, array_keys($groups));
      } else {
        $tile_group = array($tile_group);
      }

      $is_hide = ($tile_display == PhabricatorApplication::TILE_HIDE);
      if ($is_hide) {
        $show_item_id = celerity_generate_unique_node_id();
        $show_tiles_id = celerity_generate_unique_node_id();

        $show_item = id(new PhabricatorMenuItemView())
          ->setName(pht('Show More Applications'))
          ->setHref('#')
          ->addSigil('home-show-applications')
          ->setID($show_item_id);

        $hide_item = id(new PhabricatorMenuItemView())
          ->setName(pht('Show Fewer Applications'))
          ->setHref('#')
          ->addSigil('home-hide-applications');

        $nav->addMenuItem($show_item);
        $nav->addCustomBlock(
          '<div '.
          'id="'.phutil_escape_html($show_tiles_id).'"'.
          'style="display: none;">');

        Javelin::initBehavior('phabricator-home-reveal-tiles', array(
          'tilesID' => $show_tiles_id,
          'showID'  => $show_item_id,
        ));
      }

      foreach ($tile_group as $group => $application_list) {
        $tiles = array();
        foreach ($application_list as $key => $application) {
          $tile = id(new PhabricatorApplicationLaunchView())
            ->setApplication($application)
            ->setApplicationStatus(
              idx($status, get_class($application), array()))
            ->setUser($user);

          if ($tile_display == PhabricatorApplication::TILE_FULL) {
            $tile->setFullWidth(true);
          }

          $tiles[] = $tile;
        }

        if ($is_small_tiles) {
          while (count($tiles) % 3) {
            $tiles[] = id(new PhabricatorApplicationLaunchView());
          }
          $nav->addLabel($groups[$group]);
        }
        $nav->addCustomBlock(
          phutil_render_tag(
            'div',
            array(
              'class' => 'application-tile-group',
            ),
            id(new AphrontNullView())->appendChild($tiles)->render()));
      }

      $is_hide = ($tile_display == PhabricatorApplication::TILE_HIDE);
      if ($is_hide) {
        $nav->addMenuItem($hide_item);
        $nav->addCustomBlock('</div>');
      }
    }

  $nav->addFilter(
      '',
      pht('Customize Applications...'),
      '/settings/panel/home/');
    $nav->addClass('phabricator-side-menu-home');
    $nav->selectFilter(null);

    return $nav;
  }

}

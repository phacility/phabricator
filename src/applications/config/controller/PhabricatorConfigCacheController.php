<?php

final class PhabricatorConfigCacheController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('cache/');

    $title = pht('Cache Status');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Cache Status'));

    $nav->setCrumbs($crumbs);

    list($remedy, $properties) = $this->getProperties();

    $property_list = id(new PHUIPropertyListView());
    foreach ($properties as $property) {
      list($name, $value) = $property;
      $property_list->addProperty($name, $value);
    }


    $box = id(new PHUIObjectBoxView())
      ->setFormErrors($remedy)
      ->setHeaderText(pht('Cache'))
      ->addPropertyList($property_list);

    $nav->appendChild($box);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function getProperties() {
    $remedy = array();

    $properties = array();

    // NOTE: If APCu is installed, it reports that APC is installed.
    if (extension_loaded('apc') && !extension_loaded('apcu')) {
      $cache_installed = true;
      $cache_name = pht('APC');
      $cache_version = phpversion('apc');
      $cache_enabled = (bool)ini_get('apc.enabled');
      if (!$cache_enabled) {
        $remedy[] = pht('Enable APC');
      }
      $datacache_installed = true;
      $datacache_name = pht('APC User Cache');
      $datacache_version = phpversion('apc');
      $datacache_enabled = true;
    } else {
      if (extension_loaded('Zend OPcache')) {
        $cache_installed = true;
        $cache_name = pht('Zend Opcache');
        $cache_enabled = (bool)ini_get('opcache.enable');
        $cache_version = phpversion('Zend OPcache');
        if (!$cache_enabled) {
          $remedy[] = pht('Enable Opcache.');
        }
      } else {
        if (version_compare(phpversion(), '5.5', '>=')) {
          $remedy[] = pht('Install OPcache.');
        } else {
          $remedy[] = pht('Install APC.');
        }

        $cache_installed = false;
        $cache_name = pht('None');
        $cache_enabled = false;
        $cache_version = null;
      }

      if (extension_loaded('apcu')) {
        $datacache_installed = true;
        $datacache_name = pht('APCu');
        $datacache_version = phpversion('apcu');
        $datacache_enabled = (bool)ini_get('apc.enabled');
      } else {
        if (version_compare(phpversion(), '5.5', '>=')) {
          $remedy[] = pht('Install APCu.');
        } else {
          // We already suggested installing APC above.
        }

        $datacache_installed = false;
        $datacache_name = pht('None');
        $datacache_version = null;
        $datacache_enabled = false;
      }
    }

    if ($cache_installed) {
      $cache_property = $this->renderYes($cache_name);
    } else {
      $cache_property = $this->renderNo($cache_name);
    }

    if ($cache_enabled) {
      $cache_enabled_property = $this->renderYes(pht('Enabled'));
    } else {
      $cache_enabled_property = $this->renderNo(pht('Not Enabled'));
    }

    $properties[] = array(pht('Opcode Cache'), $cache_property);
    $properties[] = array(pht('Enabled'), $cache_enabled_property);
    if ($cache_version) {
      $properties[] = array(
        pht('Version'),
        $this->renderInfo($cache_version),
      );
    }

    if ($datacache_installed) {
      $datacache_property = $this->renderYes($datacache_name);
    } else {
      $datacache_property = $this->renderNo($datacache_name);
    }

    if ($datacache_enabled) {
      $datacache_enabled_property = $this->renderYes(pht('Enabled'));
    } else {
      $datacache_enabled_property = $this->renderNo(pht('Not Enabled'));
    }

    $properties[] = array(pht('Data Cache'), $datacache_property);
    $properties[] = array(pht('Enabled'), $datacache_enabled_property);
    if ($datacache_version) {
      $properties[] = array(
        pht('Version'),
        $this->renderInfo($datacache_version),
      );
    }


    return array($remedy, $properties);
  }

  private function renderYes($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-check', 'green'),
      ' ',
      $info,
    );
  }

  private function renderNo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-times-circle', 'red'),
      ' ',
      $info,
    );
  }

  private function renderInfo($info) {
    return array(
      id(new PHUIIconView())->setIconFont('fa-info-circle', 'grey'),
      ' ',
      $info,
    );
  }

}

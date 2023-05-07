<?php

final class PhabricatorConfigConsoleController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setViewer($viewer)
      ->setBig(true);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Settings'))
        ->setHref($this->getApplicationURI('settings/'))
        ->setImageIcon('fa-wrench')
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Review and modify configuration settings.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Setup Issues'))
        ->setHref($this->getApplicationURI('issue/'))
        ->setImageIcon('fa-exclamation-triangle')
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Show unresolved issues with setup and configuration.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Services'))
        ->setHref($this->getApplicationURI('cluster/databases/'))
        ->setImageIcon('fa-server')
        ->setClickable(true)
        ->addAttribute(
          pht(
            'View status information for databases, caches, repositories, '.
            'and other services.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Extensions/Modules'))
        ->setHref($this->getApplicationURI('module/'))
        ->setImageIcon('fa-gear')
        ->setClickable(true)
        ->addAttribute(
          pht(
            'Show installed extensions and modules.')));

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Console'))
      ->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Configuration'))
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setObjectList($menu);

    $versions = $this->newLibraryVersionTable($viewer);
    $binary_versions = $this->newBinaryVersionTable();

    $launcher_view = id(new PHUILauncherView())
      ->appendChild($box)
      ->appendChild($versions)
      ->appendChild($binary_versions);

    $view = id(new PHUITwoColumnView())
      ->setFooter($launcher_view);

    return $this->newPage()
      ->setTitle(pht('Configuration'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  public function newLibraryVersionTable() {
    $viewer = $this->getViewer();

    $versions = $this->loadVersions($viewer);

    $rows = array();
    foreach ($versions as $name => $info) {
      $branchpoint = $info['branchpoint'];
      if ($branchpoint !== null && strlen($branchpoint)) {
        $branchpoint = substr($branchpoint, 0, 12);
      } else {
        $branchpoint = null;
      }

      $version = $info['hash'];
      if ($version !== null && strlen($version)) {
        $version = substr($version, 0, 12);
      } else {
        $version = pht('Unknown');
      }


      $epoch = $info['epoch'];
      if ($epoch) {
        $epoch = phabricator_date($epoch, $viewer);
      } else {
        $epoch = null;
      }

      $rows[] = array(
        $name,
        $version,
        $epoch,
        $branchpoint,
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Library'),
          pht('Version'),
          pht('Date'),
          pht('Branchpoint'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          null,
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Version Information'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($table_view);
  }

  private function loadVersions(PhabricatorUser $viewer) {
    $specs = array(
      'phabricator',
      'arcanist',
    );

    $all_libraries = PhutilBootloader::getInstance()->getAllLibraries();
    // This puts the core libraries at the top:
    $other_libraries = array_diff($all_libraries, $specs);
    $specs = array_merge($specs, $other_libraries);

    $log_futures = array();
    $remote_futures = array();

    foreach ($specs as $lib) {
      $root = dirname(phutil_get_library_root($lib));

      $log_command = csprintf(
        'git log --format=%s -n 1 --',
        '%H %ct');

      $remote_command = csprintf(
        'git remote -v');

      $log_futures[$lib] = id(new ExecFuture('%C', $log_command))
        ->setCWD($root);

      $remote_futures[$lib] = id(new ExecFuture('%C', $remote_command))
        ->setCWD($root);
    }

    $all_futures = array_merge($log_futures, $remote_futures);

    id(new FutureIterator($all_futures))
      ->resolveAll();

    // A repository may have a bunch of remotes, but we're only going to look
    // for remotes we host to try to figure out where this repository branched.
    $upstream_pattern = '(github\.com/phacility/|secure\.phabricator\.com/)';

    $upstream_futures = array();
    $lib_upstreams = array();
    foreach ($specs as $lib) {
      $remote_future = $remote_futures[$lib];

      list($err, $stdout) = $remote_future->resolve();
      if ($err) {
        // If this fails for whatever reason, just move on.
        continue;
      }

      // These look like this, with a tab separating the first two fields:
      // remote-name     http://remote.uri/ (push)

      $upstreams = array();

      $remotes = phutil_split_lines($stdout, false);
      foreach ($remotes as $remote) {
        $remote_pattern = '/^([^\t]+)\t([^ ]+) \(([^)]+)\)\z/';
        $matches = null;
        if (!preg_match($remote_pattern, $remote, $matches)) {
          continue;
        }

        // Remote URIs are either "push" or "fetch": we only care about "fetch"
        // URIs.
        $type = $matches[3];
        if ($type != 'fetch') {
          continue;
        }

        $uri = $matches[2];
        $is_upstream = preg_match($upstream_pattern, $uri);
        if (!$is_upstream) {
          continue;
        }

        $name = $matches[1];
        $upstreams[$name] = $name;
      }

      // If we have several suitable upstreams, try to pick the one named
      // "origin", if it exists. Otherwise, just pick the first one.
      if (isset($upstreams['origin'])) {
        $upstream = $upstreams['origin'];
      } else if ($upstreams) {
        $upstream = head($upstreams);
      } else {
        $upstream = null;
      }

      if (!$upstream) {
        continue;
      }

      $lib_upstreams[$lib] = $upstream;

      $merge_base_command = csprintf(
        'git merge-base HEAD %s/master --',
        $upstream);

      $root = dirname(phutil_get_library_root($lib));

      $upstream_futures[$lib] = id(new ExecFuture('%C', $merge_base_command))
        ->setCWD($root);
    }

    if ($upstream_futures) {
      id(new FutureIterator($upstream_futures))
        ->resolveAll();
    }

    $results = array();
    foreach ($log_futures as $lib => $future) {
      list($err, $stdout) = $future->resolve();
      if (!$err) {
        list($hash, $epoch) = explode(' ', $stdout);
      } else {
        $hash = null;
        $epoch = null;
      }

      $result = array(
        'hash' => $hash,
        'epoch' => $epoch,
        'upstream' => null,
        'branchpoint' => null,
      );

      $upstream_future = idx($upstream_futures, $lib);
      if ($upstream_future) {
        list($err, $stdout) = $upstream_future->resolve();
        if (!$err) {
          $branchpoint = trim($stdout);
          if (strlen($branchpoint)) {
            // We only list a branchpoint if it differs from HEAD.
            if ($branchpoint != $hash) {
              $result['upstream'] = $lib_upstreams[$lib];
              $result['branchpoint'] = trim($stdout);
            }
          }
        }
      }

      $results[$lib] = $result;
    }

    return $results;
  }

  private function newBinaryVersionTable() {
    $rows = array();

    $rows[] = array(
      'php',
      phpversion(),
      php_sapi_name(),
    );

    $binaries = PhutilBinaryAnalyzer::getAllBinaries();
    foreach ($binaries as $binary) {
      if (!$binary->isBinaryAvailable()) {
        $binary_version = pht('Not Available');
        $binary_path = null;
      } else {
        $binary_version = $binary->getBinaryVersion();
        $binary_path = $binary->getBinaryPath();
      }

      $rows[] = array(
        $binary->getBinaryName(),
        $binary_version,
        $binary_path,
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Binary'),
          pht('Version'),
          pht('Path'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Other Version Information'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($table_view);
  }


}

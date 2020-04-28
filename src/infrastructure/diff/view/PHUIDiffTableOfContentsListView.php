<?php

final class PHUIDiffTableOfContentsListView extends AphrontView {

  private $items = array();
  private $authorityPackages;
  private $header;
  private $infoView;
  private $background;
  private $bare;

  private $components = array();

  public function addItem(PHUIDiffTableOfContentsItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function setAuthorityPackages(array $authority_packages) {
    assert_instances_of($authority_packages, 'PhabricatorOwnersPackage');
    $this->authorityPackages = $authority_packages;
    return $this;
  }

  public function getAuthorityPackages() {
    return $this->authorityPackages;
  }

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setInfoView(PHUIInfoView $infoview) {
    $this->infoView = $infoview;
    return $this;
  }

  public function setBare($bare) {
    $this->bare = $bare;
    return $this;
  }

  public function getBare() {
    return $this->bare;
  }

  public function render() {
    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-table-of-contents-css');

    Javelin::initBehavior('phabricator-tooltips');

    if ($this->getAuthorityPackages()) {
      $authority = mpull($this->getAuthorityPackages(), null, 'getPHID');
    } else {
      $authority = array();
    }

    $items = $this->items;
    $viewer = $this->getViewer();

    $item_map = array();

    $vector_tree = new ArcanistDiffVectorTree();
    foreach ($items as $item) {
      $item->setViewer($viewer);

      $changeset = $item->getChangeset();

      $old_vector = $changeset->getOldStatePathVector();
      $new_vector = $changeset->getNewStatePathVector();

      $tree_vector = $this->newTreeVector($old_vector, $new_vector);

      $item_map[implode("\n", $tree_vector)] = $item;

      $vector_tree->addVector($tree_vector);
    }
    $node_list = $vector_tree->newDisplayList();

    $node_map = array();
    foreach ($node_list as $node) {
      $path_vector = $node->getVector();
      $path_vector = implode("\n", $path_vector);
      $node_map[$path_vector] = $node;
    }

    // Mark all nodes which contain at least one path which exists in the new
    // state. Nodes we don't mark contain only deleted or moved files, so they
    // can be rendered with a less-prominent style.

    foreach ($node_map as $node_key => $node) {
      $item = idx($item_map, $node_key);

      if (!$item) {
        continue;
      }

      $changeset = $item->getChangeset();
      if (!$changeset->getIsLowImportanceChangeset()) {
        $node->setAncestralAttribute('important', true);
      }
    }

    $any_packages = false;
    $any_coverage = false;
    $any_context = false;

    $rows = array();
    $rowc = array();
    foreach ($node_map as $node_key => $node) {
      $display_vector = $node->getDisplayVector();
      $item = idx($item_map, $node_key);

      if ($item) {
        $changeset = $item->getChangeset();
        $icon = $changeset->newFileTreeIcon();
      } else {
        $changeset = null;
        $icon = id(new PHUIIconView())
          ->setIcon('fa-folder-open-o grey');
      }

      if ($node->getChildren()) {
        $old_dir = true;
        $new_dir = true;
      } else {
        // TODO: When properties are set on a directory in SVN directly, this
        // might be incorrect.
        $old_dir = false;
        $new_dir = false;
      }

      $display_view = $this->newComponentView(
        $icon,
        $display_vector,
        $old_dir,
        $new_dir,
        $item);

      $depth = $node->getDisplayDepth();

      $style = sprintf('padding-left: %dpx;', $depth * 16);

      if ($item) {
        $packages = $item->renderPackages();
      } else {
        $packages = null;
      }

      if ($packages) {
        $any_packages = true;
      }

      if ($item) {
        if ($item->getCoverage()) {
          $any_coverage = true;
        }
        $coverage = $item->renderCoverage();
        $modified_coverage = $item->renderModifiedCoverage();
      } else {
        $coverage = null;
        $modified_coverage = null;
      }

      if ($item) {
        $context = $item->getContext();
        if ($context) {
          $any_context = true;
        }
      } else {
        $context = null;
      }

      if ($item) {
        $lines = $item->renderChangesetLines();
      } else {
        $lines = null;
      }

      $rows[] = array(
        $context,
        phutil_tag(
          'div',
          array(
            'style' => $style,
          ),
          $display_view),
        $lines,
        $coverage,
        $modified_coverage,
        $packages,
      );

      $classes = array();

      $have_authority = false;

      if ($item) {
        $packages = $item->getPackages();
        if ($packages) {
          if (array_intersect_key($packages, $authority)) {
            $have_authority = true;
          }
        }
      }

      if ($have_authority) {
        $classes[] = 'highlighted';
      }

      if (!$node->getAttribute('important')) {
        $classes[] = 'diff-toc-low-importance-row';
      }

      if ($changeset) {
        $classes[] = 'diff-toc-changeset-row';
      } else {
        $classes[] = 'diff-toc-no-changeset-row';
      }

      $rowc[] = implode(' ', $classes);
    }

    $table = id(new AphrontTableView($rows))
      ->setRowClasses($rowc)
      ->setClassName('aphront-table-view-compact')
      ->setHeaders(
        array(
          null,
          pht('Path'),
          pht('Size'),
          pht('Coverage (All)'),
          pht('Coverage (Touched)'),
          pht('Packages'),
        ))
      ->setColumnClasses(
        array(
          null,
          'diff-toc-path wide',
          'right',
          'differential-toc-cov',
          'differential-toc-cov',
          null,
        ))
      ->setColumnVisibility(
        array(
          $any_context,
          true,
          true,
          $any_coverage,
          $any_coverage,
          $any_packages,
        ))
      ->setDeviceVisibility(
        array(
          true,
          true,
          false,
          false,
          false,
          true,
        ));

    $anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('toc')
      ->setNavigationMarker(true);

    if ($this->bare) {
      return $table;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Table of Contents'));

    if ($this->header) {
      $header = $this->header;
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground($this->background)
      ->setTable($table)
      ->appendChild($anchor);

    if ($this->infoView) {
      $box->setInfoView($this->infoView);
    }

    return $box;
  }

  private function newTreeVector($old, $new) {
    if ($old === null && $new === null) {
      throw new Exception(pht('Changeset has no path vectors!'));
    }

    $vector = null;
    if ($old === null) {
      $vector = $new;
    } else if ($new === null) {
      $vector = $old;
    } else if ($old === $new) {
      $vector = $new;
    }

    if ($vector) {
      foreach ($vector as $k => $v) {
        $vector[$k] = $this->newScalarComponent($v);
      }
      return $vector;
    }

    $matrix = id(new PhutilEditDistanceMatrix())
      ->setSequences($old, $new)
      ->setComputeString(true);
    $edits = $matrix->getEditString();

    // If the edit sequence contains deletions followed by edits, move
    // the deletions to the end to left-align the new path.
    $edits = preg_replace('/(d+)(x+)/', '\2\1', $edits);

    $vector = array();
    $length = strlen($edits);

    $old_cursor = 0;
    $new_cursor = 0;

    for ($ii = 0; $ii < strlen($edits); $ii++) {
      $c = $edits[$ii];
      switch ($c) {
        case 'i':
          $vector[] = $this->newPairComponent(null, $new[$new_cursor]);
          $new_cursor++;
          break;
        case 'd':
          $vector[] = $this->newPairComponent($old[$old_cursor], null);
          $old_cursor++;
          break;
        case 's':
        case 'x':
        case 't':
          $vector[] = $this->newPairComponent(
            $old[$old_cursor],
            $new[$new_cursor]);
          $old_cursor++;
          $new_cursor++;
          break;
        default:
          throw new Exception(pht('Unknown edit string "%s"!', $c));
      }
    }

    return $vector;
  }

  private function newScalarComponent($v) {
    $key = sprintf('path(%s)', $v);

    if (!isset($this->components[$key])) {
      $this->components[$key] = $v;
    }

    return $key;
  }

  private function newPairComponent($u, $v) {
    if ($u === $v) {
      return $this->newScalarComponent($u);
    }

    $key = sprintf('pair(%s > %s)', $u, $v);

    if (!isset($this->components[$key])) {
      $this->components[$key] = array($u, $v);
    }

    return $key;
  }

  private function newComponentView(
    $icon,
    array $keys,
    $old_dir,
    $new_dir,
    $item) {

    $is_simple = true;

    $items = array();
    foreach ($keys as $key) {
      $component = $this->components[$key];

      if (is_array($component)) {
        $is_simple = false;
      } else {
        $component = array(
          $component,
          $component,
        );
      }

      $items[] = $component;
    }

    $move_icon = id(new PHUIIconView())
      ->setIcon('fa-angle-double-right pink');

    $old_row = array(
      phutil_tag('td', array(), $move_icon),
    );
    $new_row = array(
      phutil_tag('td', array(), $icon),
    );

    $last_old_key = null;
    $last_new_key = null;

    foreach ($items as $key => $component) {
      if (!is_array($component)) {
        $last_old_key = $key;
        $last_new_key = $key;
      } else {
        if ($component[0] !== null) {
          $last_old_key = $key;
        }
        if ($component[1] !== null) {
          $last_new_key = $key;
        }
      }
    }

    foreach ($items as $key => $component) {
      if (!is_array($component)) {
        $old = $component;
        $new = $component;
      } else {
        $old = $component[0];
        $new = $component[1];
      }

      $old_classes = array();
      $new_classes = array();

      if ($old === $new) {
        // Do nothing.
      } else if ($old === null) {
        $new_classes[] = 'diff-path-component-new';
      } else if ($new === null) {
        $old_classes[] = 'diff-path-component-old';
      } else {
        $old_classes[] = 'diff-path-component-old';
        $new_classes[] = 'diff-path-component-new';
      }

      if ($old !== null) {
        if (($key === $last_old_key) && !$old_dir) {
          // Do nothing.
        } else {
          $old = $old.'/';
        }
      }

      if ($new !== null) {
        if (($key === $last_new_key) && $item) {
          $new = $item->newLink();
        } else if (($key === $last_new_key) && !$new_dir) {
          // Do nothing.
        } else {
          $new = $new.'/';
        }
      }

      $old_row[] = phutil_tag(
        'td',
        array(),
        phutil_tag(
          'div',
          array(
            'class' => implode(' ', $old_classes),
          ),
          $old));
      $new_row[] = phutil_tag(
        'td',
        array(),
        phutil_tag(
          'div',
          array(
            'class' => implode(' ', $new_classes),
          ),
          $new));
    }

    $old_row = phutil_tag(
      'tr',
      array(
        'class' => 'diff-path-old',
      ),
      $old_row);

    $new_row = phutil_tag(
      'tr',
      array(
        'class' => 'diff-path-new',
      ),
      $new_row);

    $rows = array();
    $rows[] = $new_row;
    if (!$is_simple) {
      $rows[] = $old_row;
    }

    $body = phutil_tag('tbody', array(), $rows);

    $table = phutil_tag(
      'table',
      array(
      ),
      $body);

    return $table;
  }

}

<?php

final class DivinerAtomController extends DivinerController {

  private $bookName;
  private $atomType;
  private $atomName;
  private $atomContext;
  private $atomIndex;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->bookName = $data['book'];
    $this->atomType = $data['type'];
    $this->atomName = $data['name'];
    $this->atomContext = nonempty(idx($data, 'context'), null);
    $this->atomIndex = nonempty(idx($data, 'index'), null);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $book = id(new DivinerBookQuery())
      ->setViewer($viewer)
      ->withNames(array($this->bookName))
      ->executeOne();

    if (!$book) {
      return new Aphront404Response();
    }

    // TODO: This query won't load ghosts, because they'll fail `needAtoms()`.
    // Instead, we might want to load ghosts and render a message like
    // "this thing existed in an older version, but no longer does", especially
    // if we add content like comments.

    $symbol = id(new DivinerAtomQuery())
      ->setViewer($viewer)
      ->withBookPHIDs(array($book->getPHID()))
      ->withTypes(array($this->atomType))
      ->withNames(array($this->atomName))
      ->withContexts(array($this->atomContext))
      ->withIndexes(array($this->atomIndex))
      ->needAtoms(true)
      ->needExtends(true)
      ->needChildren(true)
      ->executeOne();

    if (!$symbol) {
      return new Aphront404Response();
    }

    $atom = $symbol->getAtom();
    $crumbs = $this->buildApplicationCrumbs();

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($book->getShortTitle())
        ->setHref('/book/'.$book->getName().'/'));

    $atom_short_title = $atom->getDocblockMetaValue(
      'short',
      $symbol->getTitle());

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($atom_short_title));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($symbol->getTitle())
      ->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLUE)
          ->setName(DivinerAtom::getAtomTypeNameString($atom->getType())))
      ->setSubheader($this->renderFullSignature($symbol));

    $properties = id(new PhabricatorPropertyListView());

    $group = $atom->getProperty('group');
    if ($group) {
      $group_name = $book->getGroupName($group);
    } else {
      $group_name = null;
    }

    $this->buildDefined($properties, $symbol);
    $this->buildExtendsAndImplements($properties, $symbol);

    $warnings = $atom->getWarnings();
    if ($warnings) {
      $warnings = id(new AphrontErrorView())
        ->setErrors($warnings)
        ->setTitle(pht('Documentation Warnings'))
        ->setSeverity(AphrontErrorView::SEVERITY_WARNING);
    }

    $methods = $this->composeMethods($symbol);

    $field = 'default';
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer)
      ->addObject($symbol, $field);
    foreach ($methods as $method) {
      foreach ($method['atoms'] as $matom) {
        $engine->addObject($matom, $field);
      }
    }
    $engine->process();

    $content = $this->renderDocumentationText($symbol, $engine);

    $toc = $engine->getEngineMetadata(
      $symbol,
      $field,
      PhutilRemarkupEngineRemarkupHeaderBlockRule::KEY_HEADER_TOC,
      array());

    $document = id(new PHUIDocumentView())
      ->setBook($book->getTitle(), $group_name)
      ->setHeader($header)
      ->appendChild($properties)
      ->appendChild($warnings)
      ->appendChild($content);

    $document->appendChild($this->buildParametersAndReturn(array($symbol)));

    if ($methods) {
      $tasks = $this->composeTasks($symbol);

      if ($tasks) {
        $methods_by_task = igroup($methods, 'task');

        $section = id(new DivinerSectionView())
          ->setHeader(pht('Tasks'));

        if (isset($methods_by_task[''])) {
          $tasks[''] = array(
            'name' => '',
            'title' => pht('Other Methods'),
            'defined' => $symbol,
          );
        }

        foreach ($tasks as $spec) {
          $section->addContent(
            id(new PhabricatorHeaderView())
              ->setNoBackground(true)
              ->setHeader($spec['title']));

          $task_methods = idx($methods_by_task, $spec['name'], array());
            $inner_box = id(new PHUIBoxView())
              ->addPadding(PHUI::PADDING_LARGE_LEFT)
              ->addPadding(PHUI::PADDING_LARGE_RIGHT)
              ->addPadding(PHUI::PADDING_LARGE_BOTTOM);
            if ($task_methods) {
            $inner_box->appendChild(hsprintf('<ul class="diviner-list">'));
            foreach ($task_methods as $task_method) {
              $atom = last($task_method['atoms']);
              $inner_box->appendChild(
                hsprintf('<li>%s()</li>', $atom->getName()));
            }
            $inner_box->appendChild(hsprintf('</ul>'));
          } else {
            $no_methods = pht('No methods for this task.');
            $inner_box->appendChild(hsprintf('<em>%s</em>', $no_methods));
          }
          $section->addContent($inner_box);
        }
        $document->appendChild($section);
      }

      $section = id(new DivinerSectionView())
          ->setHeader(pht('Methods'));

      foreach ($methods as $spec) {
        $matom = last($spec['atoms']);
        $method_header = id(new PhabricatorHeaderView())
          ->setNoBackground(true)
          ->setHeader($matom->getName());

        $inherited = $spec['inherited'];
        if ($inherited) {
          $method_header->addTag(
            id(new PhabricatorTagView())
              ->setType(PhabricatorTagView::TYPE_STATE)
              ->setBackgroundColor(PhabricatorTagView::COLOR_GREY)
              ->setName(pht('Inherited')));
        }

        $method_header->setSubheader(
          $this->renderFullSignature($matom));

        $section->addContent(
          array(
            $method_header,
            $this->renderMethodDocumentationText($symbol, $spec, $engine),
            $this->buildParametersAndReturn($spec['atoms']),
          ));
      }
      $document->appendChild($section);
    }

    if ($toc) {
      $side = new PHUIListView();
      $side->addMenuItem(
        id(new PHUIListItemView())
          ->setName(pht('Contents'))
          ->setType(PHUIListItemView::TYPE_LABEL));
      foreach ($toc as $key => $entry) {
        $side->addMenuItem(
          id(new PHUIListItemView())
            ->setName($entry[1])
            ->setHref('#'.$key));
      }

      $document->setSideNav($side, PHUIDocumentView::NAV_TOP);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $document,
      ),
      array(
        'title' => $symbol->getTitle(),
        'device' => true,
      ));
  }

  private function buildExtendsAndImplements(
    PhabricatorPropertyListView $view,
    DivinerLiveSymbol $symbol) {

    $lineage = $this->getExtendsLineage($symbol);
    if ($lineage) {
      $lineage = mpull($lineage, 'getName');
      $lineage = implode(' > ', $lineage);
      $view->addProperty(pht('Extends'), $lineage);
    }

    $implements = $this->getImplementsLineage($symbol);
    if ($implements) {
      $items = array();
      foreach ($implements as $spec) {
        $via = $spec['via'];
        $iface = $spec['interface'];
        if ($via == $symbol) {
          $items[] = $iface->getName();
        } else {
          $items[] = $iface->getName().' (via '.$via->getName().')';
        }
      }

      $view->addProperty(
        pht('Implements'),
        phutil_implode_html(phutil_tag('br'), $items));
    }

  }

  private function getExtendsLineage(DivinerLiveSymbol $symbol) {
    foreach ($symbol->getExtends() as $extends) {
      if ($extends->getType() == 'class') {
        $lineage = $this->getExtendsLineage($extends);
        $lineage[] = $extends;
        return $lineage;
      }
    }
    return array();
  }

  private function getImplementsLineage(DivinerLiveSymbol $symbol) {
    $implements = array();

    // Do these first so we get interfaces ordered from most to least specific.
    foreach ($symbol->getExtends() as $extends) {
      if ($extends->getType() == 'interface') {
        $implements[$extends->getName()] = array(
          'interface' => $extends,
          'via' => $symbol,
        );
      }
    }

    // Now do parent interfaces.
    foreach ($symbol->getExtends() as $extends) {
      if ($extends->getType() == 'class') {
        $implements += $this->getImplementsLineage($extends);
      }
    }

    return $implements;
  }

  private function buildDefined(
    PhabricatorPropertyListView $view,
    DivinerLiveSymbol $symbol) {

    $atom = $symbol->getAtom();
    $defined = $atom->getFile().':'.$atom->getLine();

    $link = $symbol->getBook()->getConfig('uri.source');
    if ($link) {
      $link = strtr(
        $link,
        array(
          '%%' => '%',
          '%f' => phutil_escape_uri($atom->getFile()),
          '%l' => phutil_escape_uri($atom->getLine()),
        ));
      $defined = phutil_tag(
        'a',
        array(
          'href' => $link,
          'target' => '_blank',
        ),
        $defined);
    }

    $view->addProperty(pht('Defined'), $defined);
  }

  private function composeMethods(DivinerLiveSymbol $symbol) {
    $methods = $this->findMethods($symbol);
    if (!$methods) {
      return $methods;
    }

    foreach ($methods as $name => $method) {
      // Check for "@task" on each parent, to find the most recently declared
      // "@task".
      $task = null;
      foreach ($method['atoms'] as $key => $method_symbol) {
        $atom = $method_symbol->getAtom();
        if ($atom->getDocblockMetaValue('task')) {
          $task = $atom->getDocblockMetaValue('task');
        }
      }
      $methods[$name]['task'] = $task;

      // Set 'inherited' if this atom has no implementation of the method.
      if (last($method['implementations']) !== $symbol) {
        $methods[$name]['inherited'] = true;
      } else {
        $methods[$name]['inherited'] = false;
      }
    }

    return $methods;
  }

  private function findMethods(DivinerLiveSymbol $symbol) {
    $child_specs = array();
    foreach ($symbol->getExtends() as $extends) {
      if ($extends->getType() == DivinerAtom::TYPE_CLASS) {
        $child_specs = $this->findMethods($extends);
      }
    }

    foreach ($symbol->getChildren() as $child) {
      if ($child->getType() == DivinerAtom::TYPE_METHOD) {
        $name = $child->getName();
        if (isset($child_specs[$name])) {
          $child_specs[$name]['atoms'][] = $child;
          $child_specs[$name]['implementations'][] = $symbol;
        } else {
          $child_specs[$name] = array(
            'atoms' => array($child),
            'defined' => $symbol,
            'implementations' => array($symbol),
          );
        }
      }
    }

    return $child_specs;
  }

  private function composeTasks(DivinerLiveSymbol $symbol) {
    $extends_task_specs = array();
    foreach ($symbol->getExtends() as $extends) {
      $extends_task_specs += $this->composeTasks($extends);
    }

    $task_specs = array();

    $tasks = $symbol->getAtom()->getDocblockMetaValue('task');
    if (strlen($tasks)) {
      $tasks = phutil_split_lines($tasks, $retain_endings = false);

      foreach ($tasks as $task) {
        list($name, $title) = explode(' ', $task, 2);
        $name = trim($name);
        $title = trim($title);

        $task_specs[$name] = array(
          'name'        => $name,
          'title'       => $title,
          'defined'     => $symbol,
        );
      }
    }

    return $task_specs + $extends_task_specs;
  }

  private function renderFullSignature(DivinerLiveSymbol $symbol) {
    switch ($symbol->getType()) {
      case DivinerAtom::TYPE_CLASS:
      case DivinerAtom::TYPE_INTERFACE:
      case DivinerAtom::TYPE_METHOD:
      case DivinerAtom::TYPE_FUNCTION:
        break;
      default:
        return null;
    }

    $atom = $symbol->getAtom();

    $out = array();
    if ($atom->getProperty('final')) {
      $out[] = 'final';
    }

    if ($atom->getProperty('abstract')) {
      $out[] = 'abstract';
    }

    if ($atom->getProperty('access')) {
      $out[] = $atom->getProperty('access');
    }

    if ($atom->getProperty('static')) {
      $out[] = 'static';
    }

    switch ($symbol->getType()) {
      case DivinerAtom::TYPE_CLASS:
      case DivinerAtom::TYPE_INTERFACE:
        $out[] = $symbol->getType();
        break;
      case DivinerAtom::TYPE_FUNCTION:
        switch ($atom->getLanguage()) {
          case 'php':
            $out[] = $symbol->getType();
            break;
        }
        break;
      case DivinerAtom::TYPE_METHOD:
        switch ($atom->getLanguage()) {
          case 'php':
            $out[] = DivinerAtom::TYPE_FUNCTION;
            break;
        }
        break;
    }

    $out[] = $symbol->getName();

    $out = implode(' ', $out);

    $parameters = $atom->getProperty('parameters');
    if ($parameters !== null) {
      $pout = array();
      foreach ($parameters as $parameter) {
        $pout[] = $parameter['name'];
      }
      $out .= '('.implode(', ', $pout).')';
    }

    return $out;
  }

  private function buildParametersAndReturn(array $symbols) {
    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $symbols = array_reverse($symbols);
    $out = array();

    $collected_parameters = null;
    foreach ($symbols as $symbol) {
      $parameters = $symbol->getAtom()->getProperty('parameters');
      if ($parameters !== null) {
        if ($collected_parameters === null) {
          $collected_parameters = array();
        }
        foreach ($parameters as $key => $parameter) {
          if (isset($collected_parameters[$key])) {
            $collected_parameters[$key] += $parameter;
          } else {
            $collected_parameters[$key] = $parameter;
          }
        }
      }
    }

    if (nonempty($parameters)) {
      $out[] = id(new DivinerParameterTableView())
        ->setHeader(pht('Parameters'))
        ->setParameters($parameters);
    }

    $collected_return = null;
    foreach ($symbols as $symbol) {
      $return = $symbol->getAtom()->getProperty('return');
      if ($return) {
        if ($collected_return) {
          $collected_return += $return;
        } else {
          $collected_return = $return;
        }
      }
    }

    if (nonempty($return)) {
      $out[] = id(new DivinerReturnTableView())
        ->setHeader(pht('Return'))
        ->setReturn($collected_return);
    }

    return $out;
  }

  private function renderDocumentationText(
    DivinerLiveSymbol $symbol,
    PhabricatorMarkupEngine $engine) {

    $field = 'default';
    $content = $engine->getOutput($symbol, $field);

    if (strlen(trim($symbol->getMarkupText($field)))) {
      $content = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup',
        ),
        $content);
    } else {
      $atom = $symbol->getAtom();
      $undoc = DivinerAtom::getThisAtomIsNotDocumentedString($atom->getType());
      $content = id(new PHUIBoxView())
        ->addPadding(PHUI::PADDING_LARGE_LEFT)
        ->addPadding(PHUI::PADDING_LARGE_BOTTOM)
        ->addPadding(PHUI::PADDING_LARGE_RIGHT)
        ->appendChild(hsprintf('<em>%s</em>', $undoc));
    }

    return $content;
  }

  private function renderMethodDocumentationText(
    DivinerLiveSymbol $parent,
    array $spec,
    PhabricatorMarkupEngine $engine) {

    $symbols = array_values($spec['atoms']);
    $implementations = array_values($spec['implementations']);

    $field = 'default';

    $out = array();
    foreach ($symbols as $key => $symbol) {
      $impl = $implementations[$key];
      if ($impl !== $parent) {
        if (!strlen(trim($symbol->getMarkupText($field)))) {
          continue;
        }
        $out[] = id(new PHUIBoxView())
          ->addPadding(PHUI::PADDING_LARGE_LEFT)
          ->addPadding(PHUI::PADDING_LARGE_RIGHT)
          ->addClass('diviner-method-implementation-header')
          ->appendChild(
            pht('From parent implementation in %s:', $impl->getName()));
      } else if ($out) {
        $out[] = id(new PHUIBoxView())
          ->addPadding(PHUI::PADDING_LARGE_LEFT)
          ->addPadding(PHUI::PADDING_LARGE_RIGHT)
          ->addClass('diviner-method-implementation-header')
          ->appendChild(
            pht('From this implementation:'));
      }
      $out[] = $this->renderDocumentationText($symbol, $engine);
    }

    // If we only have inherited implementations but none have documentation,
    // render the last one here so we get the "this thing has no documentation"
    // element.
    if (!$out) {
      $out[] = $this->renderDocumentationText($symbol, $engine);
    }

    return $out;
  }

}

<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Like an @{class:AphrontSideNavView}, but with a little bit of logic for the
 * common case where you're using the side nav to filter some view of objects.
 *
 * For example:
 *
 *    $nav = new AphrontSideNavFilterView();
 *    $nav
 *      ->setBaseURI($some_uri)
 *      ->addLabel('Cats')
 *      ->addFilter('meow', 'Meow')
 *      ->addFilter('purr', 'Purr')
 *      ->addSpacer()
 *      ->addLabel('Dogs')
 *      ->addFilter('woof', 'Woof')
 *      ->addFilter('bark', 'Bark');
 *    $valid_filter = $nav->selectFilter($user_selection, $default = 'meow');
 *
 */
final class AphrontSideNavFilterView extends AphrontView {

  private $items = array();
  private $baseURI;
  private $selectedFilter = false;
  private $flexNav;
  private $flexible;

  public function setFlexNav($flex_nav) {
    $this->flexNav = $flex_nav;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function addFilter($key, $name, $uri = null, $relative = false) {
    $this->items[] = array(
      'filter', $key, $name, 'uri' => $uri, 'relative' => $relative);
    return $this;
  }

  public function addFilters(array $views) {
    foreach ($views as $view) {
      $uri = isset($view['uri']) ? $view['uri'] : null;
      $relative = isset($view['relative']) ? $view['relative'] : false;
      $this->addFilter(
        $view['key'],
        $view['name'],
        $uri,
        $relative);
    }
  }

  public function addLabel($name) {
    $this->items[] = array('label', null, $name);
    return $this;
  }

  public function addSpacer() {
    $this->items[] = array('spacer', null, null);
    return $this;
  }

  public function setBaseURI(PhutilURI $uri) {
    $this->baseURI = $uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function selectFilter($key, $default = null) {
    $this->selectedFilter = $default;
    if ($key !== null) {
      foreach ($this->items as $item) {
        if ($item[0] == 'filter') {
          if ($item[1] == $key) {
            $this->selectedFilter = $key;
            break;
          }
        }
      }
    }
    return $this->selectedFilter;
  }

  public function render() {
    if (!$this->baseURI) {
      throw new Exception("Call setBaseURI() before render()!");
    }
    if ($this->selectedFilter === false) {
      throw new Exception("Call selectFilter() before render()!");
    }

    $view = new AphrontSideNavView();
    $view->setFlexNav($this->flexNav);
    $view->setFlexible($this->flexible);
    foreach ($this->items as $item) {
      list($type, $key, $name) = $item;
      switch ($type) {
        case 'spacer':
          $view->addNavItem('<br />');
          break;
        case 'label':
          $view->addNavItem(
            phutil_render_tag(
              'span',
              array(),
              phutil_escape_html($name)));
          break;
        case 'filter':
          $class = ($key == $this->selectedFilter)
            ? 'aphront-side-nav-selected'
            : null;

          if (empty($item['uri'])) {
            $href = clone $this->baseURI;
            $href->setPath($href->getPath().$key.'/');
            $href = (string)$href;
          } else {
            if (empty($item['relative'])) {
              $href = $item['uri'];
            } else {
              $href = clone $this->baseURI;
              $href->setPath($href->getPath().$item['uri']);
              $href = (string)$href;
            }
          }

          $view->addNavItem(
            phutil_render_tag(
              'a',
              array(
                'href'  => $href,
                'class' => $class,
              ),
              phutil_escape_html($name)));
          break;
        default:
          throw new Exception("Unknown item type '{$type}'.");
      }
    }
    $view->appendChild($this->renderChildren());

    return $view->render();
  }

}

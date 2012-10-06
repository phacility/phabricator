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

final class AphrontSideNavView extends AphrontView {

  private $items = array();
  private $flexNav;
  private $isFlexible;
  private $showApplicationMenu;
  private $user;
  private $currentApplication;
  private $active;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setShowApplicationMenu($show_application_menu) {
    $this->showApplicationMenu = $show_application_menu;
    return $this;
  }

  public function setCurrentApplication(PhabricatorApplication $current) {
    $this->currentApplication = $current;
    return $this;
  }

  public function addNavItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function setFlexNav($flex) {
    $this->flexNav = $flex;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->isFlexible = $flexible;
    return $this;
  }

  public function setActive($active) {
    $this->active = $active;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->items);

    if ($this->flexNav) {
      $user = $this->user;

      require_celerity_resource('phabricator-nav-view-css');

      $nav_classes = array();
      $nav_classes[] = 'phabricator-nav';

      $app_id = celerity_generate_unique_node_id();
      $nav_id = null;
      $drag_id = null;
      $content_id = celerity_generate_unique_node_id();
      $local_id = null;
      $local_menu = null;
      $main_id = celerity_generate_unique_node_id();

      $apps = $this->renderApplications();

      $app_menu = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-nav-col phabricator-nav-app',
          'id'    => $app_id,
        ),
        $apps->render());

      if ($this->isFlexible) {
        $drag_id = celerity_generate_unique_node_id();
        $flex_bar = phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-nav-drag',
            'id' => $drag_id,
          ),
          '');
      } else {
        $flex_bar = null;
      }

      $nav_menu = null;
      if ($this->items) {
        $local_id = celerity_generate_unique_node_id();
        $nav_classes[] = 'has-local-nav';
        $local_menu = phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-nav-col phabricator-nav-local',
            'id'    => $local_id,
          ),
          $view->render());
      }

      Javelin::initBehavior(
        'phabricator-nav',
        array(
          'mainID'      => $main_id,
          'appID'       => $app_id,
          'localID'     => $local_id,
          'dragID'      => $drag_id,
          'contentID'   => $content_id,
        ));

      if ($this->active && $local_id) {
        Javelin::initBehavior(
          'phabricator-active-nav',
          array(
            'localID' => $local_id,
          ));
      }

      $header_part =
        '<div class="phabricator-nav-head">'.
          '<div class="phabricator-nav-head-tablet">'.
            '<a href="#" class="nav-button nav-button-w nav-button-menu" '.
              'id="tablet-menu1"></a>'.
            '<a href="#" class="nav-button nav-button-e nav-button-content '.
              'nav-button-selected" id="tablet-menu2"></a>'.
          '</div>'.
          '<div class="phabricator-nav-head-phone">'.
            '<a href="#" class="nav-button nav-button-w nav-button-apps" '.
              'id="phone-menu1"></button>'.
            '<a href="#" class="nav-button nav-button-menu" '.
              'id="phone-menu2"></button>'.
            '<a href="#" class="nav-button nav-button-e nav-button-content '.
              'nav-button-selected" id="phone-menu3"></button>'.
          '</div>'.
        '</div>';

      return $header_part.phutil_render_tag(
        'div',
        array(
          'class' => implode(' ', $nav_classes),
          'id'    => $main_id,
        ),
        $app_menu.
        $local_menu.
        $flex_bar.
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-nav-content',
            'id' => $content_id,
          ),
          $this->renderChildren()));
    } else {

      require_celerity_resource('aphront-side-nav-view-css');

      return
        '<table class="aphront-side-nav-view">'.
          '<tr>'.
            '<th class="aphront-side-nav-navigation">'.
              $view->render().
            '</th>'.
            '<td class="aphront-side-nav-content">'.
              $this->renderChildren().
            '</td>'.
          '</tr>'.
        '</table>';
    }
  }

  private function renderApplications() {
    $core = array();
    $current = $this->currentApplication;

    $meta = null;

    $group_core = PhabricatorApplication::GROUP_CORE;

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      if ($application instanceof PhabricatorApplicationApplications) {
        $meta = $application;
        continue;
      }
      if ($application->getApplicationGroup() != $group_core) {
        continue;
      }
      if ($application->getApplicationOrder() !== null) {
        $core[] = $application;
      }
    }

    $core = msort($core, 'getApplicationOrder');
    if ($meta) {
      $core[] = $meta;
    }
    $core = mpull($core, null, 'getPHID');

    if ($current && empty($core[$current->getPHID()])) {
      array_unshift($core, $current);
    }

    Javelin::initBehavior('phabricator-tooltips', array());
    require_celerity_resource('aphront-tooltip-css');

    $apps = array();
    foreach ($core as $phid => $application) {
      $classes = array();
      $classes[] = 'phabricator-nav-app-item';

      if ($current && $phid == $current->getPHID()) {
        $selected = true;
      } else {
        $selected = false;
      }

      $iclasses = array();
      $iclasses[] = 'phabricator-nav-app-item-icon';
      $style = null;
      if ($application->getIconURI()) {
        $style = 'background-image: url('.$application->getIconURI().'); '.
                 'background-size: 30px auto;';
      } else {
        $iclasses[] = 'autosprite';
        $sprite = $application->getAutospriteName();
        if ($selected) {
          $sprite .= '-selected';
        }
        $iclasses[] = 'app-'.$sprite;
      }

      $icon = phutil_render_tag(
        'span',
        array(
          'class' => implode(' ', $iclasses),
          'style' => $style,
        ),
        '');

      $apps[] = javelin_render_tag(
        'a',
        array(
          'class' => implode(' ', $classes),
          'href' => $application->getBaseURI(),
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $application->getName(),
            'align' => 'E',
          ),
        ),
        $icon.
        phutil_escape_html($application->getName()));
    }

    return id(new AphrontNullView())->appendChild($apps);
  }

}

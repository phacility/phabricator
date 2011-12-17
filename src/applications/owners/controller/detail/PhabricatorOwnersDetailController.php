<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorOwnersDetailController extends PhabricatorOwnersController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $package = id(new PhabricatorOwnersPackage())->load($this->id);
    if (!$package) {
      return new Aphront404Response();
    }

    $paths = $package->loadPaths();
    $owners = $package->loadOwners();

    $phids = array();
    foreach ($paths as $path) {
      $phids[$path->getRepositoryPHID()] = true;
    }
    foreach ($owners as $owner) {
      $phids[$owner->getUserPHID()] = true;
    }
    $phids = array_keys($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $rows = array();

    $rows[] = array(
      'Name',
      phutil_escape_html($package->getName()));
    $rows[] = array(
      'Description',
      phutil_escape_html($package->getDescription()));

    $primary_owner = null;
    $primary_phid = $package->getPrimaryOwnerPHID();
    if ($primary_phid && isset($handles[$primary_phid])) {
      $primary_owner =
        '<strong>'.$handles[$primary_phid]->renderLink().'</strong>';
    }
    $rows[] = array(
      'Primary Owner',
      $primary_owner,
      );

    $owner_links = array();
    foreach ($owners as $owner) {
      $owner_links[] = $handles[$owner->getUserPHID()]->renderLink();
    }
    $owner_links = implode('<br />', $owner_links);
    $rows[] = array(
      'Owners',
      $owner_links);

    $rows[] = array(
      'Auditing',
      $package->getAuditingEnabled() ? 'Enabled' : 'Disabled',
    );

    $rows[] = array(
      'Related Commits',
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/related/view/all/?phid='.$package->getPHID(),
        ),
        phutil_escape_html('Related Commits'))
    );


    $path_links = array();
    foreach ($paths as $path) {
      $callsign = $handles[$path->getRepositoryPHID()]->getName();
      $repo = phutil_escape_html('r'.$callsign);
      $path_link = phutil_render_tag(
        'a',
        array(
          'href' => '/diffusion/'.$callsign.'/browse/:'.$path->getPath(),
        ),
        phutil_escape_html($path->getPath()));
      $path_links[] = $repo.' '.$path_link;
    }
    $path_links = implode('<br />', $path_links);
    $rows[] = array(
      'Paths',
      $path_links);

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'header',
        'wide',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader(
      'Package Details for "'.phutil_escape_html($package->getName()).'"');
    $panel->addButton(
      javelin_render_tag(
        'a',
        array(
          'href' => '/owners/delete/'.$package->getID().'/',
          'class' => 'button grey',
          'sigil' => 'workflow',
        ),
        'Delete Package'));
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/edit/'.$package->getID().'/',
          'class' => 'button',
        ),
        'Edit Package'));
    $panel->appendChild($table);

    $nav = new AphrontSideNavView();
    $nav->appendChild($panel);
    $nav->addNavItem(
      phutil_render_tag(
        'a',
        array(
          'href' => '/owners/package/'.$package->getID().'/',
          'class' => 'aphront-side-nav-selected',
        ),
        'Package Details'));

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => "Package '".$package->getName()."'",
      ));
  }

}

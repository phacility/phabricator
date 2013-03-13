<?php

/**
 * @group maniphest
 */
final class ManiphestSavedQueryListController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $nav = $this->buildBaseSideNav();

    $queries = id(new ManiphestSavedQuery())->loadAllWhere(
      'userPHID = %s ORDER BY name ASC',
      $user->getPHID());

    $default = null;

    if ($request->isFormPost()) {
      $new_default = null;
      foreach ($queries as $query) {
        if ($query->getID() == $request->getInt('default')) {
          $new_default = $query;
        }
      }

      if ($this->getDefaultQuery()) {
        $this->getDefaultQuery()->setIsDefault(0)->save();
      }
      if ($new_default) {
        $new_default->setIsDefault(1)->save();
      }

      return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
    }

    $rows = array();
    foreach ($queries as $query) {
      if ($query->getIsDefault()) {
        $default = $query;
      }
      $rows[] = array(
        phutil_tag(
          'input',
          array(
            'type'      => 'radio',
            'name'      => 'default',
            'value'     => $query->getID(),
            'checked'   => ($query->getIsDefault() ? 'checked' : null),
          )),
        phutil_tag(
          'a',
          array(
            'href' => '/maniphest/view/custom/?key='.$query->getQueryKey(),
          ),
          $query->getName()),
        phutil_tag(
          'a',
          array(
            'href'  => '/maniphest/custom/edit/'.$query->getID().'/',
            'class' => 'grey small button',
          ),
          'Edit'),
        javelin_tag(
          'a',
          array(
            'href'  => '/maniphest/custom/delete/'.$query->getID().'/',
            'class' => 'grey small button',
            'sigil' => 'workflow',
          ),
          'Delete'),
      );
    }

    $rows[] = array(
      phutil_tag(
        'input',
        array(
          'type'      => 'radio',
          'name'      => 'default',
          'value'     => 0,
          'checked'   => ($default === null ? 'checked' : null),
        )),
      phutil_tag('em', array(), pht('No Default')),
      '',
      '',
    );

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Default'),
        pht('Name'),
        pht('Edit'),
        pht('Delete'),
      ));
    $table->setColumnClasses(
      array(
        'radio',
        'wide pri',
        'action',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Saved Custom Queries'));
    $panel->addButton(
      phutil_tag(
        'button',
        array(),
        pht('Save Default Query')));
    $panel->appendChild($table);

    $form = phabricator_form(
      $user,
      array(
        'method' => 'POST',
        'action' => $request->getRequestURI(),
      ),
      $panel->render());

    $nav->selectFilter('saved', 'saved');
    $nav->appendChild($form);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Saved Queries'),
        'device' => true,
      ));
  }

}

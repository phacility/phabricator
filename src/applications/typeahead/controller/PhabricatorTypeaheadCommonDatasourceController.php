<?php

final class PhabricatorTypeaheadCommonDatasourceController
  extends PhabricatorTypeaheadDatasourceController {

  private $type;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->type = $data['type'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $query = $request->getStr('q');
    $raw_query = $request->getStr('raw');

    $need_users = false;
    $need_upforgrabs = false;
    $need_rich_data = false;
    switch ($this->type) {
      case 'searchowner':
        $need_users = true;
        $need_upforgrabs = true;
        break;
    }

    $results = array();

    if ($need_upforgrabs) {
      $results[] = id(new PhabricatorTypeaheadResult())
        ->setName('upforgrabs (Up For Grabs)')
        ->setPHID(ManiphestTaskOwner::OWNER_UP_FOR_GRABS);
    }

    if ($need_users) {
      $columns = array(
        'isSystemAgent',
        'isAdmin',
        'isDisabled',
        'userName',
        'realName',
        'phid');

      if ($query) {
        // This is an arbitrary limit which is just larger than any limit we
        // actually use in the application.

        // TODO: The datasource should pass this in the query.
        $limit = 15;

        $user_table = new PhabricatorUser();
        $conn_r = $user_table->establishConnection('r');
        $ids = queryfx_all(
          $conn_r,
          'SELECT id FROM %T WHERE username LIKE %>
            ORDER BY username ASC LIMIT %d',
          $user_table->getTableName(),
          $query,
          $limit);
        $ids = ipull($ids, 'id');

        if (count($ids) < $limit) {
          // If we didn't find enough username hits, look for real name hits.
          // We need to pull the entire pagesize so that we end up with the
          // right number of items if this query returns many duplicate IDs
          // that we've already selected.

          $realname_ids = queryfx_all(
            $conn_r,
            'SELECT DISTINCT userID FROM %T WHERE token LIKE %>
              ORDER BY token ASC LIMIT %d',
            PhabricatorUser::NAMETOKEN_TABLE,
            $query,
            $limit);
          $realname_ids = ipull($realname_ids, 'userID');
          $ids = array_merge($ids, $realname_ids);

          $ids = array_unique($ids);
          $ids = array_slice($ids, 0, $limit);
        }

        // Always add the logged-in user because some tokenizers autosort them
        // first. They'll be filtered out on the client side if they don't
        // match the query.
        $ids[] = $request->getUser()->getID();

        if ($ids) {
          $users = id(new PhabricatorUser())->loadColumnsWhere(
            $columns,
            'id IN (%Ld)',
            $ids);
        } else {
          $users = array();
        }
      } else {
        $users = id(new PhabricatorUser())->loadColumns($columns);
      }

      if ($need_rich_data) {
        $phids = mpull($users, 'getPHID');
        $handles = $this->loadViewerHandles($phids);
      }

      foreach ($users as $user) {
        $closed = null;
        if ($user->getIsDisabled()) {
          $closed = pht('Disabled');
        } else if ($user->getIsSystemAgent()) {
          $closed = pht('Bot/Script');
        }

        $result = id(new PhabricatorTypeaheadResult())
          ->setName($user->getFullName())
          ->setURI('/p/'.$user->getUsername())
          ->setPHID($user->getPHID())
          ->setPriorityString($user->getUsername())
          ->setIcon('fa-user bluegrey')
          ->setPriorityType('user')
          ->setClosed($closed);

        if ($need_rich_data) {
          $display_type = 'User';
          if ($user->getIsAdmin()) {
            $display_type = 'Administrator';
          }
          $result->setDisplayType($display_type);
          $result->setImageURI($handles[$user->getPHID()]->getImageURI());
        }
        $results[] = $result;
      }
    }

    $content = mpull($results, 'getWireFormat');

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    // If there's a non-Ajax request to this endpoint, show results in a tabular
    // format to make it easier to debug typeahead output.

    $rows = array();
    foreach ($results as $result) {
      $wire = $result->getWireFormat();
      $rows[] = $wire;
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Name',
        'URI',
        'PHID',
        'Priority',
        'Display Name',
        'Display Type',
        'Image URI',
        'Priority Type',
        'Sprite Class',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Typeahead Results');
    $panel->appendChild($table);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => pht('Typeahead Results'),
        'device' => true
      ));
  }

}

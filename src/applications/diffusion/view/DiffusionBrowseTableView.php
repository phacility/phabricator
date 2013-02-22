<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;
  private $handles = array();

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function renderLastModifiedColumns(
    DiffusionRequest $drequest,
    array $handles,
    PhabricatorRepositoryCommit $commit = null,
    PhabricatorRepositoryCommitData $data = null) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    if ($commit) {
      $epoch = $commit->getEpoch();
      $modified = DiffusionView::linkCommit(
        $drequest->getRepository(),
        $commit->getCommitIdentifier());
      $date = phabricator_date($epoch, $this->user);
      $time = phabricator_time($epoch, $this->user);
    } else {
      $modified = '';
      $date = '';
      $time = '';
    }

    if ($data) {
      $author_phid = $data->getCommitDetail('authorPHID');
      if ($author_phid && isset($handles[$author_phid])) {
        $author = $handles[$author_phid]->renderLink();
      } else {
        $author = self::renderName($data->getAuthorName());
      }

      $committer = $data->getCommitDetail('committer');
      if ($committer) {
        $committer_phid = $data->getCommitDetail('committerPHID');
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = self::renderName($committer);
        }
        if ($author != $committer) {
          $author = hsprintf('%s/%s', $author, $committer);
        }
      }

      $details = AphrontTableView::renderSingleDisplayLine($data->getSummary());
    } else {
      $author = '';
      $details = '';
    }

    $return = array(
      'commit'    => $modified,
      'date'      => $date,
      'time'      => $time,
      'author'    => $author,
      'details'   => $details,
    );

    $lint = self::loadLintMessagesCount($drequest);
    if ($lint !== null) {
      $return['lint'] = hsprintf(
        '<a href="%s">%s</a>',
        $drequest->generateURI(array(
          'action' => 'lint',
          'lint' => null,
        )),
        number_format($lint));
    }

    return $return;
  }

  private static function loadLintMessagesCount(DiffusionRequest $drequest) {
    $branch = $drequest->loadBranch();
    if (!$branch) {
      return null;
    }

    $conn = $drequest->getRepository()->establishConnection('r');

    $path = '/'.$drequest->getPath();
    $where = (substr($path, -1) == '/'
      ? qsprintf($conn, 'AND path LIKE %>', $path)
      : qsprintf($conn, 'AND path = %s', $path));

    if ($drequest->getLint()) {
      $where .= qsprintf($conn, ' AND code = %s', $drequest->getLint());
    }

    return head(queryfx_one(
      $conn,
      'SELECT COUNT(*) FROM %T WHERE branchID = %d %Q',
      PhabricatorRepository::TABLE_LINTMESSAGE,
      $branch->getID(),
      $where));
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    $base_path = trim($request->getPath(), '/');
    if ($base_path) {
      $base_path = $base_path.'/';
    }

    $need_pull = array();
    $rows = array();
    $show_edit = false;
    foreach ($this->paths as $path) {

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'text' => $this->renderPathIcon('dir', $browse_text),
          )));
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link = phutil_tag('strong', array(), $this->linkExternal(
          $path->getHash(),
          $path->getExternalURI(),
          $this->renderPathIcon('ext', $browse_text)));
      } else {
        if ($file_type == DifferentialChangeType::FILE_SYMLINK) {
          $type = 'link';
        } else {
          $type = 'file';
        }
        $browse_text = $path->getPath();
        $browse_link = $this->linkBrowse(
          $base_path.$path->getPath(),
          array(
            'text' => $this->renderPathIcon($type, $browse_text),
          ));
      }

      $commit = $path->getLastModifiedCommit();
      if ($commit) {
        $drequest = clone $request;
        $drequest->setPath($request->getPath().$path->getPath().$dir_slash);
        $dict = $this->renderLastModifiedColumns(
          $drequest,
          $this->handles,
          $commit,
          $path->getLastCommitData());
      } else {
        $dict = array(
          'lint'      => celerity_generate_unique_node_id(),
          'commit'    => celerity_generate_unique_node_id(),
          'date'      => celerity_generate_unique_node_id(),
          'time'      => celerity_generate_unique_node_id(),
          'author'    => celerity_generate_unique_node_id(),
          'details'   => celerity_generate_unique_node_id(),
        );

        $uri = (string)$request->generateURI(
          array(
            'action' => 'lastmodified',
            'path'   => $base_path.$path->getPath().$dir_slash,
          ));

        $need_pull[$uri] = $dict;
        foreach ($dict as $k => $uniq) {
          $dict[$k] = phutil_tag('span', array('id' => $uniq), '');
        }
      }

      $editor_button = '';
      if ($this->user) {
        $editor_link = $this->user->loadEditorLink(
          $base_path.$path->getPath(),
          1,
          $request->getRepository()->getCallsign());
        if ($editor_link) {
          $show_edit = true;
          $editor_button = phutil_tag(
            'a',
            array(
              'href' => $editor_link,
            ),
            'Edit');
        }
      }

      $rows[] = array(
        $this->linkHistory($base_path.$path->getPath().$dir_slash),
        $editor_button,
        $browse_link,
        idx($dict, 'lint'),
        $dict['commit'],
        $dict['date'],
        $dict['time'],
        $dict['author'],
        $dict['details'],
      );
    }

    if ($need_pull) {
      Javelin::initBehavior('diffusion-pull-lastmodified', $need_pull);
    }

    $branch = $this->getDiffusionRequest()->loadBranch();
    $show_lint = ($branch && $branch->getLintCommit());
    $lint = $request->getLint();

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        'History',
        'Edit',
        'Path',
        ($lint ? $lint : 'Lint'),
        'Modified',
        'Date',
        'Time',
        'Author/Committer',
        'Details',
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        'n',
        '',
        '',
        'right',
        '',
        'wide',
      ));
    $view->setColumnVisibility(
      array(
        true,
        $show_edit,
        true,
        $show_lint,
        true,
        true,
        true,
        true,
        true,
      ));
    return $view->render();
  }

  private function renderPathIcon($type, $text) {

    require_celerity_resource('diffusion-icons-css');

    return phutil_tag(
      'span',
      array(
        'class' => 'diffusion-path-icon diffusion-path-icon-'.$type,
      ),
      $text);
  }

}

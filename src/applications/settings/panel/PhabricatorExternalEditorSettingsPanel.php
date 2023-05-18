<?php

final class PhabricatorExternalEditorSettingsPanel
  extends PhabricatorEditEngineSettingsPanel {

  const PANELKEY = 'editor';

  public function getPanelName() {
    return pht('External Editor');
  }

  public function getPanelMenuIcon() {
    return 'fa-i-cursor';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsApplicationsPanelGroup::PANELGROUPKEY;
  }

  public function isTemplatePanel() {
    return true;
  }

  public function newSettingsPanelEditFormHeadContent(
    PhabricatorEditEnginePageState $state) {

    // The "Editor" setting stored in the database may be invalidated by
    // configuration or software changes. If a saved URI becomes invalid
    // (for example, its protocol is removed from the list of allowed
    // protocols), it will stop working.

    // If the stored value has a problem like this, show a static error
    // message without requiring the user to save changes.

    if ($state->getIsSubmit()) {
      return null;
    }

    $viewer = $this->getViewer();
    $pattern = $viewer->getUserSetting(PhabricatorEditorSetting::SETTINGKEY);

    if ($pattern === null || !strlen($pattern)) {
      return null;
    }

    $caught = null;
    try {
      id(new PhabricatorEditorURIEngine())
        ->setPattern($pattern)
        ->validatePattern();
    } catch (PhabricatorEditorURIParserException $ex) {
      $caught = $ex;
    }

    if (!$caught) {
      return null;
    }

    return id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->appendChild($caught->getMessage());
  }

  public function newSettingsPanelEditFormTailContent(
    PhabricatorEditEnginePageState $state) {
    $viewer = $this->getViewer();

    $variables = PhabricatorEditorURIEngine::getVariableDefinitions();

    $rows = array();
    foreach ($variables as $key => $variable) {
      $rows[] = array(
        phutil_tag('tt', array(), '%'.$key),
        $variable['name'],
        $variable['example'],
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Variable'),
          pht('Replaced With'),
          pht('Example'),
        ))
      ->setColumnClasses(
        array(
          'center',
          'pri',
          'wide',
        ));

    $variables_box = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setHeaderText(pht('External Editor URI Variables'))
      ->setTable($table);

    $label_map = array(
      'http' => pht('Hypertext Transfer Protocol'),
      'https' => pht('Hypertext Transfer Protocol over SSL'),
      'txmt' => pht('TextMate'),
      'mvim' => pht('MacVim'),
      'subl' => pht('Sublime Text'),
      'vim' => pht('Vim'),
      'emacs' => pht('Emacs'),
      'vscode' => pht('Visual Studio Code'),
      'editor' => pht('Generic Editor'),
      'idea' => pht('IntelliJ IDEA'),
    );

    $default_label = phutil_tag('em', array(), pht('Supported Protocol'));

    $config_key = 'uri.allowed-editor-protocols';

    $protocols = PhabricatorEnv::getEnvConfig($config_key);
    $protocols = array_keys($protocols);
    sort($protocols);

    $protocol_rows = array();
    foreach ($protocols as $protocol) {
      $label = idx($label_map, $protocol, $default_label);

      $protocol_rows[] = array(
        $protocol.'://',
        $label,
      );
    }

    $protocol_table = id(new AphrontTableView($protocol_rows))
      ->setNoDataString(
        pht(
          'No allowed editor protocols are configured.'))
      ->setHeaders(
        array(
          pht('Protocol'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          'wide',
        ));

    $is_admin = $viewer->getIsAdmin();

    $configure_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Configuration'))
      ->setIcon('fa-sliders')
      ->setHref(
        urisprintf(
          '/config/edit/%s/',
          $config_key))
      ->setDisabled(!$is_admin);

    $protocol_header = id(new PHUIHeaderView())
      ->setHeader(pht('Supported Editor Protocols'))
      ->addActionLink($configure_button);

    $protocols_box = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG)
      ->setHeader($protocol_header)
      ->setTable($protocol_table);

    return array(
      $variables_box,
      $protocols_box,
    );
  }

}

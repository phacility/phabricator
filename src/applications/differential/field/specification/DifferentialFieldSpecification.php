<?php

/**
 * Describes and implements the behavior for a custom field on Differential
 * revisions. Along with other configuration, you can extend this class to add
 * custom fields to Differential revisions and commit messages.
 *
 * Generally, you should implement all methods from the storage task and then
 * the methods from one or more interface tasks.
 *
 * @task storage Field Storage
 * @task edit Extending the Revision Edit Interface
 * @task view Extending the Revision View Interface
 * @task list Extending the Revision List Interface
 * @task mail Extending the E-mail Interface
 * @task conduit Extending the Conduit View Interface
 * @task commit Extending Commit Messages
 * @task load Loading Additional Data
 * @task context Contextual Data
 */
abstract class DifferentialFieldSpecification {

  private $revision;
  private $diff;
  private $manualDiff;
  private $handles;
  private $diffProperties;
  private $user;


/* -(  Storage  )------------------------------------------------------------ */


  /**
   * Return a unique string used to key storage of this field's value, like
   * "mycompany.fieldname" or similar. You can return null (the default) to
   * indicate that this field does not use any storage. This is appropriate for
   * display fields, like @{class:DifferentialLinesFieldSpecification}. If you
   * implement this, you must also implement @{method:getValueForStorage} and
   * @{method:setValueFromStorage}.
   *
   * @return string|null  Unique key which identifies this field in auxiliary
   *                      field storage. Maximum length is 32. Alternatively,
   *                      null (default) to indicate that this field does not
   *                      use auxiliary field storage.
   * @task storage
   */
  public function getStorageKey() {
    return null;
  }


  /**
   * Return a serialized representation of the field value, appropriate for
   * storing in auxiliary field storage. You must implement this method if
   * you implement @{method:getStorageKey}.
   *
   * @return string Serialized field value.
   * @task storage
   */
  public function getValueForStorage() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Set the field's value given a serialized storage value. This is called
   * when the field is loaded; if no data is available, the value will be
   * null. You must implement this method if you implement
   * @{method:getStorageKey}.
   *
   * @param string|null Serialized field representation (from
   *                    @{method:getValueForStorage}) or null if no value has
   *                    ever been stored.
   * @return this
   * @task storage
   */
  public function setValueFromStorage($value) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


/* -(  Extending the Revision Edit Interface  )------------------------------ */


  /**
   * Determine if this field should appear on the "Edit Revision" interface. If
   * you return true from this method, you must implement
   * @{method:setValueFromRequest}, @{method:renderEditControl} and
   * @{method:validateField}.
   *
   * For a concrete example of a field which implements an edit interface, see
   * @{class:DifferentialRevertPlanFieldSpecification}.
   *
   * @return bool True to indicate that this field implements an edit interface.
   * @task edit
   */
  public function shouldAppearOnEdit() {
    return false;
  }


  /**
   * Set the field's value from an HTTP request. Generally, you should read
   * the value of some field name you emitted in @{method:renderEditControl}
   * and save it into the object, e.g.:
   *
   *   $this->value = $request->getStr('my-custom-field');
   *
   * If you have some particularly complicated field, you may need to read
   * more data; this is why you have access to the entire request.
   *
   * You must implement this if you implement @{method:shouldAppearOnEdit}.
   *
   * You should not perform field validation here; instead, you should implement
   * @{method:validateField}.
   *
   * @param AphrontRequest HTTP request representing a user submitting a form
   *                       with this field in it.
   * @return this
   * @task edit
   */
  public function setValueFromRequest(AphrontRequest $request) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Build a renderable object (generally, some @{class:AphrontFormControl})
   * which can be appended to a @{class:AphrontFormView} and represents the
   * interface the user sees on the "Edit Revision" screen when interacting
   * with this field.
   *
   * For example:
   *
   *   return id(new AphrontFormTextControl())
   *     ->setLabel('Custom Field')
   *     ->setName('my-custom-key')
   *     ->setValue($this->value);
   *
   * You must implement this if you implement @{method:shouldAppearOnEdit}.
   *
   * @return AphrontView|string Something renderable.
   * @task edit
   */
  public function renderEditControl() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * This method will be called after @{method:setValueFromRequest} but before
   * the field is saved. It gives you an opportunity to inspect the field value
   * and throw a @{class:DifferentialFieldValidationException} if there is a
   * problem with the value the user has provided (for example, the value the
   * user entered is not correctly formatted). This method is also called after
   * @{method:setValueFromParsedCommitMessage} before the revision is saved.
   *
   * By default, fields are not validated.
   *
   * @return void
   * @task edit
   */
  public function validateField() {
    return;
  }

  /**
   * Determine if user mentions should be extracted from the value and added to
   * CC when creating revision. Mentions are then extracted from the string
   * returned by @{method:renderValueForCommitMessage}.
   *
   * By default, mentions are not extracted.
   *
   * @return bool
   * @task edit
   */
  public function shouldExtractMentions() {
    return false;
  }

  /**
   * Hook for applying revision changes via the editor. Normally, you should
   * not implement this, but a number of builtin fields use the revision object
   * itself as storage. If you need to do something similar for whatever reason,
   * this method gives you an opportunity to interact with the editor or
   * revision before changes are saved (for example, you can write the field's
   * value into some property of the revision).
   *
   * @param DifferentialRevisionEditor  Active editor which is applying changes
   *                                    to the revision.
   * @return void
   * @task edit
   */
  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    return;
  }

  /**
   * Hook after an edit operation has completed. This allows you to update
   * link tables or do other write operations which should happen after the
   * revision is saved. Normally you don't need to implement this.
   *
   *
   * @param DifferentialRevisionEditor  Active editor which has just applied
   *                                    changes to the revision.
   * @return void
   * @task edit
   */
  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    return;
  }


/* -(  Extending the Revision View Interface  )------------------------------ */


  /**
   * Determine if this field should appear on the revision detail view
   * interface. One use of this interface is to add purely informational
   * fields to the revision view, without any sort of backing storage.
   *
   * If you return true from this method, you must implement the methods
   * @{method:renderLabelForRevisionView} and
   * @{method:renderValueForRevisionView}.
   *
   * @return bool True if this field should appear when viewing a revision.
   * @task view
   */
  public function shouldAppearOnRevisionView() {
    return false;
  }


  /**
   * Return a string field label which will appear in the revision detail
   * table.
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnRevisionView}.
   *
   * @return string Label for field in revision detail view.
   * @task view
   */
  public function renderLabelForRevisionView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Return a markup block representing the field for the revision detail
   * view. Note that you can return null to suppress display (for instance,
   * if the field shows related objects of some type and the revision doesn't
   * have any related objects).
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnRevisionView}.
   *
   * @return string|null Display markup for field value, or null to suppress
   *                     field rendering.
   * @task view
   */
  public function renderValueForRevisionView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Load users, their current statuses and return a markup with links to the
   * user profiles and information about their current status.
   *
   * @return string Display markup.
   * @task view
   */
  public function renderUserList(array $user_phids) {
    if (!$user_phids) {
      return phutil_tag('em', array(), pht('None'));
    }

    return implode_selected_handle_links(', ',
      $this->getLoadedHandles(), $user_phids);
  }


  /**
   * Return a markup block representing a warning to display with the comment
   * box when preparing to accept a diff. A return value of null indicates no
   * warning box should be displayed for this field.
   *
   * @return string|null Display markup for warning box, or null for no warning
   */
  public function renderWarningBoxForRevisionAccept() {
    return null;
  }


/* -(  Extending the Revision List Interface  )------------------------------ */


  /**
   * Determine if this field should appear in the table on the revision list
   * interface.
   *
   * @return bool True if this field should appear in the table.
   *
   * @task list
   */
  public function shouldAppearOnRevisionList() {
    return false;
  }


  /**
   * Return a column header for revision list tables.
   *
   * @return string Column header.
   *
   * @task list
   */
  public function renderHeaderForRevisionList() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Optionally, return a column class for revision list tables.
   *
   * @return string CSS class for table cells.
   *
   * @task list
   */
  public function getColumnClassForRevisionList() {
    return null;
  }


  /**
   * Return a table cell value for revision list tables.
   *
   * @param DifferentialRevision The revision to render a value for.
   * @return string Table cell value.
   *
   * @task list
   */
  public function renderValueForRevisionList(DifferentialRevision $revision) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


/* -(  Extending the Diff View Interface  )------------------------------ */


  /**
   * Determine if this field should appear on the diff detail view
   * interface. One use of this interface is to add purely informational
   * fields to the diff view, without any sort of backing storage.
   *
   * NOTE: These diffs are not necessarily attached yet to a revision.
   * As such, a field on the diff view can not rely on the existence of a
   * revision or use storage attached to the revision.
   *
   * If you return true from this method, you must implement the methods
   * @{method:renderLabelForDiffView} and
   * @{method:renderValueForDiffView}.
   *
   * @return bool True if this field should appear when viewing a diff.
   * @task view
   */
  public function shouldAppearOnDiffView() {
    return false;
  }


  /**
   * Return a string field label which will appear in the diff detail
   * table.
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnDiffView}.
   *
   * @return string Label for field in revision detail view.
   * @task view
   */
  public function renderLabelForDiffView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * Return a markup block representing the field for the diff detail
   * view. Note that you can return null to suppress display (for instance,
   * if the field shows related objects of some type and the revision doesn't
   * have any related objects).
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnDiffView}.
   *
   * @return string|null Display markup for field value, or null to suppress
   *                     field rendering.
   * @task view
   */
  public function renderValueForDiffView() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


/* -(  Extending the E-mail Interface  )------------------------------------- */


  /**
   * Return plain text to render in e-mail messages. The text may span
   * multiple lines.
   *
   * @return int One of DifferentialMailPhase constants.
   * @return string|null Plain text, or null for no message.
   *
   * @task mail
   */
  public function renderValueForMail($phase) {
    return null;
  }


/* -(  Extending the Conduit Interface  )------------------------------------ */


  /**
   * @task conduit
   */
  public function shouldAppearOnConduitView() {
    return false;
  }

  /**
   * @task conduit
   */
  public function getValueForConduit() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * @task conduit
   */
  public function getKeyForConduit() {
    $key = $this->getStorageKey();
    if ($key === null) {
      throw new DifferentialFieldSpecificationIncompleteException($this);
    }
    return $key;
  }

/* -(  Extending the Search Interface  )------------------------------------ */

  /**
   * @task search
   */
  public function shouldAddToSearchIndex() {
    return false;
  }

  /**
   * @task search
   */
  public function getValueForSearchIndex() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * NOTE: Keys *must be* 4 characters for
   * @{class:PhabricatorSearchEngineMySQL}.
   *
   * @task search
   */
  public function getKeyForSearchIndex() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

/* -(  Extending Commit Messages  )------------------------------------------ */


  /**
   * Determine if this field should appear in commit messages. You should return
   * true if this field participates in any part of the commit message workflow,
   * even if it is not rendered by default.
   *
   * If you implement this method, you must implement
   * @{method:getCommitMessageKey} and
   * @{method:setValueFromParsedCommitMessage}.
   *
   * @return bool True if this field appears in commit messages in any capacity.
   * @task commit
   */
  public function shouldAppearOnCommitMessage() {
    return false;
  }

  /**
   * Key which identifies this field in parsed commit messages. Commit messages
   * exist in two forms: raw textual commit messages and parsed dictionaries of
   * fields. This method must return a unique string which identifies this field
   * in dictionaries. Principally, this dictionary is shipped to and from arc
   * over Conduit. Keys should be appropriate property names, like "testPlan"
   * (not "Test Plan") and must be globally unique.
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnCommitMessage}.
   *
   * @return string Key which identifies the field in dictionaries.
   * @task commit
   */
  public function getCommitMessageKey() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * Set this field's value from a value in a parsed commit message dictionary.
   * Afterward, this field will go through the normal write workflows and the
   * change will be permanently stored via either the storage mechanisms (if
   * your field implements them), revision write hooks (if your field implements
   * them) or discarded (if your field implements neither, e.g. is just a
   * display field).
   *
   * The value you receive will either be null or something you originally
   * returned from @{method:parseValueFromCommitMessage}.
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnCommitMessage}.
   *
   * @param mixed Field value from a parsed commit message dictionary.
   * @return this
   * @task commit
   */
  public function setValueFromParsedCommitMessage($value) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * In revision control systems which read revision information from the
   * working copy, the user may edit the commit message outside of invoking
   * "arc diff --edit". When they do this, only some fields (those fields which
   * can not be edited by other users) are safe to overwrite. For instance, it
   * is fine to overwrite "Summary" because no one else can edit it, but not
   * to overwrite "Reviewers" because reviewers may have been added or removed
   * via the web interface.
   *
   * If a field is safe to overwrite when edited in a working copy commit
   * message, return true. If the authoritative value should always be used,
   * return false. By default, fields can not be overwritten.
   *
   * arc will only attempt to overwrite field values if run with "--verbatim".
   *
   * @return bool True to indicate the field is save to overwrite.
   * @task commit
   */
  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return false;
  }

  /**
   * Return true if this field should be suggested to the user during
   * "arc diff --edit". Basicially, return true if the field is something the
   * user might want to fill out (like "Summary"), and false if it's a
   * system/display/readonly field (like "Differential Revision"). If this
   * method returns true, the field will be rendered even if it has no value
   * during edit and update operations.
   *
   * @return bool True to indicate the field should appear in the edit template.
   * @task commit
   */
  public function shouldAppearOnCommitMessageTemplate() {
    return true;
  }

  /**
   * Render a human-readable label for this field, like "Summary" or
   * "Test Plan". This is distinct from the commit message key, but generally
   * they should be similar.
   *
   * @return string Human-readable field label for commit messages.
   * @task commit
   */
  public function renderLabelForCommitMessage() {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * Render a human-readable value for this field when it appears in commit
   * messages (for instance, lists of users should be rendered as user names).
   *
   * The ##$is_edit## parameter allows you to distinguish between commit
   * messages being rendered for editing and those being rendered for amending
   * or commit. Some fields may decline to render a value in one mode (for
   * example, "Reviewed By" appears only when doing commit/amend, not while
   * editing).
   *
   * @param bool True if the message is being edited.
   * @return string Human-readable field value.
   * @task commit
   */
  public function renderValueForCommitMessage($is_edit) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }

  /**
   * Return one or more labels which this field parses in commit messages. For
   * example, you might parse all of "Task", "Tasks" and "Task Numbers" or
   * similar. This is just to make it easier to get commit messages to parse
   * when users are typing in the fields manually as opposed to using a
   * template, by accepting alternate spellings / pluralizations / etc. By
   * default, only the label returned from @{method:renderLabelForCommitMessage}
   * is parsed.
   *
   * @return list List of supported labels that this field can parse from commit
   *              messages.
   * @task commit
   */
  public function getSupportedCommitMessageLabels() {
    return array($this->renderLabelForCommitMessage());
  }

  /**
   * Parse a raw text block from a commit message into a canonical
   * representation of the field value. For example, the "CC" field accepts a
   * comma-delimited list of usernames and emails and parses them into valid
   * PHIDs, emitting a PHID list.
   *
   * If you encounter errors (like a nonexistent username) while parsing,
   * you should throw a @{class:DifferentialFieldParseException}.
   *
   * Generally, this method should accept whatever you return from
   * @{method:renderValueForCommitMessage} and parse it back into a sensible
   * representation.
   *
   * You must implement this method if you return true from
   * @{method:shouldAppearOnCommitMessage}.
   *
   * @param string
   * @return mixed The canonical representation of the field value. For example,
   *               you should lookup usernames and object references.
   * @task commit
   */
  public function parseValueFromCommitMessage($value) {
    throw new DifferentialFieldSpecificationIncompleteException($this);
  }


  /**
   * This method allows you to take action when a commit appears in a tracked
   * branch (for example, by closing tasks associated with the commit).
   *
   * @param PhabricatorRepository The repository the commit appeared in.
   * @param PhabricatorRepositoryCommit The commit itself.
   * @param PhabricatorRepostioryCommitData Commit data.
   * @return void
   *
   * @task commit
   */
  public function didParseCommit(
    PhabricatorRepository $repo,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {
    return;
  }

  public function getCommitMessageTips() {
      return array();
  }


/* -(  Loading Additional Data  )-------------------------------------------- */


  /**
   * Specify which @{class:PhabricatorObjectHandle}s need to be loaded for your
   * field to render correctly.
   *
   * This is a convenience method which makes the handles available on all
   * interfaces where the field appears. If your field needs handles on only
   * some interfaces (or needs different handles on different interfaces) you
   * can overload the more specific methods to customize which interfaces you
   * retrieve handles for. Requesting only the handles you need will improve
   * the performance of your field.
   *
   * You can later retrieve these handles by calling @{method:getHandle}.
   *
   * @return list List of PHIDs to load handles for.
   * @task load
   */
  protected function getRequiredHandlePHIDs() {
    return array();
  }


  /**
   * Specify which @{class:PhabricatorObjectHandle}s need to be loaded for your
   * field to render correctly on the view interface.
   *
   * This is a more specific version of @{method:getRequiredHandlePHIDs} which
   * can be overridden to improve field performance by loading only data you
   * need.
   *
   * @return list List of PHIDs to load handles for.
   * @task load
   */
  public function getRequiredHandlePHIDsForRevisionView() {
    return $this->getRequiredHandlePHIDs();
  }


  /**
   * Specify which @{class:PhabricatorObjectHandle}s need to be loaded for your
   * field to render correctly on the list interface.
   *
   * This is a more specific version of @{method:getRequiredHandlePHIDs} which
   * can be overridden to improve field performance by loading only data you
   * need.
   *
   * @param DifferentialRevision The revision to pull PHIDs for.
   * @return list List of PHIDs to load handles for.
   * @task load
   */
  public function getRequiredHandlePHIDsForRevisionList(
    DifferentialRevision $revision) {
    return array();
  }


  /**
   * Specify which @{class:PhabricatorObjectHandle}s need to be loaded for your
   * field to render correctly on the edit interface.
   *
   * This is a more specific version of @{method:getRequiredHandlePHIDs} which
   * can be overridden to improve field performance by loading only data you
   * need.
   *
   * @return list List of PHIDs to load handles for.
   * @task load
   */
  public function getRequiredHandlePHIDsForRevisionEdit() {
    return $this->getRequiredHandlePHIDs();
  }

  /**
   * Specify which @{class:PhabricatorObjectHandle}s need to be loaded for your
   * field to render correctly on the commit message interface.
   *
   * This is a more specific version of @{method:getRequiredHandlePHIDs} which
   * can be overridden to improve field performance by loading only data you
   * need.
   *
   * @return list List of PHIDs to load handles for.
   * @task load
   */
  public function getRequiredHandlePHIDsForCommitMessage() {
    return $this->getRequiredHandlePHIDs();
  }

  /**
   * Parse a list of users into a canonical PHID list.
   *
   * @param string Raw list of comma-separated user names.
   * @return list List of corresponding PHIDs.
   * @task load
   */
  protected function parseCommitMessageUserList($value) {
    return $this->parseCommitMessageObjectList($value, $mailables = false);
  }

  /**
   * Parse a list of mailable objects into a canonical PHID list.
   *
   * @param string Raw list of comma-separated mailable names.
   * @return list List of corresponding PHIDs.
   * @task load
   */
  protected function parseCommitMessageMailableList($value) {
    return $this->parseCommitMessageObjectList($value, $mailables = true);
  }


  /**
   * Parse and lookup a list of object names, converting them to PHIDs.
   *
   * @param string Raw list of comma-separated object names.
   * @param bool   True to include mailing lists.
   * @param bool   True to make a best effort. By default, an exception is
   *               thrown if any item is invalid.
   * @return list List of corresponding PHIDs.
   * @task load
   */
  public static function parseCommitMessageObjectList(
    $value,
    $include_mailables,
    $allow_partial = false) {

    $value = array_unique(array_filter(preg_split('/[\s,]+/', $value)));
    if (!$value) {
      return array();
    }

    $object_map = array();

    $users = id(new PhabricatorUser())->loadAllWhere(
      '(username IN (%Ls))',
      $value);

    $user_map = mpull($users, 'getPHID', 'getUsername');
    foreach ($user_map as $username => $phid) {
      // Usernames may have uppercase letters in them. Put both names in the
      // map so we can try the original case first, so that username *always*
      // works in weird edge cases where some other mailable object collides.
      $object_map[$username] = $phid;
      $object_map[strtolower($username)] = $phid;
    }

    if ($include_mailables) {
      $mailables = id(new PhabricatorMetaMTAMailingList())->loadAllWhere(
        '(email IN (%Ls)) OR (name IN (%Ls))',
        $value,
        $value);
      $object_map += mpull($mailables, 'getPHID', 'getName');
      $object_map += mpull($mailables, 'getPHID', 'getEmail');
    }

    $invalid = array();
    $results = array();
    foreach ($value as $name) {
      if (empty($object_map[$name])) {
        if (empty($object_map[strtolower($name)])) {
          $invalid[] = $name;
        } else {
          $results[] = $object_map[strtolower($name)];
        }
      } else {
        $results[] = $object_map[$name];
      }
    }

    if ($invalid && !$allow_partial) {
      $invalid = implode(', ', $invalid);
      $what = $include_mailables
        ? "users and mailing lists"
        : "users";
      throw new DifferentialFieldParseException(
        "Commit message references nonexistent {$what}: {$invalid}.");
    }

    return array_unique($results);
  }


/* -(  Contextual Data  )---------------------------------------------------- */


  /**
   * @task context
   */
  final public function setRevision(DifferentialRevision $revision) {
    $this->revision = $revision;
    $this->didSetRevision();
    return $this;
  }

  /**
   * @task context
   */
  protected function didSetRevision() {
    return;
  }


  /**
   * @task context
   */
  final public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  /**
   * @task context
   */
  final public function setManualDiff(DifferentialDiff $diff) {
    $this->manualDiff = $diff;
    return $this;
  }

  /**
   * @task context
   */
  final public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  /**
   * @task context
   */
  final public function setDiffProperties(array $diff_properties) {
    $this->diffProperties = $diff_properties;
    return $this;
  }

  /**
   * @task context
   */
  final public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  /**
   * @task context
   */
  final protected function getRevision() {
    if (empty($this->revision)) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->revision;
  }


  /**
   * Determine if revision context is currently available.
   *
   * @task context
   */
  final protected function hasRevision() {
    return (bool)$this->revision;
  }


  /**
   * @task context
   */
  final protected function getDiff() {
    if (empty($this->diff)) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->diff;
  }

  /**
   * @task context
   */
  final protected function getManualDiff() {
    if (!$this->manualDiff) {
      return $this->getDiff();
    }
    return $this->manualDiff;
  }

  /**
   * @task context
   */
  final protected function getUser() {
    if (empty($this->user)) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->user;
  }

  /**
   * Get the handle for an object PHID. You must overload
   * @{method:getRequiredHandlePHIDs} (or a more specific version thereof)
   * and include the PHID you want in the list for it to be available here.
   *
   * @return PhabricatorObjectHandle Handle to the object.
   * @task context
   */
  final protected function getHandle($phid) {
    if ($this->handles === null) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    if (empty($this->handles[$phid])) {
      $class = get_class($this);
      throw new Exception(
        "A differential field (of class '{$class}') is attempting to retrieve ".
        "a handle ('{$phid}') which it did not request. Return all handle ".
        "PHIDs you need from getRequiredHandlePHIDs().");
    }
    return $this->handles[$phid];
  }

  final protected function getLoadedHandles() {
    if ($this->handles === null) {
      throw new DifferentialFieldDataNotAvailableException($this);
    }

    return $this->handles;
  }

  /**
   * Get the list of properties for a diff set by @{method:setManualDiff}.
   *
   * @return array Array of all Diff properties.
   * @task context
   */
  final public function getDiffProperties() {
    if ($this->diffProperties === null) {
      // This will be set to some (possibly empty) array if we've loaded
      // properties, so null means diff properties aren't available in this
      // context.
      throw new DifferentialFieldDataNotAvailableException($this);
    }
    return $this->diffProperties;
  }

  /**
   * Get a property of a diff set by @{method:setManualDiff}.
   *
   * @param  string      Diff property key.
   * @return mixed|null  Diff property, or null if the property does not have
   *                     a value.
   * @task context
   */
  final public function getDiffProperty($key) {
    return idx($this->getDiffProperties(), $key);
  }

}

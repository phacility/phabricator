/**
 * @provides javelin-behavior-phui-file-upload
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phuix-dropdown-menu
 */

JX.behavior('phui-file-upload', function(config) {

  function startUpload(workflow, input) {
    var files = input.files;

    if (!files || !files.length) {
      return;
    }

    var state = {
      workflow: workflow,
      input: input,
      waiting: 0,
      phids: []
    };

    var callback = JX.bind(null, didUpload, state);

    var dummy = input;
    var uploader = new JX.PhabricatorDragAndDropFileUpload(dummy)
      .setURI(config.uploadURI)
      .setChunkThreshold(config.chunkThreshold);

    uploader.listen('didUpload', callback);
    uploader.start();

    workflow.pause();
    for (var ii = 0; ii < files.length; ii++) {
      state.waiting++;
      uploader.sendRequest(files[ii]);
    }
  }

  function didUpload(state, file) {
    state.phids.push(file.getPHID());
    state.waiting--;

    if (state.waiting) {
      return;
    }

    state.workflow
      .addData(config.inputName, state.phids.join(', '))
      .resume();
  }

  JX.Workflow.listen('start', function(workflow) {
    var form = workflow.getSourceForm();
    if (!form) {
      return;
    }

    var input;
    try {
      input = JX.$(config.fileInputID);
    } catch (ex) {
      return;
    }

    var local_form = JX.DOM.findAbove(input, 'form');
    if (!local_form) {
      return;
    }

    if (local_form !== form) {
      return;
    }

    startUpload(workflow, input);
  });

});

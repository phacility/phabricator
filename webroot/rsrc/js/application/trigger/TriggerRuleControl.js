/**
 * @requires phuix-form-control-view
 * @provides trigger-rule-control
 * @javelin
 */

JX.install('TriggerRuleControl', {

  construct: function() {
  },

  properties: {
    type: null,
    specification: null
  },

  statics: {
    newFromDictionary: function(map) {
      return new JX.TriggerRuleControl()
        .setType(map.type)
        .setSpecification(map.specification);
    },
  },

  members: {
    newInput: function(rule) {
      var phuix = new JX.PHUIXFormControl()
        .setControl(this.getType(), this.getSpecification());

      phuix.setValue(rule.getValue());

      return {
        node: phuix.getRawInputNode(),
        get: JX.bind(phuix, phuix.getValue)
      };
    }

  }

});

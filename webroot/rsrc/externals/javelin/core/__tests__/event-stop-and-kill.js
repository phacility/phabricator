/**
 * @requires javelin-event
 */

describe('Event Stop/Kill', function() {
  var target;

  beforeEach(function() {
    target = new JX.Event();
  });

  it('should stop an event', function() {
    expect(target.getStopped()).toBe(false);
    target.prevent();
    expect(target.getStopped()).toBe(false);
    target.stop();
    expect(target.getStopped()).toBe(true);
  });

  it('should prevent the default action of an event', function() {
    expect(target.getPrevented()).toBe(false);
    target.stop();
    expect(target.getPrevented()).toBe(false);
    target.prevent();
    expect(target.getPrevented()).toBe(true);
  });

  it('should kill (stop and prevent) an event', function() {
    expect(target.getPrevented()).toBe(false);
    expect(target.getStopped()).toBe(false);
    target.kill();
    expect(target.getPrevented()).toBe(true);
    expect(target.getStopped()).toBe(true);
  });
});

/**
 * NullHome — app bootstrap.
 *
 * Instantiates controllers, calls their init() methods, wires up
 * non-MVC modules, and emits 'app:ready' to trigger initial data loads.
 * Contains no application logic.
 */
document.addEventListener('DOMContentLoaded', function () {
    var roomController = new RoomController();

    roomController.init();

    initMenu();
    initRoomForm(function () {
        AppEvents.emit('room:added', {});
    });
    initRoomRemove(
        function () { return roomController.getRooms(); },
        function (updated) { roomController.setRooms(updated); }
    );
    initWemoScan();

    var validationController = new ValidationController();
    validationController.init();

    AppEvents.emit('app:ready', {});
});

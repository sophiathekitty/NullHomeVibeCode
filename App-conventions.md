# NullHome Front-End App Conventions
 
This document is the authoritative reference for how the front-end JavaScript application is structured and how code must be written and organized. All contributors and automated agents must follow these rules.
 
It is a companion to `CONVENTIONS.md` (PHP/general) and `API_CONVENTIONS.md` (API contract).
 
---
 
## 1. Architecture Overview
 
The front-end follows an MVC pattern that mirrors the PHP back-end structure. The three layers — Model, View, Controller — have strict, non-overlapping responsibilities. A thin `app.js` bootstraps the application. A static `AppEvents` class handles cross-controller communication.
 
| Layer | Location | Responsibility |
|---|---|---|
| Model | `/public/js/models/` | API calls, envelope unwrapping, data shape |
| View | `/public/js/views/` | Template cloning, DOM rendering |
| Controller | `/public/js/controllers/` | User interaction, polling, wires model → view, emits/subscribes to AppEvents |
| App bootstrap | `/public/js/app.js` | Instantiates and wires controllers only — no logic |
| Event dispatcher | `/public/js/app-events.js` | Static named-event dispatcher |
 
---
 
## 2. Directory Structure
 
```
/public/js/
  app.js                         → Bootstrap only. Instantiates controllers. No logic.
  app-events.js                  → AppEvents static class.
  models/
    base-model.js                → BaseModel class. All models extend this.
    room-model.js                → RoomModel
    device-model.js              → DeviceModel
    (etc.)
  views/
    base-view.js                 → BaseView class. All views extend this.
    room-view.js                 → RoomView
    device-view.js               → DeviceView
    (etc.)
  controllers/
    base-controller.js           → BaseController class. All controllers extend this.
    room-controller.js           → RoomController
    device-controller.js         → DeviceController
    (etc.)
```
 
---
 
## 3. Layer Responsibilities
 
### 3.1 Model
 
- Owns **all** `fetch()` calls. No other layer may call `fetch()`.
- Unwraps the standard API envelope. Consumers always receive `response.data`, never the raw envelope.
- Checks `response.success` and throws a typed error (including `response.error`) on failure.
- Never touches the DOM.
- Never references a View or Controller.
 
**Base class interface:**
 
```javascript
class BaseModel {
  constructor(basePath)     // e.g. '/api/rooms'
  async get(path = '')      // GET  basePath/path → unwrapped data
  async post(path, body)    // POST basePath/path → unwrapped data
  async put(path, body)     // PUT  basePath/path → unwrapped data
  async delete(path)        // DELETE basePath/path → unwrapped data
}
```
 
**Envelope contract:** Every method unwraps the response before returning. If `response.success` is `false`, throw an `Error` with `response.error` as the message. Callers always receive plain data objects, never the `{ success, data, error }` envelope.
 
**Example subclass:**
 
```javascript
class RoomModel extends BaseModel {
  constructor() {
    super('/api/rooms');
  }
 
  async getAll() {
    return this.get();               // GET /api/rooms
  }
 
  async toggle(id) {
    return this.post(`/${id}/toggle`); // POST /api/rooms/1/toggle
  }
}
```
 
---
 
### 3.2 View
 
- Owns all DOM reads and writes.
- Clones `<template>` elements to produce DOM nodes. Never builds HTML strings.
- Exposes a `render(data)` method. The `data` argument is always already-unwrapped model data.
- Never calls `fetch()`. Never instantiates a Model.
- Never emits or subscribes to AppEvents.
 
**Base class interface:**
 
```javascript
class BaseView {
  constructor(templateId, containerId)  // selects <template> and mount container by ID
  cloneTemplate()                       // returns a deep clone of the template content
  clear()                               // empties the container
  render(data)                          // subclasses must override
}
```
 
**Template convention:** Every view has a corresponding `<template id="...">` tag in the PHP shell (`/app/index.php`). Template IDs use `kebab-case` and match the view class name: `RoomView` → `<template id="room-card">`.
 
**Example subclass:**
 
```javascript
class RoomView extends BaseView {
  constructor() {
    super('room-card', 'rooms-grid');
  }
 
  render(rooms) {
    this.clear();
    rooms.forEach(room => {
      const node = this.cloneTemplate();
      node.querySelector('.room-name').textContent = room.name;
      this.container.appendChild(node);
    });
  }
}
```
 
---
 
### 3.3 Controller
 
- Instantiates one Model and one (or more) Views.
- Attaches DOM event listeners.
- Calls model methods and passes results to view methods.
- Manages polling via `startPolling()` / `stopPolling()` inherited from `BaseController`.
- Emits and subscribes to `AppEvents` for cross-controller coordination.
- Never builds HTML strings. Never calls `fetch()`.
 
**Base class interface:**
 
```javascript
class BaseController {
  constructor()
  init()                         // subclasses override — attach listeners, load initial data
  startPolling(intervalMs)       // calls this.refresh() on an interval
  stopPolling()                  // clears the interval
  async refresh()                // subclasses override — fetch and re-render
}
```
 
**Polling rule:** Only start polling when the relevant view is visible/active. Always call `stopPolling()` when the view is hidden or the controller is no longer needed. Avoid running intervals for off-screen content.
 
**Example subclass:**
 
```javascript
class RoomController extends BaseController {
  constructor() {
    super();
    this.model = new RoomModel();
    this.view  = new RoomView();
  }
 
  init() {
    AppEvents.on('app:ready', () => this.refresh());
    AppEvents.on('room:added', () => this.refresh());
    document.getElementById('rooms-grid')
      .addEventListener('click', e => this.handleRoomClick(e));
  }
 
  async refresh() {
    const rooms = await this.model.getAll();
    this.view.render(rooms);
  }
 
  handleRoomClick(e) {
    const card = e.target.closest('[data-room-id]');
    if (!card) return;
    AppEvents.emit('room:selected', { id: card.dataset.roomId });
  }
}
```
 
---
 
### 3.4 AppEvents
 
A static named-event dispatcher. Controllers use it to communicate without holding references to each other.
 
**Interface:**
 
```javascript
class AppEvents {
  static on(eventName, callback)      // subscribe
  static off(eventName, callback)     // unsubscribe
  static emit(eventName, payload)     // dispatch to all subscribers
}
```
 
**Event naming convention:** `resource:action` in `kebab-case`. Examples:
 
| Event | Emitted by | Meaning |
|---|---|---|
| `app:ready` | `app.js` | DOM ready, all controllers initialized |
| `room:selected` | `RoomController` | User selected a room |
| `room:added` | `RoomController` | A room was successfully created |
| `room:deleted` | `RoomController` | A room was successfully deleted |
| `device:toggled` | `DeviceController` | A device state changed |
 
New events must be added to this table in `APP_CONVENTIONS.md` as they are introduced.
 
**Debugging:** To trace all events during development, add a single listener in `app.js`:
 
```javascript
// development only — remove before production
AppEvents.on('*', (name, payload) => console.log('[AppEvents]', name, payload));
```
 
---
 
### 3.5 app.js
 
Bootstraps the application. Contains no logic beyond instantiation and `init()` calls.
 
```javascript
document.addEventListener('DOMContentLoaded', () => {
  const roomController   = new RoomController();
  const deviceController = new DeviceController();
 
  roomController.init();
  deviceController.init();
 
  AppEvents.emit('app:ready', {});
});
```
 
---
 
## 4. Naming Conventions
 
| Thing | Convention | Example |
|---|---|---|
| JS files | `kebab-case.js` | `room-model.js` |
| Classes | `PascalCase` | `RoomModel` |
| Methods and variables | `camelCase` | `getAll()`, `roomId` |
| Template IDs | `kebab-case` | `room-card` |
| Container IDs | `kebab-case` | `rooms-grid` |
| AppEvent names | `resource:action` kebab | `room:selected` |
| `data-*` attributes | `kebab-case` | `data-room-id` |
 
---
 
## 5. JSDoc Standards
 
Every JS function and class method must have a JSDoc block. This is inherited from `CONVENTIONS.md` and applies equally to MVC classes.
 
Required tags:
- `@param {type} name — description` for every parameter
- `@returns {type}` for every non-void method
- `@async` for async methods (alongside `@returns {Promise<type>}`)
 
Class-level JSDoc must describe the layer and the resource the class manages.
 
**Example:**
 
```javascript
/**
 * Model for the rooms API resource.
 * Handles all fetch calls to /api/rooms and unwraps the response envelope.
 * @extends BaseModel
 */
class RoomModel extends BaseModel {
 
  /**
   * Fetches all rooms from the API.
   *
   * @async
   * @returns {Promise<Array<Object>>} Array of room data objects.
   */
  async getAll() {
    return this.get();
  }
}
```
 
---
 
## 6. API Envelope Rule
 
**No layer other than Model may reference `response.success`, `response.data`, or `response.error`.**
 
Models unwrap once. Everything downstream receives plain data. This is the single most important rule for preventing the class of bug where a raw envelope is passed to a render function.
 
---
 
## 7. Template Rules
 
- Every renderable resource has exactly one `<template>` tag in `index.php`.
- Templates contain the complete DOM structure for one item (one card, one row, one modal).
- Templates are never modified by JS — only cloned. The clone is modified, then appended.
- Template IDs are stable and must match the `templateId` passed to the View constructor.
- No inline styles or scripts inside `<template>` tags.
 
---
 
## 8. What Belongs Where — Quick Reference
 
| Task | Layer |
|---|---|
| Call `fetch()` | Model only |
| Unwrap `response.data` | Model only |
| Clone a `<template>` | View only |
| Set `.textContent` / `.classList` | View only |
| Call `addEventListener` | Controller only |
| Call `AppEvents.emit()` | Controller only |
| Call `AppEvents.on()` | Controller only |
| Call `startPolling()` | Controller only |
| Instantiate Model or View | Controller only |
| Instantiate Controller | `app.js` only |
 
---
 
## 9. Layer Summary Table
 
| Layer | File location | Knows about DOM? | Calls fetch? | Emits/subscribes events? |
|---|---|---|---|---|
| Model | `/public/js/models/` | No | Yes | No |
| View | `/public/js/views/` | Yes | No | No |
| Controller | `/public/js/controllers/` | Via view only | No | Yes |
| AppEvents | `/public/js/app-events.js` | No | No | N/A — is the dispatcher |
| App bootstrap | `/public/js/app.js` | Minimal | No | Emits `app:ready` only |
 
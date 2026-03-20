<?php
/**
 * Controller — base class for all controllers.
 *
 * Controllers combine one or more models and implement business rules.
 * They are instantiated by API handlers or service scripts.
 */
abstract class Controller {
    /**
     * Bootstrap the controller: require config, DB, and any models needed.
     * Subclasses call parent::__construct() then add their own dependencies.
     */
    public function __construct() {
        // shared bootstrap is handled by the entry point (api/index.php or services/*.php)
    }
}

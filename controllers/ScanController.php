<?php
require_once __DIR__ . '/Controller.php';
require_once APP_ROOT . '/models/NmapScan.php';
require_once APP_ROOT . '/modules/network/NetworkModule.php';

/**
 * ScanController — shared logic for the network IP scan workflow.
 *
 * Handles the parts of the scan process that are device-type agnostic:
 * resetting the nmap scan table and running the initial ping sweep. Device-
 * specific next-step logic is delegated to the appropriate device controller
 * (e.g. WemoController for Wemo devices).
 *
 * This controller is HTTP-unaware: it returns plain values and never accesses
 * superglobals or writes HTTP output.
 */
class ScanController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Reset the nmap scan table and run a fresh ping sweep.
     *
     * Clears non-known records from the nmap_scans table via
     * NmapScan::resetScan(), then calls NetworkModule::discoverIps() to
     * populate it with the live hosts currently visible on the network.
     *
     * @return int Count of IP addresses queued for scanning.
     * @throws \RuntimeException If no subnet is configured in settings.
     */
    public function reset(): int
    {
        $nmapScan = new NmapScan();
        $nmapScan->resetScan();

        return NetworkModule::discoverIps($nmapScan);
    }
}

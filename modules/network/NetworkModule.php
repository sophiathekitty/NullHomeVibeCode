<?php
require_once __DIR__ . '/../../models/SettingsModel.php';

/**
 * NetworkModule — detects the host server's primary network interface info
 * and persists it to the settings table.
 *
 * This module is HTTP-unaware. It must not reference any superglobals.
 * All outputs are return values or writes via the SettingsModel.
 */
class NetworkModule
{
    /**
     * Detect the host server's primary IP address, MAC address, and derive
     * the /24 subnet. Persists the values to the settings table via SettingsModel.
     *
     * Uses `ip addr show` (Linux) to retrieve interface information. Parses
     * the first non-loopback global IPv4 address and its associated MAC address.
     *
     * If detection fails (exec returns no output or parsing fails), returns an
     * empty array and does not write to settings.
     *
     * @param callable|null $execFn Optional callable used to execute shell commands.
     *                              Signature: (string $command): array<int, string>
     *                              where the return value is the lines of output.
     *                              Defaults to a wrapper around the built-in exec().
     * @return array<string, string> Associative array with keys 'ip', 'mac', and
     *                               'subnet', or an empty array if detection fails.
     */
    public static function detect(?callable $execFn = null): array
    {
        if ($execFn === null) {
            $execFn = static function (string $command): array {
                exec($command, $output);
                return $output;
            };
        }

        // Get the primary non-loopback global IPv4 address.
        $ipLines = $execFn(
            "ip -4 addr show scope global | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}' | head -1"
        );
        $ip = trim($ipLines[0] ?? '');

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return [];
        }

        // Get the MAC address of the interface that holds this IP.
        $macLines = $execFn(
            "ip addr show | grep -B1 'inet " . $ip . "' | grep -oP '(?<=link/ether\\s)[0-9a-f:]{17}'"
        );
        $mac = trim($macLines[0] ?? '');

        if ($mac === '' || preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', $mac) !== 1) {
            return [];
        }

        $subnet = self::deriveSubnet($ip);

        // Persist to the settings table.
        $settings = new SettingsModel();
        $settings->set('host_ip', $ip);
        $settings->set('host_mac', $mac);
        $settings->set('host_subnet', $subnet);

        return [
            'ip'     => $ip,
            'mac'    => $mac,
            'subnet' => $subnet,
        ];
    }

    /**
     * Derive a /24 CIDR subnet from an IPv4 address by zeroing the last octet.
     *
     * Example: '192.168.1.50' → '192.168.1.0/24'
     *
     * @param string $ip A valid IPv4 address (e.g. '192.168.1.50').
     * @return string The /24 subnet in CIDR notation (e.g. '192.168.1.0/24').
     */
    private static function deriveSubnet(string $ip): string
    {
        $parts    = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts) . '/24';
    }
}

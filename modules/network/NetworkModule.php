<?php
require_once __DIR__ . '/../../models/SettingsModel.php';
require_once __DIR__ . '/../../models/NmapScan.php';

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
        // $ip is already validated as a safe IPv4 string (digits and dots only)
        // by FILTER_VALIDATE_IP above, so no shell-special characters can exist.
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
     * Discover live hosts on the subnet and persist them via the NmapScan model.
     *
     * Shells out to `nmap -sn -oG -` to perform a ping sweep, parses each
     * `Host:` line in the grep output to extract IP addresses, filters out the
     * host server's own IP, and persists the results via `$nmapScan->insertIps()`.
     * Existing records are not overwritten.
     *
     * @param NmapScan      $nmapScan The NmapScan model instance used to persist IPs.
     * @param string|null   $subnet   The CIDR subnet to scan (e.g. '192.168.1.0/24').
     *                                If null, reads `host_subnet` from settings.
     * @param callable|null $execFn   Optional callable used to execute shell commands.
     *                                Signature: (string $command): array<int, string>
     *                                where the return value is the lines of output.
     *                                Defaults to a wrapper around the built-in exec().
     * @return int Count of IP addresses found (after filtering the host IP).
     * @throws \RuntimeException If no subnet is provided and `host_subnet` is not set,
     *                           or if the subnet value is not valid CIDR notation.
     */
    public static function discoverIps(NmapScan $nmapScan, ?string $subnet = null, ?callable $execFn = null): int
    {
        $execFn = $execFn ?? static function (string $cmd): array {
            exec($cmd, $output);
            return $output;
        };

        $settings = null;
        if ($subnet === null) {
            $settings = new SettingsModel();
            $subnet   = $settings->get('host_subnet');
        }

        if ($subnet === null) {
            throw new \RuntimeException('Host subnet not configured');
        }

        // Validate subnet to prevent command injection: must be valid CIDR notation
        // with each octet 0–255 and a prefix length of 0–32.
        $subnetParts = explode('/', $subnet, 2);
        if (
            count($subnetParts) !== 2
            || filter_var($subnetParts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || !ctype_digit($subnetParts[1])
            || (int) $subnetParts[1] > 32
        ) {
            throw new \RuntimeException('Invalid subnet format');
        }

        $lines = $execFn("nmap -sn -oG - {$subnet}");

        $ips = [];
        foreach ($lines as $line) {
            if (preg_match('/^Host:\s+(\d+\.\d+\.\d+\.\d+)/', $line, $m)) {
                $ips[] = $m[1];
            }
        }

        // Filter out the host server's own IP.
        $settings = $settings ?? new SettingsModel();
        $hostIp   = $settings->get('host_ip');
        if ($hostIp !== null) {
            $ips = array_values(
                array_filter($ips, static fn (string $ip): bool => $ip !== $hostIp)
            );
        }

        $nmapScan->insertIps($ips);

        return count($ips);
    }

    /**
     * Return a list of open TCP ports on the given IP address.
     *
     * Shells out to `nmap -p- --open -oG -` to scan all 65535 ports, parses
     * the `Ports:` line in the grep output, and returns only the port numbers
     * whose state is `open`. Returns an empty array if nmap returns no output
     * or no open ports are found.
     *
     * @param string        $ip     The IPv4 address to scan (e.g. '192.168.1.101').
     * @param callable|null $execFn Optional callable used to execute shell commands.
     *                              Signature: (string $command): array<int, string>
     *                              where the return value is the lines of output.
     *                              Defaults to a wrapper around the built-in exec().
     * @return array<int, int> Array of open port numbers (integers).
     */
    public static function getOpenPorts(string $ip, ?callable $execFn = null): array
    {
        $execFn = $execFn ?? static function (string $cmd): array {
            exec($cmd, $output);
            return $output;
        };

        // Validate IP to prevent command injection.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return [];
        }

        $lines = $execFn("nmap -p- --open -oG - {$ip}");

        $ports = [];
        foreach ($lines as $line) {
            if (preg_match('/Ports:\s+(.+)/', $line, $m)) {
                foreach (explode(',', $m[1]) as $part) {
                    [$portStr, $state] = array_pad(explode('/', trim($part), 3), 2, '');
                    if ($portStr !== '' && $state === 'open') {
                        $ports[] = (int) $portStr;
                    }
                }
            }
        }

        return $ports;
    }

    /**
     * Derive a /24 CIDR subnet from an IPv4 address by zeroing the last octet.
     *
     * Note: This method always uses a /24 prefix length regardless of the
     * actual subnet mask configured on the interface. This is intentional: the
     * derived value is used as a scan range for nmap device discovery and a /24
     * is the expected convention for this application.
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

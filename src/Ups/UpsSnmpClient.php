<?php

namespace Edrard\Pingmonit\Ups;

class UpsSnmpClient
{
    public function getInt(array $upsConfig, $oid)
    {
        $value = $this->getRaw($upsConfig, $oid);
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        if (preg_match('/\(([-0-9]+)\)/', $value, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/[-0-9]+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    public function getTimeTicksSeconds(array $upsConfig, $oid)
    {
        $ticks = $this->getInt($upsConfig, $oid);
        if ($ticks === null) {
            return null;
        }

        if ($ticks < 0) {
            return null;
        }

        return (int) floor($ticks / 100);
    }

    public function getRaw(array $upsConfig, $oid)
    {
        if (!is_string($oid) || $oid === '') {
            return null;
        }

        $ip = (string) ($upsConfig['ip'] ?? '');
        if ($ip === '') {
            return null;
        }

        if (!function_exists('snmpget') && !function_exists('snmp2_get') && !function_exists('snmp3_get')) {
            throw new \RuntimeException('SNMP extension is not installed/enabled (php-snmp).');
        }

        if (function_exists('snmp_set_valueretrieval')) {
            @snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        }
        if (function_exists('snmp_set_quick_print')) {
            @snmp_set_quick_print(true);
        }

        $version = (string) ($upsConfig['snmp_version'] ?? '2c');

        if ($version === '1' || $version === 'v1') {
            $community = (string) ($upsConfig['snmp_community'] ?? 'public');
            $res = @snmpget($ip, $community, $oid);
            return $res === false ? null : $res;
        }

        if ($version === '2c' || $version === 'v2c' || $version === '2') {
            $community = (string) ($upsConfig['snmp_community'] ?? 'public');
            if (function_exists('snmp2_get')) {
                $res = @snmp2_get($ip, $community, $oid);
            } else {
                $res = @snmpget($ip, $community, $oid);
            }
            return $res === false ? null : $res;
        }

        if ($version === '3' || $version === 'v3') {
            $username = (string) ($upsConfig['snmp_v3_username'] ?? '');
            $authProtocol = (string) ($upsConfig['snmp_v3_auth_protocol'] ?? 'SHA');
            $authPassword = (string) ($upsConfig['snmp_v3_auth_password'] ?? '');
            $privProtocol = (string) ($upsConfig['snmp_v3_priv_protocol'] ?? 'AES');
            $privPassword = (string) ($upsConfig['snmp_v3_priv_password'] ?? '');

            if ($username === '') {
                return null;
            }

            $secLevel = 'noAuthNoPriv';
            if ($authPassword !== '' && $privPassword !== '') {
                $secLevel = 'authPriv';
            } elseif ($authPassword !== '') {
                $secLevel = 'authNoPriv';
            }

            if ($secLevel === 'noAuthNoPriv') {
                $authProtocol = '';
                $authPassword = '';
                $privProtocol = '';
                $privPassword = '';
            } elseif ($secLevel === 'authNoPriv') {
                $privProtocol = '';
                $privPassword = '';
            }

            $res = @snmp3_get($ip, $username, $secLevel, $authProtocol, $authPassword, $privProtocol, $privPassword, $oid);
            return $res === false ? null : $res;
        }

        return null;
    }
}

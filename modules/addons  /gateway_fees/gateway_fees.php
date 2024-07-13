<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

use WHMCS\Database\Capsule;

function gateway_fees_config(): array
{
    $configarray = [
        "name" => "Gateway Fees for WHMCS",
        "description" => "Add fees based on the gateway being used.",
        "version" => "1.0.1",
        "author" => "Open Source",
        "fields" => []
    ];

    $gateways = Capsule::table('tblpaymentgateways')->get();
    foreach ($gateways as $gateway) {
        $configarray['fields']["fee_1_$gateway->gateway"] = [
            "FriendlyName" => $gateway->gateway,
            "Type" => "text",
            "Default" => "0.00",
            "Description" => "$"
        ];

        $configarray['fields']["fee_2_$gateway->gateway"] = [
            "FriendlyName" => $gateway->gateway,
            "Type" => "text",
            "Default" => "0.00",
            "Description" => "%<br />"
        ];
    }

    return $configarray;
}

function gateway_fees_activate(): void
{
    $gateways = Capsule::table('tblpaymentgateways')->groupBy('gateway')->get();
    foreach ($gateways as $gateway) {
        Capsule::table('tbladdonmodules')->insert([
            ['module' => 'gateway_fees', 'setting' => "fee_1_$gateway->gateway", 'value' => '0.00'],
            ['module' => 'gateway_fees', 'setting' => "fee_2_$gateway->gateway", 'value' => '0.00']
        ]);
    }
}

?>

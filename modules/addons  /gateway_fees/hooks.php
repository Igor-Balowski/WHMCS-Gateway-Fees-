<?php

use WHMCS\Database\Capsule;

function update_gateway_fee3(array $vars): void
{
    $id = $vars['invoiceid'];
    updateInvoiceTotal($id);
}

function update_gateway_fee1(array $vars): void
{
    $id = $vars['invoiceid'];
    $invoice = Capsule::table('tblinvoices')->where('id', $id)->first();
    if ($invoice) {
        update_gateway_fee2([
            'paymentmethod' => $invoice->paymentmethod,
            'invoiceid' => $invoice->id
        ]);
    }
}

function update_gateway_fee2(array $vars): void
{
    $paymentmethod = $vars['paymentmethod'];
    Capsule::table('tblinvoiceitems')->where('invoiceid', $vars['invoiceid'])->where('notes', 'gateway_fees')->delete();
    
    $results = Capsule::table('tbladdonmodules')
                    ->select('setting', 'value')
                    ->whereIn('setting', ["fee_2_$paymentmethod", "fee_1_$paymentmethod"])
                    ->get();

    $params = [];
    foreach ($results as $data) {
        $params[$data->setting] = $data->value;
    }

    $fee1 = $params["fee_1_$paymentmethod"] ?? 0;
    $fee2 = $params["fee_2_$paymentmethod"] ?? 0;
    $total = InvoiceTotal($vars['invoiceid']);

    if ($total > 0) {
        $amountdue = $fee1 + $total * $fee2 / 100;
        if ($fee1 > 0 && $fee2 > 0) {
            $d = "$fee1 + $fee2%";
        } elseif ($fee2 > 0) {
            $d = "$fee2%";
        } elseif ($fee1 > 0) {
            $d = "$fee1";
        }
    }

    if (isset($d)) {
        Capsule::table('tblinvoiceitems')->insert([
            "userid" => $_SESSION['uid'],
            "invoiceid" => $vars['invoiceid'],
            "type" => "Fee",
            "notes" => "gateway_fees",
            "description" => getGatewayName2($vars['paymentmethod']) . " Fees ($d)",
            "amount" => $amountdue ?? 0,
            "taxed" => "0",
            "duedate" => Capsule::raw('now()'),
            "paymentmethod" => $vars['paymentmethod']
        ]);
    }

    updateInvoiceTotal($vars['invoiceid']);
}

add_hook("InvoiceChangeGateway", 1, "update_gateway_fee2");
add_hook("InvoiceCreated", 1, "update_gateway_fee1");
add_hook("AdminInvoicesControlsOutput", 2, "update_gateway_fee3");
add_hook("AdminInvoicesControlsOutput", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 1, "update_gateway_fee1");
add_hook("InvoiceCreationAdminArea", 2, "update_gateway_fee3");

function InvoiceTotal(int $id): float
{
    $taxsubtotal = 0;
    $nontaxsubtotal = 0;
    
    $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $id)->get();
    foreach ($items as $item) {
        if ($item->taxed == "1") {
            $taxsubtotal += $item->amount;
        } else {
            $nontaxsubtotal += $item->amount;
        }
    }

    $subtotal = $nontaxsubtotal + $taxsubtotal;

    $invoice = Capsule::table('tblinvoices')->where('id', $id)->first();
    $userid = $invoice->userid;
    $credit = $invoice->credit;
    $taxrate = $invoice->taxrate;
    $taxrate2 = $invoice->taxrate2;

    if (!function_exists("getClientsDetails")) {
        require_once(dirname(__FILE__) . "/clientfunctions.php");
    }

    $clientsdetails = getClientsDetails($userid);
    $tax = $tax2 = 0;

    if ($GLOBALS['CONFIG']['TaxEnabled'] == "on" && !$clientsdetails['taxexempt']) {
        if ($taxrate != "0.00") {
            if ($GLOBALS['CONFIG']['TaxType'] == "Inclusive") {
                $taxrate = $taxrate / 100 + 1;
                $calc1 = $taxsubtotal / $taxrate;
                $tax = $taxsubtotal - $calc1;
            } else {
                $taxrate = $taxrate / 100;
                $tax = $taxsubtotal * $taxrate;
            }
        }

        if ($taxrate2 != "0.00") {
            if ($GLOBALS['CONFIG']['TaxL2Compound']) {
                $taxsubtotal += $tax;
            }

            if ($GLOBALS['CONFIG']['TaxType'] == "Inclusive") {
                $taxrate2 = $taxrate2 / 100 + 1;
                $calc1 = $taxsubtotal / $taxrate2;
                $tax2 = $taxsubtotal - $calc1;
            } else {
                $taxrate2 = $taxrate2 / 100;
                $tax2 = $taxsubtotal * $taxrate2;
            }
        }

        $tax = round($tax, 2);
        $tax2 = round($tax2, 2);
    }

    if ($GLOBALS['CONFIG']['TaxType'] == "Inclusive") {
        $subtotal = $subtotal - $tax - $tax2;
    } else {
        $total = $subtotal + $tax + $tax2;
    }

    if ($credit > 0) {
        if ($total < $credit) {
            $total = 0;
            $remainingcredit = $total - $credit;
        } else {
            $total -= $credit;
        }
    }

    $subtotal = format_as_currency($subtotal);
    $tax = format_as_currency($tax);
    $total = format_as_currency($total);

    return $total;
}

function getGatewayName2(string $modulename): string
{
    $result = Capsule::table('tblpaymentgateways')->where([
        ["gateway", "=", $modulename],
        ["setting", "=", "name"]
    ])->first();

    return $result->value ?? '';
}

?>

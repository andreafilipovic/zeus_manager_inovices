<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;

$url = 'https://testclient.zeusmanager.com/rest/symfony/technical/test.json?test_key=MMv4TWOuPh';
$stripe = new \Stripe\StripeClient("sk_test_VAoyDJUTnhkMWZAjyoAoDsYj00t2AfTidd");

/*

    1. Create customers
    2. Create product
    3. Create prices for products
    4. Create invoice item per customer
    5. Create invoice with inovice item per customer

*/

$data = getApiData($url);
$customers = createCustomers($data, $stripe);
$product_local = createProduct($stripe, 'Local');
$product_contract = createProduct($stripe, 'Contract');
$price_local = createPrice($data->prices->local_price, $stripe, $product_local);
$price_contract = createPrice($data->prices->contract_price, $stripe, $product_contract);
createInvoiceItemsForCustomers($customers, $data, $price_local, $price_contract, $stripe);
$invoices = createInvoices($customers, $stripe);

//return $invoices;
dd($invoices);


// RETRIEVE DATA
function getApiData($url)
{
    $client = new Client();
    $res = $client->request('GET', $url);
    $data = json_decode($res->getBody())->data;

    return $data;
}

// CUSTOMER
function createCustomers($data, $stripe)
{
    $customers = [];
    $companies = $data->company_data;

    foreach ($companies as $company) {
        $customers[] = $stripe->customers->create(['name' => $company->company_name, 'metadata' => ['company_id' => $company->company_id]]);
    }

    return $customers;
}


// PRODUCT
function createProduct($stripe, $name)
{
    $product = $stripe->products->create(['name' => $name]);

    return $product;
}

// PRICES
function createPrice($data, $stripe, $product)
{
    $amount = '';
    (int)$data != $data ? $amount = 'unit_amount_decimal' : $amount = 'unit_amount';

    $price = $stripe->prices->create([
        $amount => $data,
        'currency' => 'USD',
        'product' => $product->id
    ]);

    return $price;
}

// NUMBER OF LOCALS
function companyNumberOfLocals($data, $company)
{
    $locals_number = 0;
    foreach ($data->local_data as $local) {
        if ($local->company_id == $company) {
            $locals_number++;
        }
    }

    return $locals_number;
}

// NUMBER OF CONTRACTS
function getNumberOfContract($data, $company_id)
{
    $number_of_contracts_used = 0;
    foreach ($data->company_data as $company) {
        if ($company->company_id == $company_id) {
            $number_of_contracts_used = $company->number_of_contracts_used;
        }
    }

    return $number_of_contracts_used;
}

// INVOICE ITEM
function createInvoiceItem($data, $company_id, $customer, $price_local, $price_contract, $stripe): void
{
    $stripe->invoiceItems->create([
        'customer' => $customer->id,
        'price' => $price_contract->id,
        'quantity' => getNumberOfContract($data, $company_id)
    ]);

    $locals_number = companyNumberOfLocals($data, $company_id);
    if ($locals_number) {
        $stripe->invoiceItems->create([
            'customer' => $customer->id,
            'price' => $price_local->id,
            'quantity' => $locals_number
        ]);
    }
}

function createInvoiceItemsForCustomers($customers, $data, $price_local, $price_contract, $stripe): void
{
    foreach ($customers as $customer) {
        createInvoiceItem($data, $customer->metadata['company_id'], $customer, $price_local, $price_contract, $stripe);
    }
}

function createInvoices($customers, $stripe)
{
    $invoices = [];
    foreach ($customers as $customer) {
        $invoices[] = $stripe->invoices->create(['customer' => $customer->id])->toArray();
    }

    return $invoices;
}

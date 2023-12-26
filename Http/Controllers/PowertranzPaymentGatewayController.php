<?php

namespace Modules\PowertranzPaymentGateway\Http\Controllers;

use App\Enums\PaymentRouteEnum;
use App\Events\TenantRegisterEvent;
use App\Helpers\FlashMsg;
use App\Helpers\Payment\DatabaseUpdateAndMailSend\LandlordPricePlanAndTenantCreate;
use App\Mail\BasicMail;
use App\Mail\PlaceOrder;
use App\Mail\ProductOrderEmail;
use App\Mail\ProductOrderEmailAdmin;
use App\Mail\ProductOrderManualEmail;
use App\Mail\TenantCredentialMail;
use App\Models\PaymentLogs;
use App\Models\ProductOrder;
use App\Models\Tenant;
use App\Models\User;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\PowertranzPaymentGateway\Http\Traits\ConvertUsdSupport;
use Modules\PowertranzPaymentGateway\Http\Traits\CurrencySupport;
use Modules\Wallet\Entities\Wallet;
use Modules\Wallet\Entities\WalletHistory;
use Modules\Wallet\Http\Services\WalletService;
use Xgenious\Paymentgateway\Base\PaymentGatewayHelpers;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class PowertranzPaymentGatewayController extends Controller
{

use CurrencySupport,ConvertUsdSupport;

    /**
     * Display a listing of the resource.
     * @method chargeCustomer
     *
     * @return checkout url redirect user to the payment gateway website
     *
     * this method will receive all the information from the main script, while any user select any payment gateway for payment. this method will receive all of that data and make it ready for redirect user to the payment provider website for payment.
     *
     */
    public function chargeCustomer($args)
    {
        // all tenant payment process will from here....
        if (in_array($args["payment_type"],["shop_checkout"]) && $args["payment_for"] === "tenant"){
            return $this->chargeCustomerForShopCheckout($args);
        }
        abort(404);
        //make a request to Siteways server to generate checkout url based on static data
    }



    public function ShopCheckoutChargeCustomer(Request $request){


        $input = request()->input();
        /* Create a merchantAuthenticationType object with authentication details retrieved from the constants file */
        $merchant_password = \Crypt::decrypt(request()->merchant_password);
        $merchant_id = \Crypt::decrypt(request()->merchant_id);
        $gateway_key = \Crypt::decrypt(request()->gateway_key);
        $currency = request()->currency;

        $payment_url = $this->base_url().'sale';

        $header_data = [
            'PowerTranz-PowerTranzId'=> $merchant_id,
            'PowerTranz-PowerTranzPassword' => $merchant_password,
            'Content-Type' => 'application/json; charset=utf-8'
        ];
        if (!empty($gateway_key)){
           // $header_data['PowerTranz-GatewayKey '] = $gateway_key;
        }
        $cardNumber = preg_replace('/\s+/', '', $input['number']);
        $card_date = explode('/',request()->get('expiry'));
        $expiration_month = trim($card_date[0]); //detect if year value is full number like 2024 get only last two digit¥¥¥¥¥¥¥¥^-09oi87uy68uy6t5rewqsdw34e5
        $expiration_year = strlen(trim($card_date[1])) == 4 ? trim($card_date[1]) : trim($card_date[1]);
        $expiration_date = $expiration_year .$expiration_month;
        $response = Http::withHeaders($header_data)
            ->post($payment_url,[
                'TransactionIdentifier' => Str::uuid()->toString(), // Guid Ex: F388373D-9FD8-7AA0-B64B-0E51FF97227E  ( 36 Char Long string )
                'TotalAmount' => request()->get('charge_amount'), //need in decimal format
                'CurrencyCode' => $this->getCurrencyNumber($currency),//388,//$currency, // Must use numeric currency code (ISO 4217)
                'ThreeDSecure' => true,
                'AddressMatch' => false,
                'OrderIdentifier' => $input['order_id'].'__'.$input['payment_type'],
                'Source' => [
                    'CardPan' => $cardNumber, //card number for test 4012000000020071
                    'CardCvv' => $input['cvc'],
                    'CardExpiration' => $expiration_date, //Expiry date in YYMM format
                    'CardholderName' => $input['name']
                ],
                'BillingAddress' => [
                    'Line2' => 'line 2',
                    'City' => 'city',
                    'PostalCode' => '123456', // Postal or Zip code (required for AVS) Strictly Alphanumeric only - No special characters, no accents, no spaces, no dashes…etc.
                    'CountryCode' => 388,//'USA', //For USA ISO Code
                    'FirstName' => $input['name'],
                    'LastName' => ' ',
                    'Line1' => 'unknown',
                    'EmailAddress' =>  $request['payment_details']['email'] ?? 'example@example.com',
                ],

                'ExtendedData' => [
                    "ThreeDSecure" => [
                        "ChallengeWindowSize" =>  1, // Merchants preferred sized of challenge window presented to cardholder
                        /*
                        1 – 250 x 400
                        2 – 390x400
                        3 – 500x600
                        4 – 600x400
                        5 – 100%
                        */

                        "ChallengeIndicator" => "01" , // Conditional value – if supported
                        /*
                        01 = No preference
                        02 = No challenge requested
                        03 = Challenge requested: 3DS Requestor Preference
                        04 = Challenge requested: Mandate Default value if not provided is that ACS would interpret as: 01 = No preference.
                        */
                    ],
                    'MerchantResponseURL' => route('powertranzpoaymentgateway.tenant.shop.checkout.ipn')//$input['ipn_url'] //replace this with ipn url
                ]
            ]);
        $result = $response->object();
        if (property_exists($result,'RedirectData')){
            return $result->RedirectData;
        }
        $error_message = 'payment failed';
        if (property_exists($result,'Errors')){
            $error = $result->Errors;
            $error_message = current($error)->Message ?? __("payment credentials failed");
        }
        abort(501,$error_message);
    }

    public function ShopCheckoutIpn(Request $request){
        $payment_data = $this->verify_transaction();
        dd('Hello',$request->all(),$payment_data);
        //todo:: work on the ipn response to get the ipn verification
    }

    private function verify_transaction(){
        $SpiToken = request()->get('SpiToken');

        $payment_url = $this->base_url().'payment';
        $response = Http::post($payment_url,$SpiToken);
        $result = $response->object();
        if (!is_null($result)){
            $order_id = Str::of($result->OrderIdentifier)->before('__')->toString();
            $payment_type = Str::of($result->OrderIdentifier)->after('__')->toString();
            if ($result->Approved && $result->ResponseMessage === "Transaction is approved"){
                return $this->verified_data([
                    'transaction_id' => $result->TransactionIdentifier,
                    'order_id' => PaymentGatewayHelpers::unwrapped_id($order_id),
                    'payment_type' => $payment_type
                ]);
            }
        }

        return [
            'status' => 'failed',
            'order_id' => $order_id ?? null,
            'payment_type' => $payment_type ?? null
        ];
    }


    /**
     * payment gateway verified data return as payment_data
     * @method verified_data
     * @param $args
     * @return array $payment_data
     * */
    private function verified_data(array $args)
    {
        return array_merge(['status' => 'complete'], $args);
    }

    /**
     * write code for post process the payment data for tenant shop checkout
     * @method runPostPaymentProcessForTenantdShopCheckoutSuccessPayment
     * @param $payment_data
     * */
    private function runPostPaymentProcessForTenantdShopCheckoutSuccessPayment(array $payment_data)
    {
        if (isset($payment_data['status']) && $payment_data['status'] === 'complete') {
            $this->TenantShopCheckoutSendOrderMail($payment_data['order_id']);
            $order_id = wrap_random_number($payment_data['order_id']);
            ProductOrder::find($payment_data['order_id'])->update([
                'payment_status' => 'success'
            ]);

            Cart::instance("default")->destroy();
        }

    }

    /**
     * write code for post process the payment data for sending mail to admin and user about the product orders
     * @method TenantShopCheckoutSendOrderMail
     * @param $order_id
     * */
    private function TenantShopCheckoutSendOrderMail(mixed $order_id)
    {
        $order_details = ProductOrder::where('id', $order_id)->firstOrFail();
        $order_mail = get_static_option('order_page_form_mail') ?? get_static_option('tenant_site_global_email');

        try {
            //To User/Customer
            if ($order_details->checkout_type === 'digital')
            {
                Mail::to($order_mail)->send(new ProductOrderEmail($order_details));
            } else {
                Mail::to($order_mail)->send(new ProductOrderManualEmail($order_details));
            }

            // To Admin
            $admin_email = get_static_option('order_receiving_email') ?? get_static_option('tenant_site_global_email');
            if ($admin_email == null)
            {
                $admin = \App\Models\Admin::whereHas("roles", function($q){
                    $q->where("name", "Super Admin");
                })->first();
                $admin_email = $admin->email;
            }

            Mail::to($admin_email)->send(new ProductOrderEmailAdmin($order_details));

        } catch (\Exception $e) {

        }
    }

    private function chargeCustomerForShopCheckout($args)
    {
        // prepare code for make powertranz payment
        return view('powertranzpaymentgateway::powertransz', ['powertransz_data' => array_merge($args,[
            'merchant_id' => Crypt::encrypt( get_static_option('powertranz_merchant_id')),
            'currency' => get_static_option('site_global_currency'),
            'merchant_password' => Crypt::encrypt(get_static_option('powertranz_merchant_processing_password')),
            'gateway_key' =>  Crypt::encrypt(get_static_option('powertranz_gateway_key')),
            'charge_amount' => $this->charge_amount($args['total']),
            'environment' => !empty(get_static_option('powertranz_status')),
            'order_id' => PaymentGatewayHelpers::wrapped_id(($args['payment_details']['id'] ?? '000000'))
        ])]);
    }

    public function charge_amount($amount)
    {
        if (in_array(get_static_option('site_global_currency'), $this->supported_currency_list())){
            return $amount;
        }
        return $this->get_amount_in_usd($amount);
    }


    private function base_url(){
        return !empty(get_static_option('powertranz_status')) ? 'https://staging.ptranz.com/api/spi/' : 'https://TBD.ptranz.com/api/spi/';
    }

    public function supported_currency_list() : array
    {
        /*
         * Supported Currencies
         *
        United States (USD)
        East Caribbean (XCD)
        Trinidad and Tobago (TTD)
        Jamaica (JMD)
        Barbados (BBD)
        Bahamas (BSD)
        Belize (BZD)
        Dominican Republic (DOP)
        Guyana (GYD)
        Cayman Islands (KYD)
        Honduras (HNL)
        El Salvador (SVC)
        Costa Rica (CRC)
        Nicaragua (NIO)
        Panama (PAB)

         *
         * */
        return ['USD','XCD','TTD','JMD','BBD','BSD','BZD','DOP','GYD','KYD','HNL','SVC','CRC','NIO','PAB'];
    }

}

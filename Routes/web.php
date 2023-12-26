<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/* frontend routes */
Route::prefix('powertranzpoaymentgateway')->group(function() {
    Route::get("landlord-price-plan-powertranz",[\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayController::class,"landlordPricePlanIpn"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("powertranzpoaymentgateway.landlord.price.plan.ipn");

});


/* tenant payment ipn route*/
Route::middleware([
    'web',
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    PreventAccessFromCentralDomains::class
])->prefix('powertranzpoaymentgateway')->group(function () {
    Route::post("shop-checkout-powertranz-charge-customer",[\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayController::class,"ShopCheckoutChargeCustomer"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("powertranzpoaymentgateway.tenant.shop.checkout.charge");

    Route::post("shop-checkout-powertranz",[\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayController::class,"ShopCheckoutIpn"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("powertranzpoaymentgateway.tenant.shop.checkout.ipn");

});

/* admin panel routes landlord */
Route::group(['middleware' => ['auth:admin','adminglobalVariable', 'set_lang'],'prefix' => 'admin-home'],function () {
    Route::prefix('powertranzpoaymentgateway')->group(function() {
        Route::get('/settings', [\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayAdminPanelController::class,"settings"])
            ->name("powertranzpoaymentgateway.landlord.admin.settings");
        Route::post('/settings', [\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});


Route::group(['middleware' => [
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    PreventAccessFromCentralDomains::class,
    'auth:admin',
    'tenant_admin_glvar',
    'package_expire',
    'tenantAdminPanelMailVerify',
    'tenant_status',
    'set_lang'
    ],'prefix' => 'admin-home'],function () {
    Route::prefix('powertranzpoaymentgateway/tenant')->group(function() {
        Route::get('/settings', [\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayAdminPanelController::class,"settings"])
            ->name("powertranzpoaymentgateway.tenant.admin.settings");
        Route::post('/settings', [\Modules\PowertranzPaymentGateway\Http\Controllers\PowertranzPaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});


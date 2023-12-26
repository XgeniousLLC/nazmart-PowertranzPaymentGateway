<?php

namespace Modules\PowertranzPaymentGateway\Http\Controllers;

use App\Helpers\ModuleMetaData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PowertranzPaymentGatewayAdminPanelController extends Controller
{
    public function settings()
    {
        $all_module_meta_data = (new ModuleMetaData("PowertranzPaymentGateway"))->getExternalPaymentGateway();
        $powertranz = array_filter($all_module_meta_data,function ( $item ){
            if ($item->name === "Powertranz"){
                return $item;
            }
        });
        $powertranz = current($powertranz);
        return  view("powertranzpaymentgateway::admin.settings",compact("powertranz"));
    }

    public function settingsUpdate(Request $request){

        // $jsonModifier->nazmartMetaData->paymentGateway->test_mode = $request?->powertranz_test_mode_status === 'on';

        if(!is_null(tenant())){
            update_static_option('powertranz_status',$request->powertranz_status);
            update_static_option('powertranz_merchant_id',$request->powertranz_merchant_id);
            update_static_option('powertranz_merchant_processing_password',$request->powertranz_merchant_processing_password);
            update_static_option('powertranz_gateway_key',$request->powertranz_gateway_key);
        }

        if(is_null(tenant())){
            $jsonModifier = json_decode(file_get_contents("core/Modules/PowertranzPaymentGateway/module.json"));
//            $jsonModifier->nazmartMetaData->paymentGateway->status = $request?->powertranz_status === 'on';
//            $jsonModifier->nazmartMetaData->paymentGateway->test_mode = $request?->powertranz_test_mode_status === 'on';
//            $jsonModifier->nazmartMetaData->paymentGateway->admin_settings->show_admin_landlord = $request?->powertranz_landlord_status === 'on';
            $jsonModifier->nazmartMetaData->paymentGateway->admin_settings->show_admin_tenant = $request?->powertranz_tenant_status === 'on';

            file_put_contents("core/Modules/PowertranzPaymentGateway/module.json",json_encode($jsonModifier));
        }



        return back()->with(["msg" => __("Settings Update"),"type" => "success"]);
    }
}

@extends(route_prefix()."admin.admin-master")
@section('title') {{__('Powertranz  Settings')}}@endsection
@section("content")
    <div class="col-12 stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">{{__('Powertranz Settings')}}</h4>
                <x-error-msg/>
                <x-flash-msg/>
                <form class="forms-sample" method="post" action="{{route('powertranzpoaymentgateway.'.route_prefix().'admin.settings')}}">
                    @csrf
                    @if(is_null(tenant()))
                    <x-fields.switcher label="{{__('Powertranz Enable/Disable Tenant Websites')}}" name="powertranz_tenant_status" value="{{$powertranz->admin_settings->show_admin_tenant}}"/>
                    @else
                        <x-fields.switcher label="{{__('Powertranz Enable/Disable')}}" name="powertranz_status" value="{{get_static_option('powertranz_status')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('powertranz_merchant_id')}}" name="powertranz_merchant_id" label="{{__('Powertranz Merchant ID')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('powertranz_merchant_processing_password')}}" name="powertranz_merchant_processing_password" label="{{__('Powertranz Merchant Processing Password')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('powertranz_gateway_key')}}" name="powertranz_gateway_key" label="{{__('Powertranz Gateway Key')}}"/>
                    @endif
                    <button type="submit" class="btn btn-gradient-primary mt-5 me-2">{{__('Save Changes')}}</button>
                </form>
            </div>
        </div>
    </div>
@endsection

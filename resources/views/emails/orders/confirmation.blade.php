
@component('mail::message')
# Order Confirmation

Thank you for your order!

Order ID: #{{ $order->id }}
Total Amount: ${{ $order->total_amount }}

@component('mail::button', ['url' => config('app.url')])
View Your Order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent

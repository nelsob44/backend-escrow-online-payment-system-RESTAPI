<?php

namespace App\Http\Controllers;

use App\Payment;
use Illuminate\Http\Request;
use App\Stripe;
use App\PayPalClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use App\Http\Resources\Payment as PaymentResource;
use App\Http\Resources\Payments as PaymentResourceCollection;

use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');               
    }
    /**
     * Display a listing of payments completed by the buyers, but not released to sellers.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {        
        if(auth()->user()->status > 2){
                        
            $items = Payment::where('transaction_completed', false)->paginate(5);
                                
        }else{
            
            $items = Payment::where([
                ['buyer_email', auth()->user()->email],
                ['transaction_completed', false]
            ])->paginate(5);
        }  
        
        $pendingPayments = [];
       
        \Stripe\Stripe::setApiKey(config('app.stripekey'));
        $client = PayPalClient::client();
                
        foreach($items as $item) {
            try {
                $event = null;
                                
               
                if($item->payment_option === 'stripe') {

                    //Retrieve confirmed stripe payments
                    $event = \Stripe\PaymentIntent::retrieve($item->intent_id);
                    
                    if($event->status !== 'succeeded' || (($item->amount_paid * 100) != $event->amount_received)) {
                       
                        $item->payment_status = $event->status;
                        $item->currency = $event->charges->data[0]->currency;                     
                        $item->correct_payment = false;                                                              
                    } else {
                        
                        $item->payment_status = $event->status;
                        $item->currency = $event->charges->data[0]->currency;
                        $item->correct_payment = true;
                    }                    
                                       
                } elseif($item->payment_option === 'paypal') {

                    //Retrieve confirmed paypal payments
                    $request = new OrdersGetRequest($item->paypal_order_id);
                    $request->headers["prefer"] = "return=representation";
                    $response = $client->execute($request);
                    $answer = json_encode($response->result);
                    
                    $answertwo = json_decode($answer, true);
                    \Log::info($answertwo['status']);
                                       
                    if($answertwo['status'] !== 'COMPLETED' || (($item->amount_paid) != $answertwo['purchase_units'][0]['items'][0]['unit_amount']['value'])) {
                       
                        $item->payment_status = 'failed';
                        $item->currency = $answertwo['purchase_units'][0]['items'][0]['unit_amount']['currency_code'];                     
                        $item->correct_payment = false;                                                              
                    } else {
                        
                        $item->payment_status = 'succeeded';
                        $item->currency = $answertwo['purchase_units'][0]['items'][0]['unit_amount']['currency_code'];                     

                        $item->correct_payment = true;
                    }

                }
                unset($item->intent_id);
                unset($item->paypal_order_id);
                unset($item->transaction_completed);
                unset($item->updated_at);
                unset($item->updbuyer_id);
                unset($item->seller_id);
                unset($item->buyer_id);
                unset($item->amount_received);
                unset($item->commission);
                
                array_push($pendingPayments, $item);            
                
            } catch(\UnexpectedValueException $e) {
                // Invalid payload
                http_response_code(400);
                exit();
            }
        }    
           
        return new PaymentResource($pendingPayments);         
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function storePaypalOnApprove(Request $request)
    {        
        $paymentOption = 'paypal';
        $itemId = $request->itemId;
        $itemName = $request->itemName;
        $amount = $request->amount;
        $realAmount = $request->realAmount;
        $currency = $request->currency;
        $paypalOrderId = $request->paypalOrderId;
        $itemPrice = $request->itemPrice;
        
        $commission = $request->commission;
        $buyerName = $request->buyer;
        $connectionChannel = $request->connectionChannel;
        $buyerEmail = $request->buyerEmail;
        $itemDescription = $request->itemDescription;
        $sellerId = $request->seller_id;
        $sellerEmail = $request->seller_email;
        $itemModelNo = $request->itemModelNo;
        $imeiFirst = $request->imeiFirst;
        $imeiLast = $request->imeiLast;
        

        $payment = Payment::create(
            [
               'item_id' => $itemId,
               'paypal_order_id' => $paypalOrderId,
               'payment_option' => $paymentOption,
               'currency' => $currency,
               'amount_paid' => $amount,
               'item_price' => $itemPrice,
               'amount_received' => $realAmount,
               'commission' => $commission,
               'buyer_name' => $buyerName,
               'buyer_email' => $buyerEmail,
               'seller_id' => $sellerId,
               'seller_email' => $sellerEmail,
               'buyer_id' => auth()->user()->id,
               'item_description' => $itemDescription                      
            ]
        );

        return response(['payment' => $payment]);
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeStripePayment(Request $request)
    {  
        $intentId = $request->intentId;
        $itemId = $request->itemId;
        $paymentOption = 'stripe';
        $realAmount = $request->realAmount;
        $currency = $request->currency;
        $itemPrice = $request->itemPrice;
        $amountReceived = +$realAmount - (+$itemPrice * 0.0145);
        $commission = $amountReceived - $itemPrice;
        $buyerName = $request->buyer;
        $buyerEmail = $request->buyerEmail;
        $itemDescription = $request->itemDescription;
        $sellerId = $request->sellerId;
        $sellerEmail = $request->sellerEmail;


        $payment = Payment::create(
            [
               'intent_id' => $intentId,
               'item_id' => $itemId,
               'payment_option' => $paymentOption,
               'currency' => $currency,
               'amount_paid' => $realAmount,
               'item_price' => $itemPrice,
               'amount_received' => $amountReceived,
               'commission' => $commission,
               'buyer_name' => $buyerName,
               'buyer_email' => $buyerEmail,
               'seller_id' => $sellerId,
               'seller_email' => $sellerEmail,
               'buyer_id' => auth()->user()->id,
               'item_description' => $itemDescription,
               'payment_completed' => true              
            ]
        );

        return response(['payment' => $payment]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Payment  $payment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Payment $payment)
    {
        //
    }
}

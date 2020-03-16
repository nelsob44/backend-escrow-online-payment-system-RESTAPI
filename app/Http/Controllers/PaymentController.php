<?php

namespace App\Http\Controllers;

use App\Payment;
use Illuminate\Http\Request;
use App\Stripe;
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if(auth()->user()->status > 2){
                        
            $items = Payment::where('payment_completed', false)->paginate(5);
                                
        }else{
            
            $items = Payment::where([
                ['buyer_email', auth()->user()->email],
                ['payment_completed', false]
            ])->paginate(5);
        }
        
        $pendingStripePayments = [];
                
        foreach($items as $item) {
            try {
                $event = null;
                                
                \Stripe\Stripe::setApiKey(env('STRIPE_KEY'));
                if($item->payment_option === 'stripe') {
                    $event = \Stripe\PaymentIntent::retrieve($item->intent_id);
                    if($event->status !== 'succeeded' || (($item->real_amount * 100) != $event->amount_received)) {
                                        
                        $item->payment_status = $event->status;
                        $item->currency = $event->charges->data[0]->currency;                     
                        $item->correct_payment = false;                                                              
                    } else {
                        
                        $item->payment_status = $event->status;
                        $item->currency = $event->charges->data[0]->currency;
                        $item->correct_payment = true;
                    }                    
                                       
                }    
                unset($item->payment_completed);
                unset($item->updated_at);
                unset($item->updbuyer_id);
                unset($item->seller_id);
                unset($item->buyer_id);
                unset($item->amount_received);
                unset($item->commission);
                
                array_push($pendingStripePayments, $item);            
                
            } catch(\UnexpectedValueException $e) {
                // Invalid payload
                http_response_code(400);
                exit();
            }
        }        
        return new PaymentResource($pendingStripePayments);         
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
        $paymentOption = 'stripe';
        $realAmount = $request->realAmount;
        $currency = $request->currency;
        $itemPrice = $request->itemPrice;
        $amountReceived = $realAmount - ($itemPrice * 0.0145);
        $commission = $amountReceived - $itemPrice;
        $buyerName = $request->buyer;
        $buyerEmail = $request->buyerEmail;
        $itemDescription = $request->itemDescription;
        $sellerId = $request->sellerId;
        $sellerEmail = $request->sellerEmail;


        $payment = Payment::create(
            [
               'intent_id' => $intentId,
               'payment_option' => $paymentOption,
               'currency' => $currency,
               'real_amount' => $realAmount,
               'item_price' => $itemPrice,
               'amount_received' => $amountReceived,
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

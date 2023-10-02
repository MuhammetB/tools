<?php

namespace App\Trait;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

trait NotificationTemplate
{
    public function newOrderMsg($order)
    {
        return View::make('template.newOrder', ['order' => $order])->render();
    }

    public function newOfferMsg($order, $offer)
    {
        $createdDate = Carbon::parse($order?->first_offer?->created_at ?? $offer->created_at);
        $currentDate = Carbon::now();
        $remainingTime = $currentDate->diffInSeconds($createdDate->addMinutes(config('app.max_order_destroy_time')+1));
        $remainingMinutes = floor($remainingTime / 60);
        $remainingSeconds = $remainingTime % 60;
        return View::make('template.newOffer', [
            'order' => $order,
            'offer' => $offer,
            'max_order_destroy_time' => "$remainingMinutes:$remainingSeconds"
        ])->render();
    }

    public function offerAcceptedMsg($order, $offer)
    {

        return View::make('template.offerAccepted'
            , ['order' => $order, 'offer' => $offer]
        )->render();
    }
    public function AcceptedOrderResultGroupMsg($order, $offer,$groups)
    {

        return View::make('template.SuccessBotOrder'
            , ['order' => $order, 'offer' => $offer,'groups'=>$groups]
        )->render();
    }

    public function offerCancelledMsg($order, $percent, $type)
    {
//        $createdDate = Carbon::parse($order->first_offer->created_at);
//        $newDate = $createdDate->addMinutes(config('app.max_order_destroy_time'));

        $createdDate = Carbon::parse($order->first_offer->created_at);
        $currentDate = Carbon::now();
        $remainingTime = $currentDate->diffInSeconds($createdDate->addMinutes(config('app.max_order_destroy_time')));
        $remainingMinutes = floor($remainingTime / 60);
        $remainingSeconds = $remainingTime % 60;
        return View::make('template.offerCancelled', [
            'order' => $order,
            'percent' => $percent,
            'type' => $type,
            'max_order_destroy_time' => "$remainingMinutes:$remainingSeconds"
        ])->render();
    }

    public function SuccessOrderMsg($order, $offer)
    {

        return View::make('template.SuccessOrder'
            , ['order' => $order, 'offer' => $offer]
        )->render();
    }
}

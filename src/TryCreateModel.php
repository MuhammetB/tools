<?php

namespace App\Trait;

use App\Jobs\WhatsDeleteTask;
use App\Models\AiParse;
use App\Models\AiRequest;
use App\Models\City;
use App\Models\Offer;
use App\Models\Order;
use App\Models\Webhook;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

trait TryCreateModel
{
use WhatsAppApi,NotificationTemplate;
    public function tryCreateOrder(AiParse $ai_parse, $user, Webhook $webhook)
    {
        $response_msg = null;
        $response = $ai_parse->OpenAiGetOrderParse($user->id);
        if (!$user->hasRole("can_make_{$response['type']}_order")) {
            $response_msg = __("You can't send this Type Of Order");
        } elseif ($ai_parse->delivery) {
            $response_msg = __("there are no delivery");
        } elseif ((float)$response['amount'] > (float)$user->max_order) {
            $response_msg = __("Your max order amount is") . $user->max_order;
        } elseif (!$response['city']) {
            $response_msg = __("Sorry, We don't provide services in this region at this moment");
        } elseif (!$response['currency']) {
            $response_msg = __("Sorry, this currency is not currently available");
        } elseif (!$response['amount']) {
            $response_msg = __("amount not clear");
        } elseif (City::query()->findOrFail($response['city'])->users->count() === 0) {
            $response_msg = __("Sorry, We don't provide services in this region at this moment");
        } else {
            $order = Order::createNewOrder([
                'type' => $response['type'],
                'city' => $response['city'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
                'money_type' => $ai_parse->money_type,
                'time' => $ai_parse->during,
                'description' => $response_msg,
            ], $user->id);
            $AiRequest = AiRequest::findOrFail($response['AiRequestId']);
            $AiRequest->order_id = $order->id;
            $AiRequest->user_id = $user->id;
            $AiRequest->save();
            $webhook->order_id = $order->id;
            $webhook->save();
            $response_msg = View::make('template.orderCreated', ['order' => $order])->render();
            $this->sendWhatsAppApiDirect(["priority"=>"0","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, "referenceId" =>"*Order$order->id*" , "msg_type" => "create_order"]);
            return null;
        }
        $this->sendWhatsAppApiDirect(["priority"=>"9","to" =>$user->group_id , "body" =>$response_msg , "msgId" =>$webhook->wh_server_id ,  "msg_type" => "create_order_failed"]);
        return null;
    }

    public function tryCancelOrder($user, $order, Webhook $webhook)
    {
        if ($order->status !== 'done') {
            $order->status = 'cancelled';
            $order->save();
            $order->offers()->where('status', 'waiting')->update(['status' => 'cancelled']);
            WhatsDeleteTask::dispatch($order->id)->onQueue('delete');
            $response_msg = __('Order cancelled successfully');

            $this->sendWhatsAppApiDirect(["priority"=>"8","to" =>$user->group_id , "body" =>$response_msg , "msgId" => $webhook->wh_server_id, "referenceId" =>"*Order$order->id*" , "msg_type" => "order_cancelled"]);
        } else {
            $response_msg = __('cant cancel successful order');

            $this->sendWhatsAppApiDirect(["priority"=>"9","to" => $user->group_id, "body" =>$response_msg , "msgId" => $webhook->wh_server_id, "referenceId" =>"*Order$order->id*" , "msg_type" =>'order_cancelled_failed' ]);

        }
    }

    public function tryCreateOffer(AiParse $ai_parse, $user, $order, Webhook $webhook)
    {
        $response = (object)$ai_parse->OpenAiGetOfferParse($user->id, $order);
        $response_msg = null;
        if ($response->percent !== null) {
            if ((float)$order->amount > (float)$user->max_offer) {
                $response_msg = __("Your max offer amount is") . $user->max_offer;
            } elseif (($response->percent < 0 || (int)($response->percent * 100) != ($response->percent * 100)) || ($response->percent === 0 && $order->currency->type === 'cut')) {
                $response_msg = __("sent number is not correct");
            } elseif ($order->currency->type === 'cut' && $response->type === 'back' && $order->type === 'have') {
                $response_msg = __("can't send this type of cut offer");
            } elseif ($order->created_by === $user->id) {
                $response_msg = __("You can't make Offer for your order");
            } elseif ($order->last_offer?->created_by === $user->id) {
                $response_msg = __("You already have the best offer.");
            } elseif ($order->status === 'done') {
                $response_msg = __("this order has been already taken");
            } elseif ($order->status === 'cancelled') {
                $response_msg = __("this order has been cancelled,OrderTime Out");
            } elseif (!in_array($order->city->id, $user->cities->pluck('id')->toArray(), true)) {
                $response_msg = __("you dont have permission to send to this offer to this order");
            } elseif ($order->first_offer && $order->status === 'waiting') {
                $currentTime = Carbon::now();
                $createdAt = Carbon::parse($order->first_offer->created_at);
                if ((int)$currentTime->diffInMinutes($createdAt) >= (int)config('app.max_order_destroy_time')) {
                    if ((int)$currentTime->diffInMinutes($createdAt) >= (int)config('app.max_order_destroy_time') + 1) {
                        WhatsDeleteTask::dispatch($order->id)->onQueue('delete');
                        if ($order->status === 'waiting') {
                            $order->offers()->where('status', 'waiting')->update(["status" => 'cancelled']);
                            $order->status = 'cancelled';
                            $order->save();
                        }
                    }
                    $response_msg = __("this order has been cancelled,OrderTime Out");
                } elseif ($order?->last_offer?->type === $response->type) {
                    if ($order->currency->type === 'cut') {
                        $offerDetails = " : {$order->last_offer->percent} " . __('cut');
                    } else {
                        $offerDetails = " " . __($order->last_offer->type) . ": {$order->last_offer->percent} " . __('thousand');
                    }
                    if ($order->last_offer->percent <= $response->percent && $response->type === 'commission') {
                        $response_msg = __("there is another offer with best price") . $offerDetails;
                    } elseif ($order->last_offer->percent >= $response->percent && $response->type === 'back') {
                        $response_msg = __("there is another offer with best price") . $offerDetails;

                    }
                }
                if ($order->last_offer->type === 'back' && $response->type === 'commission') {
                    $offerDetails = " " . __($order->last_offer->type) . ": {$order->last_offer->percent} " . __('thousand');
                    $response_msg = __("there is another offer with best price") . $offerDetails;
                }
            }
            if (!$response_msg) {
                $offer = Offer::createNewOffer(['percent' => $response->percent, 'type' => $response->type], $order, $user->id);
                $response_msg = View::make('template.offerCreated', ['order' => $order, 'offer' => $offer])->render();
                if ($response->AiRequestId) {
                    $AiRequest = AiRequest::findOrFail($response->AiRequestId);
                    $AiRequest->order_id = $order->id;
                    $AiRequest->offer_id = $offer->id;
                    $AiRequest->user_id = $user->id;
                    $AiRequest->save();
                }
                $webhook->order_id = $order->id;
                $webhook->offer_id = $offer->id;
                $webhook->save();

            $this->sendWhatsAppApiDirect(["priority"=>"0","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*Offer$offer->id*", "msg_type" => "create_offer"]);
                return null;
            }
            $this->sendWhatsAppApiDirect(["priority"=>"8","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*", "msg_type" => "create_offer_failed"]);
            return null;

        }

    }

    public function tryAcceptOffer(AiParse $ai_parse, $user, Webhook $webhook)
    {
        $offer = $ai_parse->parseOfferId();
        if ($offer) {
            $currentTime = Carbon::now();
            if ($offer->order->created_by !== $user->id) {
                $response_msg = __("can't found this order");
            } elseif ($offer->order->status === 'done') {
                $response_msg = __('this order has been already taken');
            } elseif ($offer->order->status === 'cancelled') {
                $response_msg = __('this order has been cancelled');
            } elseif ($offer->order->last_offer->id !== $offer->id) {
                $response_msg = __('Offer time out. There is another offer please check');
            } elseif ($currentTime->diffInMinutes(Carbon::parse($offer->order->first_offer->created_at)) >= config('app.max_order_destroy_time') + 1) {
                $response_msg = __('Offer time out');
            } else {
                $order = $offer->order;

//                $wh_mut_msgs = $order->sent_webhooks()->get();
//                foreach ($wh_mut_msgs as $wh_mut_msg) {
//                    if ($wh_mut_msg['offer_id'] !== null && $wh_mut_msg['offer_id'] !== $offer->id) {
//                        $this->deleteWhatsAppApiDirect($wh_mut_msg['wh_server_id']);
//                    } elseif (!in_array($wh_mut_msg['user_id'], [$order->created_by, $offer->created_by])) {
//                        $this->deleteWhatsAppApiDirect($wh_mut_msg['wh_server_id']);
//                    }
//                }

                $order->offers()->where('status', 'waiting')->update(['status' => 'cancelled']);
                $offer->status = 'accepted';
                $offer->save();
                $order->status = 'done';
                $order->taken_by = $offer->created_by;
                $order->save();
                $msg = $this->offerAcceptedMsg($order, $offer);
                $this->sendWhatsAppApiDirect(["priority"=>"8","to" => $offer->created_by_user->group_id, "body" => $msg, "referenceId" => "*Order$order->id*Offer$offer->id*", "msg_type" => "offer_accepted"]);
                $groups = $this->getWhatsAppGroups();
                $result_groups_msg = $this->AcceptedOrderResultGroupMsg($order, $offer, $groups);
                $this->sendWhatsAppApiDirect(["priority"=>"8","to" => config('ultramsg.result_groups'), "body" => $result_groups_msg, "referenceId" => "*Order$order->id*Offer$offer->id*", "msg_type" => "offer_accepted_bot"]);
                $response_msg = $this->SuccessOrderMsg($order, $offer);
                $this->sendWhatsAppApiDirect(["priority"=>"8","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*Offer$offer->id*", "msg_type" => "accept_offer"]);

                return null;
            }
            $order = $offer->order;
            $this->sendWhatsAppApiDirect(["priority"=>"9","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*Offer$offer->id*", "msg_type" => "accept_offer_failed"]);
            return null;

        } else {
            $order = $ai_parse->parseOrderId();
            $response_msg = 'Please select Offer Msg';
            $this->sendWhatsAppApiDirect(["priority"=>"9","to" => $user->group_id, "body" => $response_msg, "msgId" => $webhook->wh_server_id, 'referenceId' => "*Order$order->id*", 'msg_type' => "accept_offer_failed"]);
            return null;
        }
    }

    public function tryCheck($order, $user, Webhook $webhook)
    {
        if ($order->status === 'done') {
            $currentTime = Carbon::now();
            $oneHourAgo = $currentTime->subHour();
            if ($order->sent_webhooks()->where('msg_type', 'check_order')->where('created_at', '>=', $oneHourAgo)->where('user_id', $user->id)->first()) {
                $this->sendWhatsAppApiDirect(["priority"=>"9","to" => $user->group_id, "body" => 'يمكنكم إرسال طلب متابعة واحد خلال الساعة', "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*", "msg_type" => "check_order_failed"]);
            } else {
                $wh_offer_accepted_bot = $order->sent_webhooks()->where('msg_type', 'offer_accepted_bot')->first();
                $this->sendWhatsAppApiDirect(["priority"=>"8","to" => config('ultramsg.result_groups'), "body" => "قام $user->group_name بإرسال طلب متابعة", "msgId" => $wh_offer_accepted_bot->wh_server_id, "referenceId" => "*Order$order->id*User$user->id*", "msg_type" => "check_order"]);
            }
        } else {
            $this->sendWhatsAppApiDirect(["priority"=>"9","to" => $user->group_id, "body" => 'العمل لم يتم', "msgId" => $webhook->wh_server_id, "referenceId" => "*Order$order->id*", "msg_type" => "check_order_failed"]);
        }
    }
}

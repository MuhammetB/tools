<?php

namespace App\Trait;


use App\Jobs\ProcessTimeConsumingPusherTask;
use App\Notifications\AppReloadData;

trait ReloadData
{
    public static function PushReloadOrders($order)
    {
//        $users = $order->city->users()->where('max_offer', '>=', (float)$order->amount);
//        $user_ids = $users->pluck('id')->toArray();
//        if (count($user_ids) > 0) {
//            ProcessTimeConsumingPusherTask::dispatch(['user_ids' => $user_ids], 'reload_orders');
//        }
    }

    public static function PushReloadOrder($order)
    {
//        ProcessTimeConsumingPusherTask::dispatch(['order_id' => $order->id], 'reload_order');

    }

    public static function PushReloadMyOrders($order)
    {
//        ProcessTimeConsumingPusherTask::dispatch(['user_id' => $order->created_by], 'reload_my_orders');

    }

    public static function PushReloadMyOffers($order)
    {
//        $users = $order->city->users()->whereHas('offers', function ($query) use ($order) {
//            $query->where('order_id', $order->id);
//        });
//        $user_ids = $users->pluck('id')->toArray();
//        if (count($user_ids) > 0) {
//        ProcessTimeConsumingPusherTask::dispatch(['user_ids' => $user_ids], 'reload_my_offers');
////            event(new AppReloadData(['user_ids' => $user_ids], 'reload_my_offers'));
//        }
    }
}

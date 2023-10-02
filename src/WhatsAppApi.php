<?php

namespace App\Trait;


use App\Jobs\WhatsSendTask;
use App\Models\Webhook;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait WhatsAppApi
{
    public static function sendWhatsAppApi($data)
    {
        if ((config('ultramsg.enable')) && $data['to'] && $data['body']) {
            WhatsSendTask::dispatch($data)->onQueue('whats');
        }
    }

    public function sendDeleteWhatsAppRequest($msgId)
    {

        Http::post(config('ultramsg.url') . "messages/delete", [
            'token' => config('ultramsg.token'),
            'msgId' => $msgId,
        ]);
    }

    public function deleteWhatsAppApiDirect($msgId)
    {

        if ((config('ultramsg.enable')) && $msgId) {
            if (is_array($msgId)) {
                foreach ($msgId as $element) {
                    if ($element) {
                        $this->sendDeleteWhatsAppRequest($element);
                    }
                }
            } else {
                $this->sendDeleteWhatsAppRequest($msgId);
            }
        }
    }

    public function sendWhatsAppApiDirect($data)
    {
        if ((config('ultramsg.enable')) && isset($data['to']) && isset($data['body'])) {
            $response = Http::post(config('ultramsg.url') . "messages/chat", [
                'token' => config('ultramsg.token'),
                'to' => $data['to'] ?? null,
                'body' => $data['body'] ?? null,
                'mentions' => $data['mentions'] ?? null,
                'msgId' => $data['msgId'] ?? null,
                'referenceId' => $data['referenceId'] ?? null,
                'priority' => $data['priority'] ?? 10,
            ]);

            $response = json_decode($response->body());
            if (is_array($response)) {
                foreach ($response as $object) {
                    $this->addEmptyWebhook($object->id, $data['body'], $data['msg_type'] ?? null, $data['referenceId'] ?? null);
                }
            } else {
                $this->addEmptyWebhook($response->id, $data['body'], $data['msg_type'] ?? null, $data['referenceId'] ?? null);
            }
//            while (Webhook::query()->where('wh_event_type', 'message_sent')->count() > 0) {
//                sleep(1);
//            }
        }
    }

    public static function fetchWhatsAppGroups()
    {
        $response = Http::get(config('ultramsg.url') . "groups", [
            'token' => config('ultramsg.token'),
        ]);
        File::put(resource_path('jsonData/WpGroupsData.json'), $response);
        return 'success';
    }

    public static function getWhatsAppGroups()
    {
        $WpGroupsData = json_decode(File::get(resource_path('jsonData/WpGroupsData.json')), true);
        $data = [];
        foreach ($WpGroupsData as $WpGroup) {
            $WpGroup = (object)$WpGroup;
            if (Str::contains($WpGroup->name, 'البوابة')) {
                $data[$WpGroup->id] = $WpGroup->name;
            }
        }
        return $data;

    }

    function addEmptyWebhook($id, $textMsg = null, $msg_type = null, $referenceId)
    {
        Webhook::query()->create([
            'wh_id' => $id,
            'msg_type' => $msg_type,
            'wh_body' => $textMsg,
            'wh_event_type' => 'message_sent',
            'wh_referenceId' => $referenceId,
        ]);
    }
}

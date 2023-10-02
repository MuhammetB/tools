<?php

namespace App\Trait;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ApiResponses
{


    public function dataWithMessage($data, $message, $code = 200): \Illuminate\Http\JsonResponse
    {
//      DB::commit();
        $data['api_response_message'] = __($message);
        return $this->FinalResponse($data, $code);
    }


    public function data($data, $code = 200): \Illuminate\Http\JsonResponse
    {
        return $this->FinalResponse($data, $code);
    }

    public function success($message, $code = 200): \Illuminate\Http\JsonResponse
    {
//      DB::commit();
        return $this->FinalResponse(['api_response_message' => __($message)], $code);
    }

    public function validationError($errors, $code = 422): \Illuminate\Http\JsonResponse
    {
//      DB::rollBack();
        return $this->FinalResponse([
            "api_response_error" =>__( "The given data was invalid."),
            "errors" => $errors], $code);
    }

    public function error($error, $code = 301): \Illuminate\Http\JsonResponse
    {
//      DB::rollBack();
        return $this->FinalResponse(['api_response_message' => __($error)], $code);
    }

    public function FinalResponse($data, $code): \Illuminate\Http\JsonResponse
    {
        return response()->json($data, $code);
    }


}


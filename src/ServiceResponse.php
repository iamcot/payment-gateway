<?php namespace Iamcot\PaymentGateway;

class ServiceResponse {
    public $status;
    public $errorCode;
    public $data;

    function __construct($status, $errorCode, $data = null) {
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->data = $data;
    }
}
<?php

class ControllerExtensionPaymentMoka extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/moka');
        $this->load->model('extension/payment/moka');

        $data = array();

        $data['months'] = $this->model_extension_payment_moka->getMonths();
        $data['years'] = $this->model_extension_payment_moka->getYears();

        $data['checkout'] = html_entity_decode($this->url->link('extension/payment/moka/checkout', '', true), ENT_COMPAT, 'UTF-8');
        $data['installmentTable'] = html_entity_decode($this->url->link('extension/payment/moka/installment-table', '', true), ENT_COMPAT, 'UTF-8');

        return $this->load->view('extension/payment/moka', $data);
    }

    public function checkout()
    {
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->library('moka');

        $cookies = array('PHPSESSID', 'OCSESSID', 'default');

        foreach ($cookies as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                $this->setcookieSameSite($cookie, $_COOKIE[$cookie], time() + 86400, "/", $_SERVER['SERVER_NAME'], true, true);
            }
        }

        $data = array();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $order_id = $this->session->data['order_id'];
            $order_info = $this->model_checkout_order->getOrder($order_id);

            $total_formatted = $this->currency->format($order_info['total'], $order_info['currency_code'], false, false);

            $card_holder_full_name = $this->request->post['card_holder_full_name'];
            $card_number = str_replace(' ', '', $this->request->post['card_number']);
            $exp_month = $this->request->post['card_expiry_month'];
            $exp_year = $this->request->post['card_expiry_year'];
            $cvc_number = $this->request->post['card_cvc_number'];
            $installment = $this->request->post['installment'] ?? null;
            $currency = $order_info['currency_code'] == 'TRY' ? 'TL' : $order_info['currency_code'];
            $callback_url = html_entity_decode($this->url->link('extension/payment/moka/callback', '', true), ENT_COMPAT, "UTF-8");

            $options = [
                'dealerCode' => $this->config->get('payment_moka_dealer_code'),
                'username' => $this->config->get('payment_moka_username'),
                'password' => $this->config->get('payment_moka_password'),
            ];

            if ($this->config->get('payment_moka_api_environment') == 'test') {
                $options['baseUrl'] = 'https://service.refmoka.com';
            }

            $moka = new \Moka\MokaClient($options);

            $request = new Moka\Model\CreatePaymentRequest();

            $request->setCardHolderFullName($card_holder_full_name);
            $request->setCardNumber($card_number);
            $request->setExpMonth($exp_month);
            $request->setExpYear($exp_year);
            $request->setCvcNumber($cvc_number);

            $request->setAmount($total_formatted);
            $request->setCurrency($currency);
            $request->setClientIp($order_info['ip']);
            $request->setOtherTrxCode($order_id);
            $request->setSoftware('OPENCART');
            $request->setReturnHash(1);
            $request->setRedirectUrl($callback_url);

            if ($installment) {
                $retrieveInstallmentInfoRequest = new Moka\Model\RetrieveInstallmentInfoRequest();
                $retrieveInstallmentInfoRequest->setBinNumber(substr($card_number, 0, 6));
                $retrieveInstallmentInfoRequest->setCurrency($currency);
                $retrieveInstallmentInfoRequest->setOrderAmount($total_formatted);
                $retrieveInstallmentInfoRequest->setIsThreeD(1);
                $retrieveInstallmentInfoRequest->setIsIncludedCommissionAmount(1);

                $installmentInfo = $moka->payments()->retrieveInstallmentInfo($retrieveInstallmentInfoRequest);
                $installmentInfoData = $installmentInfo->getData();

                if (isset($installmentInfoData->BankPaymentInstallmentInfoList[0])) {
                    $paymentInstallmentInfoList = $installmentInfoData->BankPaymentInstallmentInfoList[0]->PaymentInstallmentInfoList;

                    if (isset($paymentInstallmentInfoList[$installment - 1])) {
                        $request->setInstallmentNumber($installment);
                        $request->setAmount($paymentInstallmentInfoList[$installment - 1]->Amount);
                    }
                }
            }

            $payment = $moka->payments()->createThreeds($request);

            $payment_result_code = $payment->getResultCode();
            $payment_data = $payment->getData();

            if ($payment_result_code == 'Success') {
                if (isset($payment_data->Url)) {
                    $data['redirect'] = $payment_data->Url;
                }
                if (isset($payment_data->CodeForHash)) {
                    $this->session->data['codeForHash'] = $payment_data->CodeForHash;
                }
            }

            if ($payment_result_code !== 'Success') {
                $data['error_warning'] = $this->language->get($payment_result_code);
            }
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function installmentTable()
    {
        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->library('moka');

        $options = [
            'dealerCode' => $this->config->get('payment_moka_dealer_code'),
            'username' => $this->config->get('payment_moka_username'),
            'password' => $this->config->get('payment_moka_password'),
        ];

        if ($this->config->get('payment_moka_api_environment') == 'test') {
            $options['baseUrl'] = 'https://service.refmoka.com';
        }

        $moka = new \Moka\MokaClient($options);

        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $currency = $order_info['currency_code'] == 'TRY' ? 'TL' : $order_info['currency_code'];
        $total_formatted = $this->currency->format($order_info['total'], $order_info['currency_code'], false, false);

        $retrieveInstallmentInfoRequest = new Moka\Model\RetrieveInstallmentInfoRequest();
        $retrieveInstallmentInfoRequest->setBinNumber($this->request->post['bin_number']);
        $retrieveInstallmentInfoRequest->setCurrency($currency);
        $retrieveInstallmentInfoRequest->setOrderAmount($total_formatted);
        $retrieveInstallmentInfoRequest->setIsThreeD(1);
        $retrieveInstallmentInfoRequest->setIsIncludedCommissionAmount(1);

        $response = $moka->payments()->retrieveInstallmentInfo($retrieveInstallmentInfoRequest);

        $data = $response->getData();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function callback()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/moka');
        $this->load->library('moka');

        if (isset($this->request->post['hashValue'])) {
            $code_for_hash = hash('sha256', $this->session->data['codeForHash'] . 'T');
            $hashValue = $this->request->post['hashValue'];
            $order_id = $this->session->data['order_id'];

            if ($code_for_hash == $hashValue) {
                $options = [
                    'dealerCode' => $this->config->get('payment_moka_dealer_code'),
                    'username' => $this->config->get('payment_moka_username'),
                    'password' => $this->config->get('payment_moka_password'),
                ];

                if ($this->config->get('payment_moka_api_environment') == 'test') {
                    $options['baseUrl'] = 'https://service.refmoka.com';
                }

                $moka = new \Moka\MokaClient($options);

                $paymentDetailRequest = new \Moka\Model\RetrievePaymentDetailRequest();
                $paymentDetailRequest->setOtherTrxCode($order_id);

                $paymentDetail = $moka->payments()->retrieve($paymentDetailRequest);

                $paymentDetail_result_code = $paymentDetail->getResultCode();
                $paymentDetail_data = $paymentDetail->getData();

                if ($paymentDetail_result_code == 'Success') {
                    $this->model_extension_payment_moka->addTransaciton($paymentDetail_data->PaymentDetail, $order_id);
                }

                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_moka_order_status'));

                $this->response->redirect($this->url->link('checkout/success'));
            } else {
                $this->response->redirect($this->url->link('checkout/failure'));
            }
        }
    }

    private function validate()
    {
        $this->load->language('extension/payment/moka');
        $this->load->model('extension/payment/moka');

        if (!isset($this->request->post['card_holder_full_name']) || utf8_strlen($this->request->post['card_holder_full_name']) < 1 || utf8_strlen($this->request->post['card_holder_full_name']) > 80) {
            $this->error['warning'] = $this->language->get('error_card_holder_full_name');
        }

        if (!isset($this->request->post['card_number']) || utf8_strlen($this->request->post['card_number']) < 1 || utf8_strlen($this->request->post['card_number']) > 19) {
            $this->error['warning'] = $this->language->get('error_card_number');
        }

        if (!isset($this->request->post['card_cvc_number']) || utf8_strlen($this->request->post['card_cvc_number']) < 1 || utf8_strlen($this->request->post['card_cvc_number']) > 4) {
            $this->error['warning'] = $this->language->get('error_card_cvc_number');
        }

        return !$this->error;
    }

    private function setCookieSameSite($name, $value, $expire, $path, $domain, $secure, $httponly)
    {
        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httponly);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => 'None',
                'secure' => $secure,
                'httponly' => $httponly
            ]);
        }
    }
}
